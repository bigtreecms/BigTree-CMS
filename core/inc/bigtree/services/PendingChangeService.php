<?php
	namespace BigTree\Services;

	use BigTree\Api\Entity;
	use BigTree\Api\Hooks;
	use BigTree\Api\Json;
	use BigTree\Api\Pagination;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Sanitize;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Services\AI\OpenGraphDomain;
	use BigTree\Services\AI\PreviewValue;
	use BigTree\Services\AI\Tools\PendingChangeToolBackend;
	use BigTreeAutoModule;
	use BigTreeJSONDB;
	use SQL;

	/**
	 * Pending change inspection and approval/rejection.
	 *
	 * "Approve" semantics differ per type:
	 *   - "EDIT" (existing row): publishPendingItem applies changes to live table
	 *   - "NEW" (new entry): publishPendingItem promotes the pending change to a real row
	 *   - page changes: hand off to PageService when migrated; for v1 we publish only
	 *     module entries and mark page changes as out-of-scope for the SPA approval flow.
	 */
	class PendingChangeService implements PendingChangeToolBackend {
		public function list(Request $request) {
			$mine_only = $request->queryBool("mine");
			$me = (int)$request->user->id;

			$where = $mine_only ? " WHERE user = ?" : "";
			$args = $mine_only ? [$me] : [];

			return Pagination::paginate(
				$request,
				"SELECT COUNT(*) FROM bigtree_pending_changes" . $where,
				"SELECT id, user, date, title, `table`, item_id, type, module, pending_page_parent
				 FROM bigtree_pending_changes" . $where . " ORDER BY date DESC, id DESC",
				$args,
				function ($r) {

					return [
						"id" => (int)$r["id"],
						"user" => (int)$r["user"],
						"date" => $r["date"],
						"title" => $r["title"],
						"table" => $r["table"],
						"item_id" => $r["item_id"] !== null ? (int)$r["item_id"] : null,
						"type" => $r["type"],
						"module" => $r["module"],
						"pending_page_parent" => (int)$r["pending_page_parent"],
					];
				},
				100
			);
		}

		/**
		 * GET /pending-changes/export?group=pages|{module id} — the rows of one
		 * section of the pending changes screen as a table the SPA saves as CSV,
		 * limited to changes the caller can publish (legacy export-changes did the
		 * same from getPublishableChanges).
		 *
		 * Pages: Author, Page, Type, Updated. A module: Author plus its view's
		 * columns, read from the draft (over the live row for an edit), or Author
		 * and Item ID when the module has no view of that table. View parsers
		 * aren't run, so a column shows its stored value.
		 *
		 * @return Response { filename, headers: string[], rows: string[][] }
		 */
		public function export(Request $request) {
			$group = $request->queryString("group");
			$user = $request->user;

			if ($group === "pages") {
				$changes = SQL::fetchAll(
					"SELECT * FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' ORDER BY date DESC, id DESC"
				);
			} else {
				$changes = SQL::fetchAll(
					"SELECT * FROM bigtree_pending_changes WHERE module = ? ORDER BY date DESC, id DESC",
					$group
				);
			}

			$changes = array_values(array_filter($changes, function ($row) use ($user) {

				return $this->isPublisherFor($user, $row);
			}));
			$names = $this->authorNames($changes);

			if ($group === "pages") {
				$rows = [];

				foreach ($changes as $change) {
					$draft = Json::decode($change["changes"]);
					$is_new = $change["type"] === "NEW";
					$title = $draft["nav_title"] ?? null;

					if (!$is_new && $title === null) {
						$title = SQL::fetchSingle("SELECT nav_title FROM bigtree_pages WHERE id = ?", (int)$change["item_id"]);
					}

					if (!$is_new && (int)$change["item_id"] === 0 && !$title) {
						$title = "Home";
					}

					$rows[] = [
						$names[(int)$change["user"]] ?? "",
						self::plainText($title),
						$is_new ? "New" : "Edit",
						(string)$change["date"],
					];
				}

				return Response::ok([
					"filename" => "pending-changes-pages.csv",
					"headers" => ["Author", "Page", "Type", "Updated"],
					"rows" => $rows,
				]);
			}

			$module = $group !== "" ? BigTreeJSONDB::get("modules", $group) : null;

			if (!is_array($module)) {
				throw new BadRequestException("group must be \"pages\" or a module id", "bad_group");
			}

			$table = (string)($changes[0]["table"] ?? "");
			$fields = [];

			foreach ((array)($module["views"] ?? []) as $view) {
				if (is_array($view) && ($view["table"] ?? "") === $table && is_array($view["fields"] ?? null)) {
					$fields = $view["fields"];

					break;
				}
			}

			$headers = ["Author"];

			foreach ($fields as $column => $field) {
				$headers[] = (string)($field["title"] ?? $column);
			}

			if (!$fields) {
				$headers[] = "Item ID";
			}

			$rows = [];

			foreach ($changes as $change) {
				$row = [$names[(int)$change["user"]] ?? ""];
				$is_new = $change["type"] === "NEW" || empty($change["item_id"]);
				$item_id = $is_new ? "p" . $change["id"] : (string)$change["item_id"];

				if (!$fields) {
					$row[] = $item_id;
					$rows[] = $row;

					continue;
				}

				$values = Json::decode($change["changes"]);

				if (!$is_new && $table !== "") {
					$live = SQL::fetch("SELECT * FROM `" . str_replace("`", "", $table) . "` WHERE id = ?", (int)$change["item_id"]);
					$values = array_merge(is_array($live) ? $live : [], $values);
				}

				foreach (array_keys($fields) as $column) {
					$row[] = self::plainText($values[$column] ?? "");
				}

				$rows[] = $row;
			}

			$slug = trim(preg_replace("/[^a-z0-9]+/", "-", strtolower((string)($module["name"] ?? "module"))), "-");

			return Response::ok([
				"filename" => "pending-changes-" . ($slug ?: "module") . ".csv",
				"headers" => $headers,
				"rows" => $rows,
			]);
		}

		/** Display names for the authors of $changes, keyed by user id. */
		private function authorNames(array $changes): array {
			$ids = array_values(array_unique(array_filter(array_map(function ($row) {

				return (int)$row["user"];
			}, $changes))));

			if (!$ids) {

				return [];
			}

			$names = [];
			$placeholders = implode(",", array_fill(0, count($ids), "?"));

			foreach (SQL::fetchAll("SELECT id, name FROM bigtree_users WHERE id IN ($placeholders)", ...$ids) as $user) {
				$names[(int)$user["id"]] = (string)$user["name"];
			}

			return $names;
		}

		/** A cell value as plain text: arrays as JSON, markup stripped, entities decoded. */
		private static function plainText($value): string {
			if (is_array($value)) {
				$value = json_encode($value);
			}

			return trim(html_entity_decode(strip_tags((string)$value), ENT_QUOTES, "UTF-8"));
		}

		public function get(Request $request) {
			$id = $request->id();
			$row = Entity::findOrFail("bigtree_pending_changes", $id, "Pending change");
			$this->enforceVisibility($request->user, $row);

			$row["changes"] = Json::decode($row["changes"]);
			$row["mtm_changes"] = Json::decode($row["mtm_changes"]);
			$row["tags_changes"] = Json::decode($row["tags_changes"]);
			$row["open_graph_changes"] = Json::decode($row["open_graph_changes"]);
			$row["id"] = (int)$row["id"];
			$row["user"] = (int)$row["user"];

			return Response::ok($row);
		}

		public function approve(Request $request) {
			$id = $request->id();
			$row = Entity::findOrFail("bigtree_pending_changes", $id, "Pending change");
			$this->enforcePublisher($request->user, $row);

			$this->applyPendingChange($row, $request->user);

			return Response::noContent();
		}

		/**
		 * Apply a queued change live and drop the queue row. Shared by approve() (REST)
		 * and the publish_pending_change AI tool so the two paths can never diverge.
		 * The caller is responsible for the publisher check first.
		 *
		 * @param array<string,mixed> $row The raw pending-change row.
		 * @param object|array $user
		 * @return array<string,mixed> A small structured outcome.
		 */
		private function applyPendingChange(array $row, $user): array {
			$id = (int)$row["id"];

			// Tag ids the blob names that no longer name a live tag. Read before the
			// write, because the write is what drops them — and disclosed afterwards
			// rather than silently swallowed, which is how the unrepaired blob already
			// behaved.
			$stale_tags = $this->aiStaleTagIds($row);

			// Pages: apply the queued change live and drop the queue row.
			if ($row["table"] === "bigtree_pages") {
				(new PageService())->publishPendingChange($row, $user);

				return self::withStaleTagNote(
					["mode" => "published", "table" => "bigtree_pages", "type" => (string)$row["type"]],
					$stale_tags
				);
			}

			// Module entries
			$module = $row["module"] ? BigTreeJSONDB::get("modules", $row["module"]) : null;

			if (!$module) {
				throw new BadRequestException("Cannot resolve module for this change", "module_unresolved");
			}

			$changes = BigTreeAutoModule::sanitizeData($row["table"], Json::decode($row["changes"]));
			$mtm_changes = Json::decode($row["mtm_changes"]);
			$tags_changes = Json::decode($row["tags_changes"]);
			$open_graph_changes = Json::decode($row["open_graph_changes"]);

			// The entry half of the same filter, and here it is not a cosmetic one:
			// BigTreeAutoModule::updateItem/createItem insert tag ids blindly — no
			// existence check, unlike PageService::syncTags — so an id whose tag has
			// been deleted trips bigtree_tags_rel_ibfk_1 *after* the data UPDATE has
			// landed and every existing relation has been wiped. What survived was a
			// half-published row with no tags, a change row that was never deleted, no
			// recache, no re-index, no audit row and no event, and a change that threw
			// again on every retry, forever.
			if ($stale_tags) {
				$tags_changes = TagService::partitionExistingTagIds($tags_changes)["kept"];
			}

			// Branch on item_id, not type: a re-edited NEW draft keeps item_id = null
			// while submitChange flips its type to "EDIT", so type is unreliable here.
			if ($row["item_id"] !== null) {
				// Existing entry — apply the change in place. updateItem deletes the
				// pending change row itself once the live row is updated.
				$item_id = (int)$row["item_id"];

				// …but only if the entry is still there. updateItem's UPDATE matches no
				// rows against a deleted entry, deletes the change anyway, and reports
				// success — so "publish this change" destroyed the change and said it
				// had published it.
				if (!SQL::fetchSingle("SELECT id FROM `" . $row["table"] . "` WHERE id = ?", $item_id)) {

					throw new BadRequestException(
						"The entry this change belongs to has been deleted, so the change can't be published.",
						"entry_deleted"
					);
				}

				BigTreeAutoModule::updateItem($row["table"], $item_id, $changes, $mtm_changes, $tags_changes, $open_graph_changes);
				$promoted = false;
			} else {
				// New entry — promote the pending change to a real row. publishPendingItem
				// deletes the pending change row itself (the id is the publishing change).
				$item_id = BigTreeAutoModule::publishPendingItem($row["table"], $id, $changes, $mtm_changes, $tags_changes, $open_graph_changes);
				$promoted = true;
			}

			// Re-key the draft's tracked resource allocations from "p{change}" onto the
			// now-live row (mirrors legacy dashboard/approve-change.php).
			$this->reallocatePendingResources($row["table"], $item_id, $id);

			if (!empty($row["publish_hook"]) && is_callable($row["publish_hook"])) {
				call_user_func($row["publish_hook"], $row["table"], $item_id, $changes, $mtm_changes, $tags_changes, $open_graph_changes);
			}

			// The row is live now, so say so: the legacy primitives fire nothing, so
			// without this an extension subscribing to api.module_entry.updated would
			// see publisher writes and miss every editor-authored change once it was
			// approved — which is the assistant's default path, since an editor's entry
			// write queues by construction. The page half of this same method has
			// always fired, because publishPendingChange replays the change through
			// performCreate/performUpdate.
			//
			// Same event names as the live writes: a subscriber should not have to learn
			// a second vocabulary for "this row is now live", and there is no double-fire
			// to worry about because updateItem/publishPendingItem fire nothing.
			$fresh = BigTreeAutoModule::getItem($row["table"], $item_id);
			$payload = [
				"module" => (string)$row["module"],
				"table" => (string)$row["table"],
				"id" => (int)$item_id,
				"item" => $fresh["item"] ?? $fresh,
			];

			if ($promoted) {
				Hooks::fire("module_entry.created", $payload);
			} else {
				Hooks::fire("module_entry.updated", $payload);
			}

			return self::withStaleTagNote(
				["mode" => "published", "table" => (string)$row["table"], "item_id" => (int)$item_id],
				$stale_tags
			);
		}

		/**
		 * The concurrent-edit lock descriptor for the record a queued change writes.
		 *
		 * Publishing and rejecting were the only writers of a lockable record that
		 * emitted no descriptor, and they write the most contended one there is: an
		 * editor's draft and the publisher reviewing it are the two people most likely
		 * to be in the same record at the same time — the exact case aiEntryLock argues
		 * it must cover. The lockable kinds are the three screens that call useLock:
		 * bigtree_pages (PageEdit), module:{id} (ModuleEntryEdit) and config:settings
		 * (SettingEdit); a pending change only ever targets the first two.
		 *
		 * A NEW *page* draft emits nothing, matching aiPageLock and PageEdit's own
		 * `enabled: !draft` — the page editor takes no lock on a draft, so there is
		 * never a holder to name. A NEW *entry* draft does emit one, keyed by its
		 * "p"-prefixed id, because ModuleEntryEdit locks a draft by whatever id is in
		 * the URL. Advisory like every other lock note: approving is the take-over
		 * decision.
		 *
		 * @param array<string,mixed> $row A bigtree_pending_changes row.
		 * @return array<string,mixed>
		 */
		private function aiChangeLock(array $row): array {
			$item_id = $row["item_id"] !== null ? (int)$row["item_id"] : 0;

			if ((string)$row["table"] === "bigtree_pages") {

				return $item_id > 0 ? ["table" => "bigtree_pages", "id" => $item_id] : [];
			}

			$module = (string)($row["module"] ?? "");

			if ($module === "") {

				return [];
			}

			return [
				"table" => "module:{$module}",
				"id" => $item_id > 0 ? $item_id : "p" . (int)$row["id"],
			];
		}

		/**
		 * Why a queued change can no longer be published, in the words the write paths
		 * use — or "" when its target is still there.
		 *
		 * The staging half of the guards in PageService::publishPendingChange and
		 * applyPendingChange. Both of those refuse at approval; this is what lets the
		 * card refuse instead, so a change stranded by a deleted page or entry never
		 * becomes a proposal the approver can only fail. Rejecting a stranded change
		 * stays possible — that is the way out of the queue, so aiValidateRejectChange
		 * deliberately does not ask this.
		 *
		 * @param array<string,mixed> $row A bigtree_pending_changes row.
		 */
		private function aiMissingTargetError(array $row): string {
			$table = (string)$row["table"];
			$item_id = $row["item_id"] !== null ? (int)$row["item_id"] : 0;

			if ($table === "bigtree_pages") {
				if ((string)$row["type"] === "NEW") {
					$changes = Json::decode($row["changes"] ?? "");
					$parent = (int)($changes["parent"] ?? $row["pending_page_parent"] ?? 0);

					return $parent > 0 && !SQL::exists("bigtree_pages", $parent)
						? "The parent page this new page belongs under has been deleted, so the change can't be "
							. "published."
						: "";
				}

				return $item_id > 0 && !SQL::exists("bigtree_pages", $item_id)
					? "The page this change belongs to has been deleted, so the change can't be published."
					: "";
			}

			if ($item_id < 1 || $table === "") {

				return "";
			}

			return SQL::fetchSingle("SELECT id FROM `" . str_replace("`", "", $table) . "` WHERE id = ?", $item_id)
				? ""
				: "The entry this change belongs to has been deleted, so the change can't be published.";
		}

		/**
		 * The tag ids a queued change would replay that no longer name a live tag.
		 *
		 * Reads whichever copy of the list the replay actually uses: a page's tags ride
		 * inside the `changes` blob (PageService::publishPendingChange replays that),
		 * an entry's live in `tags_changes`. A page change with no `tags` key isn't
		 * touching tags at all — performCreate/performUpdate only sync when the key is
		 * present — so it has nothing to go stale.
		 *
		 * @param array<string,mixed> $row A bigtree_pending_changes row.
		 * @return list<int>
		 */
		private function aiStaleTagIds(array $row): array {
			if ((string)$row["table"] === "bigtree_pages") {
				$changes = Json::decode($row["changes"] ?? "");
				$ids = is_array($changes) && is_array($changes["tags"] ?? null) ? $changes["tags"] : [];
			} else {
				$ids = Json::decode($row["tags_changes"] ?? "");
			}

			return TagService::partitionExistingTagIds($ids)["dropped"];
		}

		/**
		 * Fold the stale-tag disclosure into a publish outcome, when there is one.
		 *
		 * @param array<string,mixed> $out
		 * @param list<int> $stale_tags
		 * @return array<string,mixed>
		 */
		private static function withStaleTagNote(array $out, array $stale_tags): array {
			$note = self::aiStaleTagNote($stale_tags, true);

			if ($note !== "") {
				$out["note"] = $note;
			}

			return $out;
		}

		/**
		 * The one sentence both halves of a publish use for tags the replay can't
		 * apply, so the card and the result describe the same drop.
		 *
		 * @param list<int> $stale_tags
		 * @param bool $applied True once the publish has run (past tense).
		 */
		private static function aiStaleTagNote(array $stale_tags, bool $applied): string {
			if (!$stale_tags) {

				return "";
			}

			$count = count($stale_tags);
			$one = $count === 1;
			$subject = $count . " tag" . ($one ? "" : "s") . " on this change";

			return $applied
				? $subject . " no longer existed and " . ($one ? "was" : "were") . " not applied."
				: $subject . " no longer exist" . ($one ? "s" : "") . " and won't be applied.";
		}

		public function reject(Request $request) {
			$id = $request->id();
			$row = Entity::findOrFail("bigtree_pending_changes", $id, "Pending change");
			$this->enforcePublisher($request->user, $row);

			SQL::delete("bigtree_pending_changes", $id);
			// Discard the draft's resource allocations (mirrors legacy reject-change.php).
			ResourceAllocationService::deallocateResources($row["table"], "p".$id);
			$this->fireChangeRejected($id, (string)$row["table"]);

			return Response::noContent();
		}

		// — helpers —

		/**
		 * The pending_change.rejected event, from the one place both rejecters call.
		 *
		 * It used to fire from the assistant only, so an extension listening for a
		 * discarded draft saw assistant rejections and missed every one made through the
		 * SPA — the same inversion page.archived / unarchived / moved once had, one
		 * service over.
		 */
		private function fireChangeRejected(int $id, string $table, string $via = ""): void {
			$payload = ["id" => $id, "table" => $table];

			if ($via !== "") {
				$payload["via"] = $via;
			}

			Hooks::fire("pending_change.rejected", $payload);
		}

		/**
		 * Re-key a published draft's resource allocations from its "p{change}" entry
		 * onto the new live row id. updateResourceAllocation is a legacy instance
		 * method (now static on ResourceAllocationService).
		 */
		private function reallocatePendingResources(string $table, $live_id, int $change_id): void {
			ResourceAllocationService::updateResourceAllocation($table, $live_id, $change_id);
		}

		private function enforceVisibility($user, array $row) {
			if ((int)$user->level >= 1) {
				return;
			}

			if ((int)$row["user"] === (int)$user->id) {
				return;
			}

			// Editors can see changes for items they have at least view access to.
			if ($row["module"] && PermissionService::userHasModuleAccess($user, $row["module"], "v")) {
				return;
			}
			throw new AuthorizationException("Not your pending change");
		}

		private function enforcePublisher($user, array $row) {
			if (!$this->isPublisherFor($user, $row)) {
				throw new AuthorizationException("Publisher access required");
			}
		}

		/**
		 * Non-throwing publisher check for a pending change, so the AI validation path
		 * can turn "not a publisher" into a denial instead of an exception. Publisher
		 * rights resolve against the affected page (or parent for a NEW draft) or the
		 * change's module; global admins/devs always qualify.
		 *
		 * @param object|array $user
		 * @param array<string,mixed> $row
		 */
		private function isPublisherFor($user, array $row): bool {
			if (PermissionService::level($user) >= 1) {

				return true;
			}

			if ($row["table"] === "bigtree_pages") {
				$page_id = (int)($row["item_id"] ?? 0) ?: (int)($row["pending_page_parent"] ?? 0);

				return PermissionService::userHasPageAccess($user, $page_id, "p");
			}

			$module = $row["module"] ?? "";

			return $module && PermissionService::userHasModuleAccess($user, $module, "p");
		}

		// — AI tool seam (PendingChangeToolBackend) —
		//
		// get_pending_changes surfaces the changes relevant to the user (their own
		// submissions and ones they can publish); publish_pending_change is a two-phase
		// publisher action that reuses applyPendingChange, so the AI path and the REST
		// approve path stay identical.

		/**
		 * Pending changes relevant to the acting user: ones they submitted and ones
		 * they may publish. Each row is tagged mine / can_publish for the model.
		 *
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiPendingChanges(int $limit, $user): array {
			$me = (int)(is_object($user) ? ($user->id ?? 0) : ($user["id"] ?? 0));
			$limit = max(1, $limit);
			$columns = "id, user, date, title, `table`, item_id, type, module, pending_page_parent";

			// An administrator or developer can publish every queued change
			// (isPublisherFor short-circuits on level), so the newest $limit rows are
			// exactly the answer — no scan and no cap at all.
			if (PermissionService::level($user) >= 1) {
				$rows = SQL::fetchAll(
					"SELECT {$columns} FROM bigtree_pending_changes ORDER BY date DESC, id DESC LIMIT " . $limit
				);
				$out = [];

				foreach ($rows as $row) {
					$out[] = $this->aiPresentPendingRow($row, (int)$row["user"] === $me, true);
				}

				return ["pending_changes" => $out];
			}

			// For an editor, publishability is a per-row permission computation with
			// no SQL equivalent. It used to be answered by scanning the newest 500
			// rows, which on a busy queue hid older changes outright. Walk the queue in
			// pages instead and stop as soon as $limit qualifying rows are collected:
			// bounded memory, and nothing is invisible just for being old.
			$out = [];
			$offset = 0;

			do {
				$rows = SQL::fetchAll(
					"SELECT {$columns} FROM bigtree_pending_changes
					 ORDER BY date DESC, id DESC LIMIT " . self::AI_PENDING_SCAN_BATCH . " OFFSET " . $offset
				);

				foreach ($rows as $row) {
					$mine = (int)$row["user"] === $me;
					$can_publish = $this->isPublisherFor($user, $row);

					if (!$mine && !$can_publish) {

						continue;
					}

					$out[] = $this->aiPresentPendingRow($row, $mine, $can_publish);

					if (count($out) >= $limit) {

						return ["pending_changes" => $out];
					}
				}

				$offset += self::AI_PENDING_SCAN_BATCH;
			} while (count($rows) === self::AI_PENDING_SCAN_BATCH);

			return ["pending_changes" => $out];
		}

		/** Rows read per pass when walking the queue for an editor. */
		private const AI_PENDING_SCAN_BATCH = 500;

		/**
		 * @param array<string,mixed> $row
		 * @return array<string,mixed>
		 */
		private function aiPresentPendingRow(array $row, bool $mine, bool $can_publish): array {
			$out = [
				"id" => (int)$row["id"],
				"title" => (string)$row["title"],
				"table" => (string)$row["table"],
				"item_id" => $row["item_id"] !== null ? (int)$row["item_id"] : null,
				"type" => (string)$row["type"],
				"module" => (string)$row["module"],
				"mine" => $mine,
				"can_publish" => $can_publish,
				"date" => $row["date"],
			];

			// Carried on the list read as well as on the card, so the model reviewing
			// the queue can see it before it proposes anything. The
			// changes blob is deliberately not in the list query — it is a LONGTEXT and
			// the editor path walks the queue 500 rows at a time — so it is read only
			// for the rows that are actually returned, which the caller's limit bounds.
			if (!array_key_exists("changes", $row)) {
				$row["changes"] = SQL::fetchSingle(
					"SELECT changes FROM bigtree_pending_changes WHERE id = ?",
					(int)$row["id"]
				);
			}

			$incomplete = $this->aiIncompleteRequired($row);

			if ($incomplete) {
				$out["incomplete_required"] = $incomplete;
			}

			return $out;
		}

		/**
		 * One pending change with the actual field-level diff it would apply.
		 *
		 * The list read and the publish preview both showed only title/table/type, so
		 * an approver confirmed a publish without ever seeing what it would change.
		 * Visible to the change's own author as well as to its publishers.
		 *
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiGetPendingChange(int $id, $user): array {
			if ($id < 1) {

				return ["error" => "A pending-change id is required."];
			}

			$row = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $id);

			if (!$row) {

				return ["error" => "Pending change {$id} does not exist."];
			}

			$mine = (int)$row["user"] === (int)(is_object($user) ? $user->id : ($user["id"] ?? 0));
			$can_publish = $this->isPublisherFor($user, $row);

			if (!$mine && !$can_publish) {

				return ["denied" => "You can only view your own pending changes, or ones you can publish."];
			}

			return ["pending_change" => [
				"id" => $id,
				"title" => (string)$row["title"],
				"table" => (string)$row["table"],
				"item_id" => $row["item_id"] !== null ? (int)$row["item_id"] : null,
				"type" => (string)$row["type"],
				"module" => (string)$row["module"],
				"mine" => $mine,
				"can_publish" => $can_publish,
				"date" => $row["date"],
				"is_new_item" => $row["item_id"] === null,
				"changes" => $this->aiChangeDiff($row),
				// What a publisher would put live unfilled. Said on the detail read as
				// well as on the publish card, because this is the read the model makes
				// when it is deciding whether to propose the publish at all.
				"incomplete_required" => $this->aiIncompleteRequired($row),
			]];
		}

		/**
		 * The required fields a queued change would put live still empty.
		 *
		 * `PageService::aiPageCreateGate` and
		 * `AutoModuleService::aiEntryCreateGate` both relax their blocked-required
		 * refusal for a draft — deliberately, because a draft is the "a human completes
		 * this" case — and both disclose it on the create card. Nothing between that
		 * card and the live site ever asked again: this seam built a diff and staged
		 * "It will go live immediately once you approve" with no required check at all,
		 * and the queue read carried no marker either, so the model reviewing the queue
		 * couldn't see it.
		 *
		 * Disclosed rather than blocked: refusing would make the
		 * assistant unable to publish changes the admin's own pending-changes screen
		 * publishes, and would strand the draft with no in-chat way out.
		 *
		 * @param array<string,mixed> $row A bigtree_pending_changes row.
		 * @return list<string>
		 */
		private function aiIncompleteRequired(array $row): array {
			$changes = Json::decode($row["changes"] ?? "");
			$table = (string)$row["table"];
			$item_id = $row["item_id"] !== null ? (int)$row["item_id"] : 0;

			if ($table === "bigtree_pages") {
				$live = $item_id > 0
					? SQL::fetch("SELECT template, resources FROM bigtree_pages WHERE id = ?", $item_id)
					: null;
				// A change may switch the template, in which case the required set is
				// the incoming one's — the same rule aiTemplateSwitchGaps applies.
				$template = trim((string)($changes["template"] ?? ($live["template"] ?? "")));

				if ($template === "") {

					return [];
				}

				$stored = is_array($live) ? Json::decode($live["resources"] ?? "") : [];
				$proposed = is_array($changes["resources"] ?? null) ? $changes["resources"] : [];

				return (new PageService())->aiRequiredUnsettableResources($template, array_merge($stored, $proposed));
			}

			$existing = [];

			if ($item_id > 0 && $table !== "") {
				$live = SQL::fetch("SELECT * FROM `" . str_replace("`", "", $table) . "` WHERE id = ?", $item_id);
				$existing = is_array($live) ? $live : [];
			}

			return (new AutoModuleService())->aiChangeBlockedRequired(
				(string)($row["module"] ?? ""),
				$table,
				array_merge($existing, $changes)
			);
		}

		/**
		 * The one sentence the create card said and the publish card didn't, so both
		 * halves of a draft's life describe it the same way.
		 *
		 * @param list<string> $incomplete
		 */
		private static function aiIncompleteRequiredNote(array $incomplete): string {
			if (!$incomplete) {

				return "";
			}

			$one = count($incomplete) === 1;

			return " Note that " . implode(", ", $incomplete) . " " . ($one ? "is" : "are")
				. " required and still empty — the assistant can't fill "
				. ($one ? "it" : "them") . ", so publishing puts the "
				. ($one ? "gap" : "gaps") . " live.";
		}

		/**
		 * A compact field-level diff for a pending change: each changed field with the
		 * value it would replace (when the change targets an existing row).
		 *
		 * Values are length-capped so a large HTML body can't flood a proposal card
		 * or a model's context.
		 *
		 * @param array<string,mixed> $row a bigtree_pending_changes row
		 * @return list<array<string,mixed>>
		 */
		private function aiChangeDiff(array $row): array {
			$changes = Json::decode($row["changes"]);
			$table = (string)$row["table"];
			$is_page = $table === "bigtree_pages";

			// A NEW change has no live row to compare against.
			$existing = [];

			if ($row["item_id"] !== null && $table !== "") {
				$live = SQL::fetch("SELECT * FROM `" . str_replace("`", "", $table) . "` WHERE id = ?", (int)$row["item_id"]);
				$existing = is_array($live) ? $live : [];
			}

			$out = [];

			foreach ($changes as $column => $value) {
				// Page tags ride inside `changes` as a raw array of numeric ids, which
				// tells an approver nothing about what is being published.
				if ($is_page && $column === "tags") {
					$out[] = [
						"column" => "tags",
						"to" => implode(", ", $this->aiTagNamesFor($value)),
						"from" => implode(", ", $this->aiLiveTagNames($table, (int)$row["item_id"])),
					];

					continue;
				}

				$entry = [
					"column" => (string)$column,
					"to" => $this->aiDiffValue($value),
				];

				if ($existing && array_key_exists($column, $existing)) {
					$entry["from"] = $this->aiDiffValue($existing[$column]);
				}

				$out[] = $entry;
			}

			// The other three change columns were decoded by nobody. For a *module
			// entry* tags and Open Graph live exclusively there, so a draft whose only
			// change was its tag set read as "0 fields affected" and the approver
			// published a tag rewrite sight-unseen.
			$tags_changes = Json::decode($row["tags_changes"] ?? "");

			if (!$is_page && $tags_changes) {
				$out[] = [
					"column" => "tags",
					"to" => implode(", ", $this->aiTagNamesFor($tags_changes)),
					"from" => implode(", ", $this->aiLiveTagNames($table, (int)$row["item_id"])),
				];
			}

			$open_graph = Json::decode($row["open_graph_changes"] ?? "");

			foreach (OpenGraphDomain::FIELDS as $label => $key) {
				if (array_key_exists($key, $open_graph)) {
					$out[] = ["column" => $label, "to" => $this->aiDiffValue($open_graph[$key])];
				}
			}

			$mtm = Json::decode($row["mtm_changes"] ?? "");

			foreach ($mtm as $relation) {
				if (!is_array($relation)) {

					continue;
				}

				$out[] = [
					"column" => (string)($relation["table"] ?? "related records"),
					"to" => count((array)($relation["data"] ?? [])) . " linked record(s)",
				];
			}

			return $out;
		}

		/**
		 * Resolve a list of tag ids to their names, so a diff reads as words rather
		 * than as a bare array of integers.
		 *
		 * @param mixed $ids
		 * @return list<string>
		 */
		private function aiTagNamesFor($ids): array {
			$ids = array_values(array_filter(array_map("intval", (array)$ids)));

			if (!$ids) {

				return [];
			}

			$names = SQL::fetchAllSingle(
				"SELECT tag FROM bigtree_tags WHERE id IN (" . Sanitize::placeholders($ids) . ") ORDER BY tag",
				...$ids
			);

			return array_values(array_map("strval", $names ?: []));
		}

		/**
		 * The tag names currently attached to the live record a change targets.
		 *
		 * @return list<string>
		 */
		private function aiLiveTagNames(string $table, int $entry_id): array {
			if ($table === "" || $entry_id < 1) {

				return [];
			}

			$names = SQL::fetchAllSingle(
				"SELECT t.tag FROM bigtree_tags_rel r
				 JOIN bigtree_tags t ON t.id = r.tag
				 WHERE r.`table` = ? AND r.entry = ? ORDER BY t.tag",
				$table,
				(string)$entry_id
			);

			return array_values(array_map("strval", $names ?: []));
		}

		/**
		 * One side of a queued change's diff, resolved for a human through PreviewValue.
		 *
		 * A change blob is arbitrary — its keys are whichever page or entry columns the
		 * author touched, and there is no schema here to consult — so this gets the
		 * no-field treatment: link tokens decoded, then capped. That is the half that
		 * matters on this card, since a queued page edit is exactly where an `ipl://`
		 * token lives.
		 *
		 * @param mixed $value
		 */
		private function aiDiffValue($value): string {

			return PreviewValue::forHuman($value);
		}

		/**
		 * Validate publishing a pending change without applying it: existence and
		 * publisher rights. Returns denied | error | ok+summary+preview+payload.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidatePublishChange(array $args, $user): array {
			$id = (int)($args["change_id"] ?? 0);

			if ($id < 1) {

				return ["error" => "A pending-change id is required."];
			}

			$row = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $id);

			if (!$row) {

				return ["error" => "Pending change {$id} does not exist."];
			}

			if (!$this->isPublisherFor($user, $row)) {

				return ["denied" => "Publishing this change requires publisher access, which you do not have."];
			}

			$title = trim((string)$row["title"]) ?: ("change #{$id}");

			// Without the diff the approver is confirming a publish sight-unseen —
			// the preview named the change but never said what it would do.
			$diff = $this->aiChangeDiff($row);
			$count = count($diff);

			// The target can go away inside the draft's life — a page delete used to
			// leave both its EDIT draft and any NEW child draft in the queue, and
			// nothing between the queue read and the approval noticed. Refuse on the
			// card rather than at the approval, where an EDIT threw a bare 404 and a
			// NEW succeeded into an orphan.
			$missing = $this->aiMissingTargetError($row);

			if ($missing !== "") {

				return ["error" => $missing];
			}

			// Recomputed here rather than carried from the create card: the draft may
			// have been re-saved since, and the template or form behind it can gain a
			// required field inside its life.
			$incomplete = $this->aiIncompleteRequired($row);
			$preview = [
				"action" => "publish_pending_change",
				"change_id" => $id,
				"title" => $title,
				"table" => (string)$row["table"],
				"type" => (string)$row["type"],
				"is_new_item" => $row["item_id"] === null,
				"fields" => $diff,
			];

			if ($incomplete) {
				// The key ProposalCard already renders as the needs-a-person warning, so
				// the publish card discloses exactly what the create card disclosed.
				$preview["incomplete_required"] = $incomplete;
			}

			// Tags the blob names that no longer exist. Staged here *and* re-derived at
			// approval (withStaleTagNote), because a merge or a delete can land inside
			// the card's own life — and a dropped tag must not happen quietly.
			$stale_note = self::aiStaleTagNote($this->aiStaleTagIds($row), false);

			if ($stale_note !== "") {
				$preview["warning"] = $stale_note;
			}

			return [
				"ok" => true,
				"summary" => "Publish the pending change “{$title}”"
					. ($count ? " ({$count} field" . ($count === 1 ? "" : "s") . " affected)" : "")
					. ". It will go live immediately once you approve."
					. self::aiIncompleteRequiredNote($incomplete)
					. ($stale_note !== "" ? " " . $stale_note : ""),
				"preview" => $preview,
				"payload" => [
					"change_id" => $id,
				],
				// Pending changes collapse in place, so the blob this card diffed can be
				// wholly rewritten before the publisher clicks Approve — publishing
				// content they never saw, under an audit row saying they approved it.
				"fingerprint" => ["type" => "pending_change", "id" => $id],
				"lock" => $this->aiChangeLock($row),
			];
		}

		/**
		 * Validate rejecting (discarding) a pending change.
		 *
		 * The assistant could publish a change but never decline one, so a reviewer
		 * could only ever say yes in chat. Allowed for a publisher *or* the change's
		 * own author — withdrawing your own unpublished draft needs no approval from
		 * anyone. (The REST reject route is publisher-only; the author case is the
		 * deliberate difference, and it can only ever discard the author's own work.)
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateRejectChange(array $args, $user): array {
			$id = (int)($args["change_id"] ?? 0);

			if ($id < 1) {

				return ["error" => "A pending-change id is required."];
			}

			$row = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $id);

			if (!$row) {

				return ["error" => "Pending change {$id} does not exist."];
			}

			$mine = (int)$row["user"] === (int)(is_object($user) ? $user->id : ($user["id"] ?? 0));
			$can_publish = $this->isPublisherFor($user, $row);

			if (!$mine && !$can_publish) {

				return ["denied" => "You can only reject your own pending changes, or ones you have publisher access to."];
			}

			$title = trim((string)$row["title"]) ?: ("change #{$id}");
			$diff = $this->aiChangeDiff($row);
			$is_new = $row["item_id"] === null;

			// Rejecting a NEW draft throws away content that exists nowhere else;
			// rejecting an EDIT just drops the proposed changes. Say which.
			$consequence = $is_new
				? " The drafted content will be discarded — it has never been published, so it will be lost."
				: " The live version stays as it is; only the proposed changes are discarded.";

			return [
				"ok" => true,
				"summary" => "Reject the pending change “{$title}”." . $consequence . " This cannot be undone.",
				"preview" => [
					"action" => "reject_pending_change",
					"change_id" => $id,
					"title" => $title,
					"table" => (string)$row["table"],
					"type" => (string)$row["type"],
					"is_new_item" => $is_new,
					"mine" => $mine,
					"destructive" => true,
					"fields" => $diff,
				],
				"payload" => ["change_id" => $id],
				// The mirror of publish's problem: a stale reject discards work newer
				// than the card described.
				"fingerprint" => ["type" => "pending_change", "id" => $id],
				"lock" => $this->aiChangeLock($row),
			];
		}

		/**
		 * Apply an approved rejection: drop the queued change and its draft resource
		 * allocations, exactly as the REST reject route does. Re-checks rights.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws AuthorizationException
		 */
		public function aiRejectChange(array $payload, $user): array {
			$id = (int)($payload["change_id"] ?? 0);
			$row = $id > 0 ? SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $id) : null;

			if (!$row) {

				return ["mode" => "error", "message" => "That pending change no longer exists (it may already have been resolved)."];
			}

			$mine = (int)$row["user"] === (int)(is_object($user) ? $user->id : ($user["id"] ?? 0));

			if (!$mine && !$this->isPublisherFor($user, $row)) {
				throw new AuthorizationException("Publisher access required to reject someone else's change");
			}

			SQL::delete("bigtree_pending_changes", $id);
			// Discard the draft's resource allocations (mirrors legacy reject-change.php).
			ResourceAllocationService::deallocateResources($row["table"], "p".$id);
			$this->fireChangeRejected($id, (string)$row["table"], "ai_assistant");

			return [
				"mode" => "rejected",
				"change_id" => $id,
				"title" => trim((string)$row["title"]) ?: ("change #{$id}"),
			];
		}

		/**
		 * Apply an approved publish from a stored payload. Re-checks publisher rights,
		 * then reuses the same applyPendingChange the REST approve path uses.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws AuthorizationException
		 */
		public function aiPublishChange(array $payload, $user): array {
			$id = (int)($payload["change_id"] ?? 0);
			$row = $id > 0 ? SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $id) : null;

			if (!$row) {

				return ["mode" => "error", "message" => "That pending change no longer exists (it may have already been published)."];
			}

			if (!$this->isPublisherFor($user, $row)) {
				throw new AuthorizationException("Publisher access required");
			}

			return $this->applyPendingChange($row, $user);
		}
	}
