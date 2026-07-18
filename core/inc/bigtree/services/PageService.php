<?php
	namespace BigTree\Services;

	use BigTree\Api\Entity;
	use BigTree\Api\Flag;
	use BigTree\Api\Json;
	use BigTree\Api\Sanitize;
	use BigTree\Api\Hooks;
	use BigTree\Api\Pagination;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTree\Services\AI\Tools\PageToolBackend;
	use BigTreeCMS;
	use BigTree;
	use SQL;
	use BigTreeJSONDB;
	use TextStatistics;

	/**
	 * Page CRUD, tree navigation, revisions, archive/publish, reorder, search.
	 *
	 * v1 scope: core CRUD + tree + revisions + reorder + archive + search.
	 * Out of scope for v1 (handled by legacy admin for now):
	 *  - Tag and open-graph wiring on create/update — SPA can call /tags/* separately
	 *  - Template publish hooks (extension surface; needs lifecycle middleware)
	 *  - Multi-site path collision dance
	 */
	class PageService implements PageToolBackend {
		// Template resource types the assistant is allowed to set when creating a page:
		// plain scalar values it can synthesize safely. Everything else (uploads,
		// matrices, relationships, callouts, routes) is omitted from the AI schema and
		// rejected if supplied — mirrors AutoModuleService's AI_SIMPLE_FIELD_TYPES.
		private const AI_SIMPLE_RESOURCE_TYPES = [
			"text", "textarea", "html", "htmleditor", "simple-editor", "code",
			"number", "currency", "phone", "email", "color",
			"date", "datetime", "time", "select", "radio", "checkbox", "list",
		];

		// Content columns snapshotted into bigtree_page_revisions (restoreRevision
		// update map + insertRevisionSnapshot share this list; per-map extras stay
		// explicit at each call site).
		private const REVISION_COLUMNS = [
			"title",
			"meta_description",
			"template",
			"external",
			"new_window",
			"resources",
		];

		public function list(Request $request) {
			$parent = $request->queryInt("parent");
			$include_archived = $request->queryBool("include_archived");

			$where = "parent = ?";
			$args = [$parent];

			if (!$include_archived) {
				$where .= " AND archived = ''";
			}

			$rows = SQL::fetchAll(...array_merge([
				"SELECT id, parent, nav_title, route, in_nav, archived, position, template, `external`, trunk, updated_at, publish_at, expire_at, EXISTS (SELECT 1 FROM bigtree_pages c WHERE c.parent = bigtree_pages.id) AS has_children FROM bigtree_pages WHERE " . $where . " ORDER BY position DESC, nav_title ASC",
			], $args));

			$me = $request->user;

			$items = array_filter(array_map(function ($r) use ($me) {
				$level = PermissionService::userPageLevel($me, (int)$r["id"]);

				if ($level === "n") {
					return null;
				}
				return [
					"id" => (int)$r["id"],
					"parent" => (int)$r["parent"],
					"nav_title" => Sanitize::decodeEntities($r["nav_title"]),
					"route" => $r["route"],
					"in_nav" => Flag::isOn($r["in_nav"]),
					"archived" => Flag::isOn($r["archived"]),
					"trunk" => Flag::isOn($r["trunk"]),
					"position" => (int)$r["position"],
					"template" => $r["template"],
					"external" => Sanitize::decodeEntities($r["external"]),
					"updated_at" => $r["updated_at"],
					"publish_at" => $r["publish_at"],
					"expire_at" => $r["expire_at"],
					"scheduled" => !empty($r["publish_at"]) && $r["publish_at"] > date("Y-m-d H:i:s"),
					"access" => $level,
					"has_pending_change" => false,
					"has_children" => (bool)$r["has_children"],
				];
			}, $rows));

			// Enrich with pending change state (used by the SPA to show "Changed" status)
			$visibleIds = array_column($items, "id");

			if ($visibleIds) {
				$placeholders = Sanitize::placeholders($visibleIds);
				$pendingRows = SQL::fetchAll(
					"SELECT DISTINCT item_id FROM bigtree_pending_changes 
					 WHERE `table` = 'bigtree_pages' AND item_id IN ($placeholders)",
					...$visibleIds
				);
				$hasPending = array_flip(array_map("intval", array_column($pendingRows, "item_id")));
				
				foreach ($items as &$it) {
					$it["has_pending_change"] = isset($hasPending[$it["id"]]);
				}
			}

			// Include "NEW" pending pages (drafts that only live in bigtree_pending_changes).
			// These do not yet have a row in bigtree_pages.
			$pendingNew = SQL::fetchAll(
				"SELECT * FROM bigtree_pending_changes 
				 WHERE pending_page_parent = ? AND `table` = 'bigtree_pages' AND type = 'NEW'",
				$parent
			);

			foreach ($pendingNew as $pc) {
				$changes = Json::decode($pc["changes"]);

				$isArchived = !empty($changes["archived"]);
				if (!$include_archived && $isArchived) {
					continue;
				}

				$inNav = !empty($changes["in_nav"]);

				// Permission for a brand-new pending page is based on the parent
				$level = PermissionService::userPageLevel($me, $parent);
				if ($level === "n") {
					continue;
				}

				$publishAt = $changes["publish_at"] ?? null;
				$isScheduled = $publishAt && $publishAt > date("Y-m-d H:i:s");

				$items[] = [
					"id" => (int)$pc["id"],
					"parent" => $parent,
					"nav_title" => Sanitize::decodeEntities(($changes["nav_title"] ?? "")),
					"route" => (string)($changes["route"] ?? ""),
					"in_nav" => $inNav,
					"archived" => $isArchived,
					"trunk" => !empty($changes["trunk"]),
					"position" => 999999, // New pending pages sort at the end for now
					"template" => (string)($changes["template"] ?? ""),
					"external" => Sanitize::decodeEntities(($changes["external"] ?? "")),
					"updated_at" => $pc["date"],
					"publish_at" => $publishAt,
					"scheduled" => $isScheduled,
					"access" => $level,
					"has_children" => false,
					"pending" => true,
					"pending_change_id" => (int)$pc["id"],
				];
			}

			return Response::ok(array_values($items));
		}

		public function get(Request $request) {
			$id = $request->id();
			$this->enforce($request->user, $id, "v");

			$page = Entity::findOrFail("bigtree_pages", $id, "Page");

			$out = $this->present($page, true);

			// Surface the caller's access level so the SPA can decide whether to
			// offer "Save & Publish" (publishers only) alongside "Save".
			$out["access"] = PermissionService::userPageLevel($request->user, $id);

			// The edit screen requests pending=true so it can keep editing the queued
			// draft (mirrors legacy getPendingPage) rather than the live content.
			if (!empty($request->query["pending"])) {
				$this->applyPendingOverlay($out, $id);
			}

			if (!empty($request->query["fields"]) && strpos((string)$request->query["fields"], "lineage") !== false) {
				$out["lineage"] = $this->lineage($id);
			}

			return Response::ok($out);
		}

		// GET /pages/{id}/seo-rating — score the page's live content with the same
		// algorithm as the legacy admin (PageService::getPageSEORating: title, meta
		// description, H1, content length/links/readability, freshness). Returns the
		// 0-100 score, the human recommendations, and the legacy gradient color so
		// the SPA can render the rating verbatim.
		public function seoRating(Request $request) {
			$id = $request->id();
			$this->enforce($request->user, $id, "v");

			$page = Entity::findOrFail("bigtree_pages", $id, "Page");

			$content = Json::decode($page["resources"]);
			$seo = PageService::getPageSEORating($page, $content);

			// getPageSEORating returns null when the template can't be resolved (e.g.
			// an external link or a removed template) — there's nothing to rate.
			if (is_null($seo)) {
				return Response::ok([
					"available" => false,
					"score" => null,
					"recommendations" => [],
					"color" => null,
				]);
			}

			return Response::ok([
				"available" => true,
				"score" => (int)$seo["score"],
				"recommendations" => array_values($seo["recommendations"]),
				"color" => $seo["color"],
			]);
		}

		public function create(Request $request) {
			$d = $request->body;
			$parent = (int)($d["parent"] ?? 0);
			$this->enforce($request->user, $parent, "e", "create child page");

			// Publishers (and global admins/devs) may write live by passing publish=true;
			// everyone else — and publishers who leave it off — creates a NEW pending draft.
			$access = PermissionService::userPageLevel($request->user, $parent);
			$can_publish = PermissionService::isPublisher($request->user, $access);

			if (empty($d["publish"]) || !$can_publish) {
				$pending_id = $this->writePendingPageChange($request->user, "NEW", $parent, $d);

				Hooks::fire("page.pending_created", [
					"parent" => $parent, "pending_change_id" => (int)$pending_id,
				]);

				return Response::created(["pending_change_id" => (int)$pending_id, "pending" => true], null);
			}

			return Response::created($this->performCreate($d, $request->user), null);
		}

		// — AI tool seam (PageToolBackend) —
		//
		// The create_page assistant tool drives page creation through the same
		// enforce/publisher/pending-change logic as create() above; these methods just
		// package it without a Request so the tool (validate) and the proposal approval
		// (execute) can reuse it. Permission is checked here, never in the model.

		/**
		 * Subtrees the user may create a page under, for a create_page needs_input
		 * prompt. Administrators/developers get the site root plus current top-level
		 * pages; an editor gets the pages they hold an explicit editor/publisher grant
		 * on (root is deliberately withheld from non-admins).
		 *
		 * @param object|array $user
		 * @return list<array{id:int,title:string,path:string}>
		 */
		public function aiWritableParents($user): array {
			$out = [];

			if (PermissionService::level($user) >= 1) {
				$out[] = ["id" => 0, "title" => "Top level (site root)", "path" => ""];

				$rows = SQL::fetchAll(
					"SELECT id, nav_title, title, path FROM bigtree_pages
						WHERE parent = 0 AND archived = '' ORDER BY position DESC, id ASC LIMIT 25"
				);

				foreach ($rows as $row) {
					$out[] = $this->writableParentRow($row);
				}

				return $out;
			}

			$permissions = Json::decode(is_object($user) ? ($user->permissions ?? []) : ($user["permissions"] ?? []));
			$page_perms = is_array($permissions["page"] ?? null) ? $permissions["page"] : [];

			foreach ($page_perms as $page_id => $rank) {
				if ((int)$page_id === 0 || !in_array($rank, ["e", "p"], true)) {

					continue;
				}

				$row = SQL::fetch(
					"SELECT id, nav_title, title, path FROM bigtree_pages WHERE id = ? AND archived = ''",
					(int)$page_id
				);

				if ($row) {
					$out[] = $this->writableParentRow($row);
				}
			}

			return $out;
		}

		/**
		 * @param array<string,mixed> $row
		 * @return array{id:int,title:string,path:string}
		 */
		private function writableParentRow(array $row): array {
			$title = trim((string)($row["nav_title"] ?? "")) ?: trim((string)($row["title"] ?? "")) ?: ("Page #" . (int)$row["id"]);

			return [
				"id" => (int)$row["id"],
				"title" => $title,
				"path" => (string)($row["path"] ?? ""),
			];
		}

		/**
		 * Validate a proposed page creation without writing anything.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidatePageCreate(array $args, $user): array {
			$nav_title = trim((string)($args["nav_title"] ?? ""));

			if ($nav_title === "") {

				return ["error" => "nav_title is required."];
			}

			$parent = (int)($args["parent"] ?? 0);

			if ($parent > 0 && !SQL::exists("bigtree_pages", $parent)) {

				return ["error" => "Parent page {$parent} does not exist."];
			}

			if (!PermissionService::userHasPageAccess($user, $parent, "e")) {

				return ["denied" => "You do not have permission to create a page here."];
			}

			$rank = PermissionService::userPageLevel($user, $parent);
			$template = trim((string)($args["template"] ?? ""));

			if ($template !== "") {
				if (!BigTreeJSONDB::exists("templates", $template)) {

					return ["error" => "Template \"{$template}\" does not exist."];
				}
			} else {
				// No template given. A blank template is only valid for an external
				// link, which the assistant can't create — so default to the same
				// template the admin's "Add page" screen would preselect: the first
				// flexible (non-routed) template, falling back to the first template.
				$template = $this->aiDefaultTemplate();
			}

			// Collect the page's content for the template's simple fields. Complex
			// fields (uploads, matrices, relationships) are omitted from the schema and
			// rejected if supplied — the assistant never fabricates a file reference.
			$schema = $this->aiTemplateResourceSchema($template);
			$provided = is_array($args["content"] ?? null) ? $args["content"] : [];
			$sifted = $this->aiSiftResourceContent($schema, $provided);

			if (isset($sifted["error"])) {

				return $sifted;
			}

			$resources = $sifted["data"];
			$missing = $this->aiMissingRequiredResources($schema, $resources);

			// A required template field with no content mirrors the "Add page" screen's
			// own required check — surface the settable fields so the model can fill
			// them in (or ask the user) and retry, rather than staging an empty page.
			if ($missing) {

				return ["error" => "The “{$template}” template needs content for these required fields before the page "
					. "can be created: " . implode(", ", $missing) . ". Settable fields: "
					. $this->aiDescribeResourceSchema($schema)];
			}

			$can_publish = PermissionService::isPublisher($user, $rank);
			$blocked = $this->aiRequiredUnsettableResources($template);

			// This template requires content the assistant can't author. A publisher's
			// approval would put a visibly broken page straight onto the live site, so
			// refuse; a non-publisher's write only ever lands in the pending queue,
			// where a human completes it before it goes live — allow that, but say
			// plainly what will still be missing.
			if ($blocked && $can_publish) {

				return ["error" => "The “{$template}” template requires content the assistant can't provide: "
					. implode(", ", $blocked) . ". Create this page in the admin UI instead."];
			}

			$title = trim((string)($args["title"] ?? "")) ?: $nav_title;
			$route = $this->uniqueRoute($parent, BigTreeCMS::urlify($nav_title));
			$parent_path = $parent ? (string)SQL::fetchSingle("SELECT path FROM bigtree_pages WHERE id = ?", $parent) : "";
			$path = ($parent_path ? $parent_path . "/" : "") . $route;

			$parent_title = $parent
				? (trim((string)SQL::fetchSingle("SELECT nav_title FROM bigtree_pages WHERE id = ?", $parent)) ?: "page #{$parent}")
				: "the site root";

			$in_nav = array_key_exists("in_nav", $args) ? (bool)$args["in_nav"] : true;

			// SEO and scheduling fields already flow through performCreate and are
			// settable on update — omitting them here taught the model to create a
			// page and immediately edit it, spending two approvals on one intent.
			$meta_description = trim((string)($args["meta_description"] ?? ""));
			$meta_keywords = trim((string)($args["meta_keywords"] ?? ""));
			$seo_invisible = array_key_exists("seo_invisible", $args) ? (bool)$args["seo_invisible"] : false;
			$schedule = $this->aiPageSchedule($args);

			if (isset($schedule["error"])) {

				return $schedule;
			}

			$payload = [
				"parent" => $parent,
				"nav_title" => $nav_title,
				"title" => $title,
				"route" => $route,
				"template" => $template,
				"in_nav" => $in_nav,
				"meta_description" => $meta_description,
				"meta_keywords" => $meta_keywords,
				"seo_invisible" => $seo_invisible,
				"publish_at" => $schedule["publish_at"],
				"expire_at" => $schedule["expire_at"],
				"resources" => $resources,
			];

			$preview = [
				"nav_title" => $nav_title,
				"title" => $title,
				"template" => $template,
				"parent_id" => $parent,
				"parent_title" => $parent_title,
				"route" => $route,
				"path" => "/" . $path,
				"in_nav" => $in_nav,
				"fields" => $this->aiPreviewResourceContent($schema, $resources),
				"mode" => $can_publish ? "published" : "pending",
			];

			if ($meta_description !== "") {
				$preview["meta_description"] = $meta_description;
			}

			if ($seo_invisible) {
				$preview["seo_invisible"] = true;
			}

			if ($schedule["publish_at"] !== null) {
				$preview["publish_at"] = $schedule["publish_at"];
			}

			if ($schedule["expire_at"] !== null) {
				$preview["expire_at"] = $schedule["expire_at"];
			}

			$mode_note = $can_publish
				? " It will be published live once you approve."
				: " It will be queued as a pending change for a publisher to review.";

			if ($blocked) {
				$preview["incomplete_required"] = $blocked;
				$mode_note .= " Note that " . implode(", ", $blocked)
					. " " . (count($blocked) === 1 ? "is required but cannot" : "are required but cannot")
					. " be set by the assistant — a publisher must fill "
					. (count($blocked) === 1 ? "it" : "them") . " in before this page can go live.";
			}

			$summary = "Create page “{$nav_title}” under {$parent_title}." . $mode_note;

			return [
				"ok" => true,
				"summary" => $summary,
				"preview" => $preview,
				"payload" => $payload,
			];
		}

		/**
		 * Normalize the optional publish/expire window on a page proposal.
		 *
		 * Both are stored as datetimes; the model tends to emit whatever the user
		 * said ("next Tuesday"), so anything unparseable is rejected rather than
		 * silently stored as a zero date that would hide the page forever.
		 *
		 * @param array<string,mixed> $args
		 * @return array<string,mixed> ["publish_at" => ?string, "expire_at" => ?string] or ["error" => string]
		 */
		private function aiPageSchedule(array $args): array {
			$out = ["publish_at" => null, "expire_at" => null];

			foreach (["publish_at", "expire_at"] as $field) {
				if (!array_key_exists($field, $args)) {
					continue;
				}

				$raw = trim((string)$args[$field]);

				if ($raw === "") {
					continue;
				}

				$stamp = strtotime($raw);

				if ($stamp === false) {

					return ["error" => "\"{$raw}\" isn't a date I can store for {$field}. Use an explicit date like "
						. "\"2026-08-01\" or \"2026-08-01 09:00:00\"."];
				}

				$out[$field] = date("Y-m-d H:i:s", $stamp);
			}

			if ($out["publish_at"] !== null && $out["expire_at"] !== null && $out["expire_at"] <= $out["publish_at"]) {

				return ["error" => "expire_at ({$out["expire_at"]}) must be after publish_at ({$out["publish_at"]})."];
			}

			return $out;
		}

		/**
		 * The template the "Add page" UI preselects: the first flexible (non-routed)
		 * template, falling back to the first template overall. Templates are read in
		 * the same position-DESC order the admin list uses. Returns "" only when no
		 * templates exist at all.
		 *
		 * @return string
		 */
		private function aiDefaultTemplate(): string {
			$templates = BigTreeJSONDB::getAll("templates", "position", "DESC");

			if (!$templates) {

				return "";
			}

			foreach ($templates as $template) {
				if (empty($template["routed"])) {

					return (string)$template["id"];
				}
			}

			return (string)$templates[0]["id"];
		}

		/**
		 * The AI-settable content schema for a template: its simple scalar resources,
		 * keyed by resource id. Complex resources are dropped (never offered to the
		 * model, never accepted). `required` is read from the legacy `settings.validation`
		 * rule string — the same source the "Add page" screen validates against.
		 *
		 * @return array<string,array<string,mixed>>
		 */
		private function aiTemplateResourceSchema(string $template): array {
			$row = $template !== "" ? BigTreeJSONDB::get("templates", $template) : null;

			if (!$row) {

				return [];
			}

			$schema = [];

			foreach ((array)($row["resources"] ?? []) as $resource) {
				$id = (string)($resource["id"] ?? "");
				$type = (string)($resource["type"] ?? "text");

				if ($id === "" || !in_array($type, self::AI_SIMPLE_RESOURCE_TYPES, true)) {

					continue;
				}

				$settings = is_array($resource["settings"] ?? null) ? $resource["settings"] : [];
				$rules = is_string($settings["validation"] ?? null)
					? preg_split("/\s+/", trim($settings["validation"]), -1, PREG_SPLIT_NO_EMPTY)
					: [];

				$schema[$id] = [
					"id" => $id,
					"type" => $type,
					"title" => (string)($resource["title"] ?? $id),
					"required" => in_array("required", $rules ?: [], true),
				];
			}

			return $schema;
		}

		/**
		 * Required template resources whose type the assistant cannot author.
		 * aiTemplateResourceSchema drops complex resources before the required check
		 * runs, so without this scan an AI-created page passes validation while being
		 * exactly the page the "Add page" screen's own required check would refuse.
		 *
		 * Returned as "Title (id, type)" strings for a human-readable error.
		 *
		 * $existing is the content the page already carries (empty on create). A
		 * required complex resource that already has a value is not a gap — this
		 * matters on a template switch, where the outgoing template may well have
		 * populated the same resource id.
		 *
		 * @param array<string,mixed> $existing
		 * @return list<string>
		 */
		private function aiRequiredUnsettableResources(string $template, array $existing = []): array {
			$row = $template !== "" ? BigTreeJSONDB::get("templates", $template) : null;

			if (!$row) {

				return [];
			}

			$blocked = [];

			foreach ((array)($row["resources"] ?? []) as $resource) {
				$id = (string)($resource["id"] ?? "");
				$type = (string)($resource["type"] ?? "text");

				if ($id === "" || in_array($type, self::AI_SIMPLE_RESOURCE_TYPES, true)) {
					continue;
				}

				$settings = is_array($resource["settings"] ?? null) ? $resource["settings"] : [];
				$rules = is_string($settings["validation"] ?? null)
					? preg_split("/\s+/", trim($settings["validation"]), -1, PREG_SPLIT_NO_EMPTY)
					: [];

				if (!in_array("required", $rules ?: [], true)) {
					continue;
				}

				$value = $existing[$id] ?? "";

				if (is_array($value) ? (bool)$value : trim((string)$value) !== "") {
					continue;
				}

				$blocked[] = (string)($resource["title"] ?? $id) . " ({$id}, {$type})";
			}

			return $blocked;
		}

		/**
		 * The required content a page would be missing if it were switched to
		 * $template, judged against the resources it already has stored. Covers both
		 * halves of the problem: required simple fields the old template never
		 * populated, and required complex fields the assistant could not fill even
		 * if asked. Empty means the switch is safe.
		 *
		 * @param array<string,mixed> $page the existing page row
		 * @return list<string>
		 */
		private function aiTemplateSwitchGaps(string $template, array $page): array {
			$existing = Json::decode($page["resources"] ?? "");
			$existing = is_array($existing) ? $existing : [];
			$schema = $this->aiTemplateResourceSchema($template);
			$unmet = [];

			foreach ($schema as $id => $field) {
				if (empty($field["required"])) {
					continue;
				}

				$value = $existing[$id] ?? "";

				if (is_array($value) ? !$value : trim((string)$value) === "") {
					$unmet[] = (string)$field["title"] . " ({$id})";
				}
			}

			foreach ($this->aiRequiredUnsettableResources($template, $existing) as $blocked) {
				$unmet[] = $blocked;
			}

			return $unmet;
		}

		/**
		 * Keep only the provided content that maps to a settable simple resource; reject
		 * a value that is a list/object where a scalar is expected.
		 *
		 * @param array<string,array<string,mixed>> $schema
		 * @param array<string,mixed> $provided
		 * @return array<string,mixed> ["data" => array] or ["error" => string]
		 */
		private function aiSiftResourceContent(array $schema, array $provided): array {
			$data = [];

			foreach ($provided as $id => $value) {
				$id = (string)$id;

				// Unknown ids are ignored (they'd never persist); only real complex
				// fields are surfaced as an error below.
				if (!isset($schema[$id])) {

					continue;
				}

				if (is_array($value)) {

					return ["error" => "Field \"{$id}\" expects a simple value, not a list or object."];
				}

				if ($schema[$id]["type"] === "checkbox") {
					$data[$id] = !empty($value) && $value !== "false" ? "on" : "";
				} else {
					$data[$id] = is_bool($value) ? ($value ? "1" : "0") : (string)$value;
				}
			}

			return ["data" => $data];
		}

		/**
		 * Required schema resources absent (or empty) in the sifted content. Returns the
		 * field titles for a human-readable error.
		 *
		 * @param array<string,array<string,mixed>> $schema
		 * @param array<string,mixed> $data
		 * @return list<string>
		 */
		private function aiMissingRequiredResources(array $schema, array $data): array {
			$missing = [];

			foreach ($schema as $id => $field) {
				if (!empty($field["required"]) && (!array_key_exists($id, $data) || $data[$id] === "")) {
					$missing[] = (string)$field["title"];
				}
			}

			return $missing;
		}

		/**
		 * A one-line human description of the settable content fields for a
		 * schema-discovery error message.
		 *
		 * @param array<string,array<string,mixed>> $schema
		 */
		private function aiDescribeResourceSchema(array $schema): string {
			if (!$schema) {

				return "(this template has no content fields the assistant can set)";
			}

			$parts = [];

			foreach ($schema as $id => $field) {
				$parts[] = $id . " (" . $field["type"] . ($field["required"] ? ", required" : "") . ")";
			}

			return implode(", ", $parts);
		}

		/**
		 * Field-level preview rows for a proposal card: the content being set on each
		 * template resource (rendered by ProposalCard's generic `fields` list).
		 *
		 * @param array<string,array<string,mixed>> $schema
		 * @param array<string,mixed> $data
		 * @return list<array<string,mixed>>
		 */
		private function aiPreviewResourceContent(array $schema, array $data): array {
			$out = [];

			foreach ($data as $id => $value) {
				$string = is_scalar($value) ? (string)$value : (string)json_encode($value);

				if (mb_strlen($string) > 200) {
					$string = mb_substr($string, 0, 199) . "…";
				}

				$out[] = [
					"column" => $id,
					"title" => (string)($schema[$id]["title"] ?? $id),
					"to" => $string,
				];
			}

			return $out;
		}

		/**
		 * Execute an approved page creation from a stored, validated payload. Re-checks
		 * permission at approval time and honors the publisher/editor split.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiCreatePage(array $payload, $user): array {
			$parent = (int)($payload["parent"] ?? 0);

			if (!PermissionService::userHasPageAccess($user, $parent, "e")) {
				throw new AuthorizationException("Insufficient page permission to create child page (e required)");
			}

			$rank = PermissionService::userPageLevel($user, $parent);
			$can_publish = PermissionService::isPublisher($user, $rank);
			$nav_title = (string)($payload["nav_title"] ?? "");

			if (!$can_publish) {
				$pending_id = $this->writePendingPageChange($user, "NEW", $parent, $payload);

				Hooks::fire("page.pending_created", [
					"parent" => $parent, "pending_change_id" => (int)$pending_id, "via" => "ai_assistant",
				]);

				return [
					"mode" => "pending",
					"title" => $nav_title,
					"pending_change_id" => (int)$pending_id,
				];
			}

			$page = $this->performCreate($payload, $user);

			return [
				"mode" => "published",
				"title" => $nav_title,
				"page_id" => (int)($page["id"] ?? 0),
				"path" => "/" . (string)($page["path"] ?? ""),
			];
		}

		/**
		 * The page tree around $parent for the get_page_tree read tool: the parent's
		 * viewable children plus whether this user may create/edit under it. Children
		 * the user cannot even view are omitted (they should never surface to the model).
		 *
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiPageTree(int $parent, $user): array {
			if ($parent > 0 && !SQL::exists("bigtree_pages", $parent)) {

				return ["error" => "Page {$parent} does not exist."];
			}

			if (!PermissionService::userHasPageAccess($user, $parent, "v")) {

				return ["error" => "You do not have access to view this part of the tree."];
			}

			$parent_row = $parent > 0
				? SQL::fetch("SELECT id, nav_title, title, path FROM bigtree_pages WHERE id = ?", $parent)
				: ["id" => 0, "nav_title" => "Top level (site root)", "title" => "", "path" => ""];

			$rows = SQL::fetchAll(
				"SELECT id, nav_title, title, path, in_nav, archived, template FROM bigtree_pages
					WHERE parent = ? ORDER BY position DESC, id ASC LIMIT 200",
				$parent
			);

			$children = [];

			foreach ($rows as $row) {
				if (!PermissionService::userHasPageAccess($user, (int)$row["id"], "v")) {

					continue;
				}

				$children[] = [
					"id" => (int)$row["id"],
					"nav_title" => Sanitize::decodeEntities($row["nav_title"]) ?: Sanitize::decodeEntities($row["title"]),
					"path" => "/" . (string)$row["path"],
					"in_nav" => Flag::isOn($row["in_nav"]),
					"archived" => Flag::isOn($row["archived"]),
					"template" => (string)$row["template"],
					"has_children" => (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_pages WHERE parent = ?", (int)$row["id"]) > 0,
					"can_edit" => PermissionService::userHasPageAccess($user, (int)$row["id"], "e"),
				];
			}

			$can_create = PermissionService::userHasPageAccess($user, $parent, "e");

			return [
				"parent" => [
					"id" => (int)$parent_row["id"],
					"nav_title" => $parent > 0
						? (Sanitize::decodeEntities($parent_row["nav_title"]) ?: Sanitize::decodeEntities($parent_row["title"]))
						: "Top level (site root)",
					"path" => $parent > 0 ? "/" . (string)$parent_row["path"] : "",
				],
				"children" => $children,
				"can_create_here" => $can_create,
				"can_edit_here" => $parent > 0 && $can_create,
			];
		}

		/**
		 * Validate a proposed page edit without writing anything: existence, edit
		 * access, and (where supplied) template existence. Builds a payload of only the
		 * fields the model actually changed so approval replays a minimal diff.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidatePageUpdate(array $args, $user): array {
			$id = (int)($args["id"] ?? 0);

			if ($id < 1) {

				return ["error" => "A page id is required to edit a page."];
			}

			$page = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $id);

			if (!$page) {

				return ["error" => "Page {$id} does not exist."];
			}

			if (!PermissionService::userHasPageAccess($user, $id, "e")) {

				return ["denied" => "You do not have permission to edit this page."];
			}

			// Only the plain content fields the assistant is allowed to touch. Resource
			// (template field) editing is deliberately out of scope for the assistant.
			$editable = ["nav_title", "title", "meta_description", "meta_keywords", "in_nav", "seo_invisible", "template", "route"];
			$changes = [];
			$diff = [];
			$title_for_error = trim((string)$page["nav_title"]) ?: trim((string)$page["title"]) ?: "page #{$id}";

			foreach ($editable as $field) {
				if (!array_key_exists($field, $args)) {

					continue;
				}

				if ($field === "template") {
					$template = trim((string)$args["template"]);

					if ($template !== "" && !BigTreeJSONDB::exists("templates", $template)) {

						return ["error" => "Template \"{$template}\" does not exist."];
					}

					// Switching templates keeps the old template's stored resources and
					// leaves the new template's own required fields empty — the page
					// renders broken until a human edits it. Refuse unless the page's
					// existing content already satisfies the incoming template.
					if ($template !== "" && $template !== (string)$page["template"]) {
						$unmet = $this->aiTemplateSwitchGaps($template, $page);

						if ($unmet) {

							return ["error" => "Switching “{$title_for_error}” to the “{$template}” template would leave its "
								. "required content empty: " . implode(", ", $unmet) . ". Use update_page_content to supply "
								. "the new template's content in the same edit, or make this change in the admin UI."];
						}
					}
				}

				if (in_array($field, ["in_nav", "seo_invisible"], true)) {
					$new = (bool)$args[$field];
					$old = Flag::isOn($page[$field]);
				} else {
					$new = trim((string)$args[$field]);
					$old = Sanitize::decodeEntities((string)$page[$field]);
				}

				if ($new === $old) {

					continue;
				}

				$changes[$field] = $new;
				$diff[$field] = ["from" => $old, "to" => $new];
			}

			if (!$changes) {

				return ["error" => "No changes were supplied — nothing to update."];
			}

			$rank = PermissionService::userPageLevel($user, $id);
			$can_publish = PermissionService::isPublisher($user, $rank);
			$title = trim((string)$page["nav_title"]) ?: trim((string)$page["title"]) ?: "page #{$id}";

			$mode_note = $can_publish
				? " It will be published live once you approve."
				: " It will be queued as a pending change for a publisher to review.";
			$summary = "Update page “{$title}”." . $mode_note;

			return [
				"ok" => true,
				"summary" => $summary,
				"preview" => [
					"page_id" => $id,
					"page_title" => $title,
					"changes" => $diff,
					"mode" => $can_publish ? "published" : "pending",
				],
				"payload" => [
					"id" => $id,
					"changes" => $changes,
				],
			];
		}

		/**
		 * Validate an edit to a page's template content (its resources).
		 *
		 * update_page deliberately excludes resources, which left the assistant able to
		 * author a page's body at creation but never to fix a typo in it afterwards —
		 * the single biggest editor-facing gap. Content is merged onto what the page
		 * already has, so a partial edit doesn't wipe untouched fields.
		 *
		 * Optionally accepts a `template` switch in the same proposal, which is what
		 * makes changing template safe: the new template's required content can be
		 * supplied in the very same edit.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidatePageContentUpdate(array $args, $user): array {
			$id = (int)($args["id"] ?? 0);

			if ($id < 1) {

				return ["error" => "A page id is required to edit a page's content."];
			}

			$page = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $id);

			if (!$page) {

				return ["error" => "Page {$id} does not exist."];
			}

			if (!PermissionService::userHasPageAccess($user, $id, "e")) {

				return ["denied" => "You do not have permission to edit this page."];
			}

			$title = trim((string)$page["nav_title"]) ?: trim((string)$page["title"]) ?: "page #{$id}";
			$current_template = (string)$page["template"];
			$template = array_key_exists("template", $args) ? trim((string)$args["template"]) : $current_template;

			if ($template === "") {
				$template = $current_template;
			}

			if ($template !== $current_template && !BigTreeJSONDB::exists("templates", $template)) {

				return ["error" => "Template \"{$template}\" does not exist."];
			}

			$schema = $this->aiTemplateResourceSchema($template);
			$provided = is_array($args["content"] ?? null) ? $args["content"] : [];

			if (!$provided) {

				return ["error" => "Provide a \"content\" object with the fields to change. Settable fields on the "
					. "“{$template}” template: " . $this->aiDescribeResourceSchema($schema)];
			}

			$sifted = $this->aiSiftResourceContent($schema, $provided);

			if (isset($sifted["error"])) {

				return $sifted;
			}

			$changed = $sifted["data"];

			if (!$changed) {

				return ["error" => "None of the supplied fields can be set by the assistant on the “{$template}” "
					. "template. Settable fields: " . $this->aiDescribeResourceSchema($schema)];
			}

			$existing = Json::decode($page["resources"] ?? "");
			$existing = is_array($existing) ? $existing : [];

			// Merge, so an edit to one field doesn't blank every other field the page
			// carries — including the complex ones the assistant can't even see.
			$resources = array_merge($existing, $changed);
			$missing = $this->aiMissingRequiredResources($schema, $resources);

			if ($missing) {

				return ["error" => "The “{$template}” template needs content for these required fields: "
					. implode(", ", $missing) . "."];
			}

			$rank = PermissionService::userPageLevel($user, $id);
			$can_publish = PermissionService::isPublisher($user, $rank);

			// A template switch can still strand required content the assistant can't
			// author — allow it only when the merged result actually satisfies it.
			if ($template !== $current_template) {
				$blocked = $this->aiRequiredUnsettableResources($template, $resources);

				if ($blocked && $can_publish) {

					return ["error" => "Switching “{$title}” to the “{$template}” template would leave required "
						. "content empty: " . implode(", ", $blocked) . ". Make this change in the admin UI."];
				}
			}

			$diff = [];

			foreach ($changed as $field_id => $value) {
				$diff[] = [
					"column" => $field_id,
					"title" => (string)($schema[$field_id]["title"] ?? $field_id),
					"from" => $this->aiPreviewScalarValue($existing[$field_id] ?? ""),
					"to" => $this->aiPreviewScalarValue($value),
				];
			}

			$mode_note = $can_publish
				? " It will be published live once you approve."
				: " It will be queued as a pending change for a publisher to review.";
			$switch_note = $template !== $current_template
				? " The page will also switch from the “{$current_template}” template to “{$template}”."
				: "";

			$payload = [
				"id" => $id,
				"resources" => $resources,
			];

			if ($template !== $current_template) {
				$payload["template"] = $template;
			}

			return [
				"ok" => true,
				"summary" => "Update the content of “{$title}” (" . count($changed) . " field"
					. (count($changed) === 1 ? "" : "s") . ")." . $switch_note . $mode_note,
				"preview" => [
					"action" => "update_page_content",
					"page_id" => $id,
					"page_title" => $title,
					"template" => $template,
					"template_changed" => $template !== $current_template,
					"fields" => $diff,
					"mode" => $can_publish ? "published" : "pending",
				],
				"payload" => $payload,
			];
		}

		/**
		 * Execute an approved page-content edit. Re-checks edit access, then reuses the
		 * same performUpdate / pending-change split every other page write uses.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiUpdatePageContent(array $payload, $user): array {
			$id = (int)($payload["id"] ?? 0);

			if (!PermissionService::userHasPageAccess($user, $id, "e")) {
				throw new AuthorizationException("Insufficient page permission to edit page (e required)");
			}

			$page = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $id);

			if (!$page) {

				return ["mode" => "error", "message" => "Page no longer exists."];
			}

			$changes = ["resources" => is_array($payload["resources"] ?? null) ? $payload["resources"] : []];

			if (!empty($payload["template"])) {
				$changes["template"] = (string)$payload["template"];
			}

			$rank = PermissionService::userPageLevel($user, $id);
			$can_publish = PermissionService::isPublisher($user, $rank);
			$title = trim((string)$page["nav_title"]) ?: ("page #{$id}");

			if (!$can_publish) {
				$pending_id = $this->writePendingPageChange($user, "EDIT", $id, $changes);

				Hooks::fire("page.pending_updated", [
					"page" => $id, "pending_change_id" => (int)$pending_id, "via" => "ai_assistant",
				]);

				return ["mode" => "pending", "page_id" => $id, "title" => $title, "pending_change_id" => (int)$pending_id];
			}

			$this->performUpdate($id, $page, $changes, $user);

			return ["mode" => "published", "page_id" => $id, "title" => $title];
		}

		/**
		 * A short, length-capped rendering of a resource value for a proposal diff.
		 *
		 * @param mixed $value
		 */
		private function aiPreviewScalarValue($value): string {
			$string = is_scalar($value) || $value === null ? (string)$value : (string)json_encode($value);

			if (mb_strlen($string) > 200) {
				$string = mb_substr($string, 0, 199) . "…";
			}

			return $string;
		}

		/**
		 * Execute an approved page edit from a stored, validated payload. Re-checks edit
		 * access, then publishes live for a publisher or queues an EDIT pending change.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiUpdatePage(array $payload, $user): array {
			$id = (int)($payload["id"] ?? 0);
			$changes = is_array($payload["changes"] ?? null) ? $payload["changes"] : [];

			if (!PermissionService::userHasPageAccess($user, $id, "e")) {
				throw new AuthorizationException("Insufficient page permission to edit page (e required)");
			}

			$page = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $id);

			if (!$page) {

				return ["mode" => "error", "message" => "Page no longer exists."];
			}

			$rank = PermissionService::userPageLevel($user, $id);
			$can_publish = PermissionService::isPublisher($user, $rank);
			$title = trim((string)$page["nav_title"]) ?: ("page #{$id}");

			if (!$can_publish) {
				$pending_id = $this->writePendingPageChange($user, "EDIT", $id, $changes);

				Hooks::fire("page.pending_updated", [
					"id" => $id, "pending_change_id" => (int)$pending_id, "via" => "ai_assistant",
				]);

				return [
					"mode" => "pending",
					"title" => $title,
					"pending_change_id" => (int)$pending_id,
				];
			}

			$this->performUpdate($id, $page, $changes, $user);

			return [
				"mode" => "published",
				"title" => $title,
				"page_id" => $id,
			];
		}

		/**
		 * Validate a proposed page archive: existence and publisher access (archiving is
		 * a publish-level action, so an editor cannot stage it even as a pending change).
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidatePageArchive(array $args, $user): array {
			$id = (int)($args["id"] ?? 0);

			if ($id < 1) {

				return ["error" => "A page id is required to archive a page."];
			}

			$page = SQL::fetch("SELECT id, nav_title, title, path, archived FROM bigtree_pages WHERE id = ?", $id);

			if (!$page) {

				return ["error" => "Page {$id} does not exist."];
			}

			if (Flag::isOn($page["archived"])) {

				return ["error" => "That page is already archived."];
			}

			if (!PermissionService::userHasPageAccess($user, $id, "p")) {

				return ["denied" => "Archiving a page requires publisher access, which you do not have on this page."];
			}

			$title = trim((string)$page["nav_title"]) ?: trim((string)$page["title"]) ?: "page #{$id}";

			return [
				"ok" => true,
				"summary" => "Archive page “{$title}” (and any pages beneath it). It will be hidden from the site until unarchived.",
				"preview" => [
					"page_id" => $id,
					"page_title" => $title,
					"path" => "/" . (string)$page["path"],
					"action" => "archive",
				],
				"payload" => [
					"id" => $id,
				],
			];
		}

		/**
		 * Execute an approved page archive from a stored, validated payload. Re-checks
		 * publisher access and archives the page and its descendants live.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiArchivePage(array $payload, $user): array {
			$id = (int)($payload["id"] ?? 0);

			if (!PermissionService::userHasPageAccess($user, $id, "p")) {
				throw new AuthorizationException("Insufficient page permission to archive page (p required)");
			}

			$page = SQL::fetch("SELECT id, nav_title FROM bigtree_pages WHERE id = ?", $id);

			if (!$page) {

				return ["mode" => "error", "message" => "Page no longer exists."];
			}

			SQL::update("bigtree_pages", $id, ["archived" => "on", "updated_at" => "NOW()"]);
			$this->setArchivedInherited($id, "on");
			EmbeddingService::deletePage($id);

			Hooks::fire("page.archived", ["id" => $id, "via" => "ai_assistant"]);

			return [
				"mode" => "archived",
				"title" => trim((string)$page["nav_title"]) ?: ("page #{$id}"),
				"page_id" => $id,
			];
		}

		/**
		 * Validate un-archiving a page. archive_page shipped without its inverse, so a
		 * page the assistant archived could only be restored in the admin UI.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidatePageUnarchive(array $args, $user): array {
			$id = (int)($args["id"] ?? 0);

			if ($id < 1) {

				return ["error" => "A page id is required to unarchive a page."];
			}

			$page = SQL::fetch("SELECT id, nav_title, title, path, archived, archived_inherited FROM bigtree_pages WHERE id = ?", $id);

			if (!$page) {

				return ["error" => "Page {$id} does not exist."];
			}

			if (!Flag::isOn($page["archived"]) && !Flag::isOn($page["archived_inherited"])) {

				return ["error" => "That page is not archived."];
			}

			// A page archived only because an ancestor was archived has no state of its
			// own to clear — restoring it means restoring the ancestor.
			if (!Flag::isOn($page["archived"]) && Flag::isOn($page["archived_inherited"])) {

				return ["error" => "That page is archived because a page above it is archived. Unarchive the parent "
					. "page instead."];
			}

			if (!PermissionService::userHasPageAccess($user, $id, "p")) {

				return ["denied" => "Unarchiving a page requires publisher access, which you do not have on this page."];
			}

			$title = trim((string)$page["nav_title"]) ?: trim((string)$page["title"]) ?: "page #{$id}";

			return [
				"ok" => true,
				"summary" => "Restore page “{$title}” (and any pages beneath it that were archived along with it). "
					. "It will be visible on the site again.",
				"preview" => [
					"page_id" => $id,
					"page_title" => $title,
					"path" => "/" . (string)$page["path"],
					"action" => "unarchive",
				],
				"payload" => ["id" => $id],
			];
		}

		/**
		 * Execute an approved unarchive. Re-checks publisher access.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiUnarchivePage(array $payload, $user): array {
			$id = (int)($payload["id"] ?? 0);

			if (!PermissionService::userHasPageAccess($user, $id, "p")) {
				throw new AuthorizationException("Insufficient page permission to unarchive page (p required)");
			}

			$page = SQL::fetch("SELECT id, nav_title FROM bigtree_pages WHERE id = ?", $id);

			if (!$page) {

				return ["mode" => "error", "message" => "Page no longer exists."];
			}

			SQL::update("bigtree_pages", $id, ["archived" => "", "updated_at" => "NOW()"]);
			$this->setArchivedInherited($id, "");

			Hooks::fire("page.unarchived", ["id" => $id, "via" => "ai_assistant"]);

			return [
				"mode" => "unarchived",
				"title" => trim((string)$page["nav_title"]) ?: ("page #{$id}"),
				"page_id" => $id,
			];
		}

		/**
		 * Validate moving a page to a new parent.
		 *
		 * Moving rewrites the page's path and every descendant's path, so it needs
		 * publisher access on the page *and* edit access at the destination, and must
		 * refuse a move into the page's own subtree (which would orphan the branch).
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidatePageMove(array $args, $user): array {
			$id = (int)($args["id"] ?? 0);

			if ($id < 1) {

				return ["error" => "A page id is required to move a page."];
			}

			if (!array_key_exists("parent", $args)) {

				return ["error" => "A parent is required — the id of the page to move this page under, or 0 for the "
					. "site root."];
			}

			$parent = (int)$args["parent"];
			$page = SQL::fetch("SELECT id, nav_title, title, path, route, parent FROM bigtree_pages WHERE id = ?", $id);

			if (!$page) {

				return ["error" => "Page {$id} does not exist."];
			}

			if ($parent > 0 && !SQL::exists("bigtree_pages", $parent)) {

				return ["error" => "Parent page {$parent} does not exist."];
			}

			if ($parent === $id) {

				return ["error" => "A page can't be moved under itself."];
			}

			if ((int)$page["parent"] === $parent) {

				return ["error" => "That page is already under that parent."];
			}

			// Moving a page into its own subtree would detach the whole branch from the
			// tree — the path rewrite would never terminate at the root.
			if ($parent > 0 && $this->isDescendantOf($parent, $id)) {

				return ["error" => "A page can't be moved underneath one of its own child pages."];
			}

			if (!PermissionService::userHasPageAccess($user, $id, "p")) {

				return ["denied" => "Moving a page requires publisher access, which you do not have on this page."];
			}

			if (!PermissionService::userHasPageAccess($user, $parent, "e")) {

				return ["denied" => "You do not have permission to add pages in that location."];
			}

			$title = trim((string)$page["nav_title"]) ?: trim((string)$page["title"]) ?: "page #{$id}";
			$route = $this->uniqueRoute($parent, (string)$page["route"], $id);
			$parent_path = $parent ? (string)SQL::fetchSingle("SELECT path FROM bigtree_pages WHERE id = ?", $parent) : "";
			$new_path = ($parent_path ? $parent_path . "/" : "") . $route;
			$parent_title = $parent
				? (trim((string)SQL::fetchSingle("SELECT nav_title FROM bigtree_pages WHERE id = ?", $parent)) ?: "page #{$parent}")
				: "the site root";
			$descendants = (int)SQL::fetchSingle(
				"SELECT COUNT(*) FROM bigtree_pages WHERE path LIKE ?", $page["path"] . "/%"
			);

			$note = $descendants > 0
				? " {$descendants} page" . ($descendants === 1 ? "" : "s") . " beneath it will move too, and every "
					. "affected URL will change."
				: " Its URL will change.";

			return [
				"ok" => true,
				"summary" => "Move page “{$title}” under {$parent_title}." . $note,
				"preview" => [
					"action" => "move_page",
					"page_id" => $id,
					"page_title" => $title,
					"from_path" => "/" . (string)$page["path"],
					"to_path" => "/" . $new_path,
					"new_parent_id" => $parent,
					"new_parent_title" => $parent_title,
					"descendants_affected" => $descendants,
				],
				"payload" => ["id" => $id, "parent" => $parent, "route" => $route],
			];
		}

		/**
		 * Execute an approved move: re-parent the page and rewrite its subtree's paths.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiMovePage(array $payload, $user): array {
			$id = (int)($payload["id"] ?? 0);
			$parent = (int)($payload["parent"] ?? 0);

			if (!PermissionService::userHasPageAccess($user, $id, "p")) {
				throw new AuthorizationException("Insufficient page permission to move page (p required)");
			}

			if (!PermissionService::userHasPageAccess($user, $parent, "e")) {
				throw new AuthorizationException("Insufficient page permission at the destination (e required)");
			}

			$page = SQL::fetch("SELECT id, nav_title, path, route, parent, trunk FROM bigtree_pages WHERE id = ?", $id);

			if (!$page) {

				return ["mode" => "error", "message" => "Page no longer exists."];
			}

			// Re-check the structural guards at approval — the tree may have changed
			// since the proposal was staged.
			if ($parent === $id || ($parent > 0 && !SQL::exists("bigtree_pages", $parent))) {

				return ["mode" => "error", "message" => "That destination is no longer valid."];
			}

			if ($parent > 0 && $this->isDescendantOf($parent, $id)) {

				return ["mode" => "error", "message" => "That destination is now inside the page being moved."];
			}

			// Re-derive the route against the destination in case it was taken since.
			$route = $this->uniqueRoute($parent, (string)($payload["route"] ?? $page["route"]), $id);
			$parent_path = $parent ? (string)SQL::fetchSingle("SELECT path FROM bigtree_pages WHERE id = ?", $parent) : "";
			$new_path = ($parent_path ? $parent_path . "/" : "") . $route;

			SQL::update("bigtree_pages", $id, [
				"parent" => $parent,
				"route" => $route,
				"path" => $new_path,
				"updated_at" => "NOW()",
			]);

			// Descendant paths are stored, not derived — they must be rewritten too, or
			// the whole subtree 404s. Same helper the REST move uses.
			$this->repathChildren((string)$page["path"], $new_path);

			// Moving a trunk page changes the multi-site routing map (path-keyed).
			if (Flag::isOn($page["trunk"])) {
				$this->invalidateMultiSiteCache();
			}

			Hooks::fire("page.moved", ["id" => $id, "parent" => $parent, "via" => "ai_assistant"]);

			return [
				"mode" => "moved",
				"page_id" => $id,
				"title" => trim((string)$page["nav_title"]) ?: ("page #{$id}"),
				"path" => "/" . $new_path,
			];
		}

		/** Whether $id sits anywhere beneath $ancestor in the page tree. */
		private function isDescendantOf(int $id, int $ancestor): bool {
			$seen = 0;

			while ($id > 0 && $seen < 200) {
				$id = (int)SQL::fetchSingle("SELECT parent FROM bigtree_pages WHERE id = ?", $id);

				if ($id === $ancestor) {

					return true;
				}

				$seen++;
			}

			return false;
		}

		// Live page insert, shared by create() (publish path) and the pending-change
		// publish flow in publishPendingChange(). Returns the presented page payload.
		private function performCreate(array $d, $user): array {
			$parent = (int)($d["parent"] ?? 0);
			$nav_title = trim((string)$d["nav_title"]);
			$title = trim((string)($d["title"] ?? $nav_title));
			$route = $d["route"] ?? BigTreeCMS::urlify($nav_title);
			$route = $this->uniqueRoute($parent, $route);
			$parent_path = $parent ? SQL::fetchSingle("SELECT path FROM bigtree_pages WHERE id = ?", $parent) : "";
			$path = ($parent_path ? $parent_path . "/" : "") . $route;

			// Only developers can flag a page as a site trunk. Silently strip the flag
			// for non-dev callers so the request still succeeds (matches legacy createPage).
			$trunk_requested = !empty($d["trunk"]);
			$trunk_value = Flag::checkbox($trunk_requested && (int)$user->level >= 2);

			$insert = [
				"trunk" => $trunk_value,
				"parent" => $parent,
				"in_nav" => Flag::checkbox($d["in_nav"] ?? null),
				"nav_title" => BigTree::safeEncode($nav_title),
				"route" => $route,
				"path" => $path,
				"title" => BigTree::safeEncode($title),
				"meta_keywords" => $d["meta_keywords"] ?? "",
				"meta_description" => $d["meta_description"] ?? "",
				"seo_invisible" => Flag::checkbox($d["seo_invisible"] ?? null),
				"template" => $d["template"] ?? "",
				"external" => $d["external"] ?? "",
				"new_window" => Flag::checkbox($d["new_window"] ?? null),
				"resources" => json_encode($d["resources"] ?? new \stdClass()),
				"archived" => "",
				"archived_inherited" => "",
				"publish_at" => $d["publish_at"] ?? null,
				"expire_at" => $d["expire_at"] ?? null,
				"max_age" => (int)($d["max_age"] ?? 0),
				"last_edited_by" => $user->id,
				"position" => 0,
				"created_at" => "NOW()",
				"updated_at" => "NOW()",
			];

			$id = (int)SQL::insert("bigtree_pages", $insert);

			// If this new page takes over a previously-redirected route, the stale
			// redirect would steal traffic — drop it. (Mirrors legacy createPage:1730.)
			SQL::query("DELETE FROM bigtree_route_history WHERE old_route = ?", $path);

			// Tags + open-graph wiring (mirrors legacy createPage).
			$this->syncTags($id, $d["tags"] ?? null);
			$this->syncOpenGraph($id, $d["open_graph"] ?? null);

			// Trunk page added → multi-site path cache becomes stale.
			if (Flag::isOn($trunk_value)) $this->invalidateMultiSiteCache();

			$page = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $id);

			$this->allocatePageResources((int)$id, $page["template"], [
				"resources" => Json::decode($page["resources"]),
				"external" => $page["external"],
			]);

			// Fire the template's publish hook (legacy convention — extensions and
			// templates can register a function that runs on every save).
			$this->fireTemplatePublishHook(
				$page["template"], (int)$id, $insert, $d["tags"] ?? [], $d["open_graph"] ?? []
			);

			Hooks::fire("page.created", $page);
			EmbeddingService::deferIndex(function () use ($page) {
				EmbeddingService::indexPage($page);
			});

			return $this->present($page, true);
		}

		/**
		 * GET /pages/{id}/access-levels
		 * Port of legacy pages/access-levels.php: who can edit vs. publish this
		 * page. Every user is run through the page-permission resolution
		 * (explicit grant or inherited up the tree; admins+ are always
		 * publishers) and bucketed by the resulting rank.
		 */
		public function accessLevels(Request $request) {
			$id = $request->id();
			Entity::assertExists("bigtree_pages", $id, "Page");

			$publishers = [];
			$editors = [];
			$users = SQL::fetchAll("SELECT id, name, email, level, permissions FROM bigtree_users ORDER BY name");

			foreach ($users as $user) {
				$rank = PermissionService::userPageLevel($user, $id);

				if ($rank !== "p" && $rank !== "e") {
					continue;
				}

				$entry = [
					"id" => (int)$user["id"],
					"name" => $user["name"],
					"email" => $user["email"],
					"level" => (int)$user["level"],
				];

				if ($rank === "p") {
					$publishers[] = $entry;
				} else {
					$editors[] = $entry;
				}
			}

			return Response::ok(["publishers" => $publishers, "editors" => $editors]);
		}

		/**
		 * POST /pages/{id}/duplicate
		 * Port of legacy pages/duplicate.php: copy the page into a NEW pending
		 * draft under the same parent, with " (Copy)" titles and a fresh route.
		 * Requires publisher access on both the page and its parent, and (like
		 * legacy) refuses top-level pages. Improvement over legacy: tags and
		 * Open Graph are carried into the copy too.
		 */
		public function duplicate(Request $request) {
			$id = $request->id();
			$page = Entity::findOrFail("bigtree_pages", $id, "Page");

			$parent = (int)$page["parent"];

			if ($parent < 1) {
				throw new BadRequestException("Top-level pages can't be duplicated", "not_duplicatable");
			}

			$this->enforce($request->user, $id, "p", "duplicate");
			$this->enforce($request->user, $parent, "p", "duplicate into");

			$d = [
				"parent" => $parent,
				"nav_title" => $page["nav_title"] . " (Copy)",
				"title" => $page["title"] . " (Copy)",
				"route" => "",
				"in_nav" => !empty($page["in_nav"]),
				"meta_keywords" => $page["meta_keywords"],
				"meta_description" => $page["meta_description"],
				"seo_invisible" => !empty($page["seo_invisible"]),
				"template" => $page["template"],
				"external" => $page["external"],
				"new_window" => !empty($page["new_window"]),
				"resources" => Json::decode($page["resources"]),
				"publish_at" => $page["publish_at"],
				"expire_at" => $page["expire_at"],
				"max_age" => (int)$page["max_age"],
				"tags" => array_map(function ($t) {

					return (int)$t["id"];
				}, $this->loadTags($id)),
			];

			$og = $this->loadOpenGraph($id);

			if ($og) {
				$d["open_graph"] = $og;
			}

			$pending_id = $this->writePendingPageChange($request->user, "NEW", $parent, $d);

			Hooks::fire("page.pending_created", [
				"parent" => $parent, "pending_change_id" => (int)$pending_id, "duplicated_from" => $id,
			]);

			return Response::created(["pending_change_id" => (int)$pending_id, "pending" => true], null);
		}

		public function update(Request $request) {
			$id = $request->id();
			$this->enforce($request->user, $id, "e");

			$page = Entity::findOrFail("bigtree_pages", $id, "Page");

			$d = $request->body;

			// Publishers (and global admins/devs) may write live by passing publish=true;
			// everyone else — and publishers who leave it off — queues an EDIT draft.
			$access = PermissionService::userPageLevel($request->user, $id);
			$can_publish = PermissionService::isPublisher($request->user, $access);

			if (empty($d["publish"]) || !$can_publish) {
				$pending_id = $this->writePendingPageChange($request->user, "EDIT", $id, $d);

				Hooks::fire("page.pending_updated", [
					"id" => $id, "pending_change_id" => (int)$pending_id,
				]);

				return Response::ok(["pending" => true, "pending_change_id" => (int)$pending_id]);
			}

			return Response::ok($this->performUpdate($id, $page, $d, $request->user));
		}

		// Live page update, shared by update() (publish path) and the pending-change
		// publish flow in publishPendingChange(). Returns the presented page payload.
		private function performUpdate(int $id, array $page, array $d, $user): array {
			$update = [];

			if (isset($d["nav_title"])) {
				$update["nav_title"] = BigTree::safeEncode($d["nav_title"]);
			}

			if (isset($d["title"])) {
				$update["title"] = BigTree::safeEncode($d["title"]);
			}

			if (isset($d["meta_keywords"])) {
				$update["meta_keywords"] = (string)$d["meta_keywords"];
			}

			if (isset($d["meta_description"])) {
				$update["meta_description"] = (string)$d["meta_description"];
			}

			if (isset($d["seo_invisible"])) {
				$update["seo_invisible"] = Flag::checkbox($d["seo_invisible"]);
			}

			if (isset($d["in_nav"])) {
				$update["in_nav"] = Flag::checkbox($d["in_nav"]);
			}

			if (isset($d["template"])) {
				$update["template"] = (string)$d["template"];
			}

			if (isset($d["external"])) {
				$update["external"] = (string)$d["external"];
			}

			if (isset($d["new_window"])) {
				$update["new_window"] = Flag::checkbox($d["new_window"]);
			}

			if (isset($d["resources"])) {
				$update["resources"] = json_encode($d["resources"]);
			}

			if (isset($d["publish_at"])) {
				$update["publish_at"] = $d["publish_at"];
			}

			if (isset($d["expire_at"])) {
				$update["expire_at"] = $d["expire_at"];
			}

			if (isset($d["max_age"])) {
				$update["max_age"] = (int)$d["max_age"];
			}

			// Trunk flag is developer-only; non-devs silently can't change it.
			$trunk_changed = false;
			if (array_key_exists("trunk", $d) && (int)$user->level >= 2) {
				$new_trunk = Flag::checkbox($d["trunk"]);
				if ($new_trunk !== $page["trunk"]) {
					$update["trunk"] = $new_trunk;
					$trunk_changed = true;
				}
			}

			if (isset($d["route"]) && $d["route"] !== $page["route"]) {
				$new_route = $this->uniqueRoute((int)$page["parent"], $d["route"], $id);
				$update["route"] = $new_route;
				$parent_path = $page["parent"] ? SQL::fetchSingle("SELECT path FROM bigtree_pages WHERE id = ?", $page["parent"]) : "";
				$new_path = ($parent_path ? $parent_path . "/" : "") . $new_route;
				$update["path"] = $new_path;
				// Drop any existing redirect that points AT the new path (something else
				// used to live there) AND any old redirect from the page's prior path
				// (we'll replace it). Then insert the fresh redirect.
				SQL::query("DELETE FROM bigtree_route_history WHERE old_route = ? OR old_route = ?", $page["path"], $new_path);
				SQL::insert("bigtree_route_history", ["old_route" => $page["path"], "new_route" => $new_path]);
				$this->repathChildren($page["path"], $new_path);
			}

			if ($update) {
				$update["last_edited_by"] = $user->id;
				$update["updated_at"] = "NOW()";
				SQL::update("bigtree_pages", $id, $update);
			}

			// Multi-site cache must be invalidated if the trunk flag changed, OR if the
			// route changed on a page that already IS a trunk (path map is keyed by path).
			if ($trunk_changed || (isset($update["path"]) && Flag::isOn($page["trunk"]))) {
				$this->invalidateMultiSiteCache();
			}

			// Publishing live supersedes any queued draft for this page (mirrors
			// updatePage:9819) — otherwise a stale EDIT change would keep showing
			// "Changed" and could be re-approved over the freshly published content.
			// Drop the superseded draft's resource allocations before deleting it.
			foreach (SQL::fetchAllSingle(
				"SELECT id FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = ?", $id
			) as $stale_change_id) {
				\BigTree\Services\ResourceAllocationService::deallocateResources("bigtree_pages", "p".$stale_change_id);
			}

			SQL::delete("bigtree_pending_changes", ["table" => "bigtree_pages", "item_id" => $id]);

			// Tags + open-graph: only touch if the caller included the keys, so a partial
			// PATCH doesn't wipe existing tags/OG just because they weren't sent.
			if (array_key_exists("tags", $d)) {
				$this->syncTags($id, $d["tags"]);
			}

			if (array_key_exists("open_graph", $d)) {
				$this->syncOpenGraph($id, $d["open_graph"]);
			}

			// Template publish hook fires on update too. Pass the merged update payload
			// so the hook sees only what changed (matches legacy updatePage:9813).
			return $this->finishPageWrite(
				$id, $update ?: [], $d["tags"] ?? [], $d["open_graph"] ?? [], $page
			);
		}

		/**
		 * Write (or replace) a page pending change row. Editors save here always;
		 * publishers save here when they don't explicitly publish. The stored
		 * `changes` blob is the normalized edit payload (minus the publish flag,
		 * tags, and open graph, which live in their own columns) so the approval
		 * flow can replay it through performCreate()/performUpdate().
		 *
		 * For EDIT, an existing queued change for the same page is overwritten so a
		 * user's repeated saves collapse into one draft (mirrors submitPageChange).
		 */
		private function writePendingPageChange($user, string $type, int $item_or_parent, array $d): int {
			[$changes, $tags_changes, $open_graph_changes] = $this->pendingChangeFields($user, $d);

			$row = [
				"user" => (int)$user->id,
				"date" => "NOW()",
				"table" => "bigtree_pages",
				"mtm_changes" => [],
				"tags_changes" => $tags_changes,
				"open_graph_changes" => $open_graph_changes,
				"module" => "",
			];

			if ($type === "NEW") {
				$changes["parent"] = $item_or_parent;
				$row["changes"] = $changes;
				$row["title"] = "New Page Created";
				$row["type"] = "NEW";
				$row["pending_page_parent"] = $item_or_parent;

				$change_id = (int)SQL::insert("bigtree_pending_changes", $row);
				$this->allocatePageResources("p".$change_id, $changes["template"] ?? "", $changes);

				return $change_id;
			}

			// EDIT — collapse onto any existing queued change for this page.
			$row["changes"] = $changes;
			$row["title"] = "Page Change Pending";
			$row["type"] = "EDIT";
			$row["item_id"] = $item_or_parent;
			$row["pending_page_parent"] = 0;

			$existing = SQL::fetchSingle(
				"SELECT id FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = ? AND type = 'EDIT'",
				$item_or_parent
			);

			if ($existing) {
				SQL::update("bigtree_pending_changes", (int)$existing, $row);
				$this->allocatePageResources("p".(int)$existing, $changes["template"] ?? "", $changes);

				return (int)$existing;
			}

			$change_id = (int)SQL::insert("bigtree_pending_changes", $row);
			$this->allocatePageResources("p".$change_id, $changes["template"] ?? "", $changes);

			return $change_id;
		}

		/**
		 * Normalize a submitted page body into the three stored pieces of a pending
		 * change: the `changes` blob (tags/open_graph kept inside it, only when
		 * actually submitted, so publish can mirror the live array_key_exists path)
		 * and the separate NOT NULL tags/OG columns (kept for the SPA's diff view).
		 *
		 * @return array{0: array, 1: array, 2: array} [changes, tags_changes, open_graph_changes]
		 */
		private function pendingChangeFields($user, array $d): array {
			$changes = $d;
			unset($changes["publish"]);

			// Developer-only trunk flag: strip for non-devs so it can't be smuggled in.
			if ((int)$user->level < 2) {
				unset($changes["trunk"]);
			}

			$tags_changes = array_key_exists("tags", $d) ? array_values($d["tags"] ?? []) : [];
			$open_graph_changes = array_key_exists("open_graph", $d) ? ($d["open_graph"] ?? []) : [];

			return [$changes, $tags_changes, $open_graph_changes];
		}

		/**
		 * Apply a queued page pending change to the live tree, then delete the
		 * queue row. NEW promotes the draft to a real page; EDIT replays the saved
		 * field changes onto the existing page. Called by PendingChangeService on
		 * approve. Returns the presented live page payload.
		 */
		public function publishPendingChange(array $row, $user): array {
			// tags/open_graph (when submitted) live inside the changes blob, so the
			// performCreate/performUpdate paths see them exactly as a live save would.
			$d = Json::decode($row["changes"]);

			if ($row["type"] === "NEW") {
				$d["parent"] = (int)($d["parent"] ?? $row["pending_page_parent"] ?? 0);
				$result = $this->performCreate($d, $user);
			} else {
				$id = (int)$row["item_id"];
				$page = Entity::findOrFail("bigtree_pages", $id, "Page");

				$result = $this->performUpdate($id, $page, $d, $user);
			}

			SQL::delete("bigtree_pending_changes", (int)$row["id"]);
			// performCreate/performUpdate already allocated against the live page id;
			// drop the now-deleted draft's allocations so they don't dangle.
			\BigTree\Services\ResourceAllocationService::deallocateResources("bigtree_pages", "p".(int)$row["id"]);

			return $result;
		}

		/**
		 * Overlay a live page's queued EDIT draft onto its presented payload so the
		 * edit screen keeps editing the draft instead of the published content
		 * (mirrors legacy getPendingPage). Sets `changes_applied` + `pending_change_id`
		 * so the SPA can show a "you're editing an unpublished draft" indicator.
		 */
		private function applyPendingOverlay(array &$out, int $id): void {
			$change = SQL::fetch(
				"SELECT * FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = ? AND type = 'EDIT'",
				$id
			);

			if (!$change) {
				return;
			}

			$changes = Json::decode($change["changes"]);

			// The change blob stores values in the same shape the SPA submits (and
			// present() emits), so a direct overlay is safe for each known field.
			$overlay_fields = [
				"nav_title", "title", "route", "in_nav", "template", "external",
				"new_window", "meta_keywords", "meta_description", "seo_invisible",
				"publish_at", "expire_at", "max_age", "trunk", "resources", "open_graph",
			];

			// Capture the published value of each overlaid field before replacing it
			// so the SPA can show a published-vs-pending comparison per field, and
			// record which fields actually differ for the "Pending" markers.
			$original = [];
			$changed_fields = [];

			foreach ($overlay_fields as $field) {
				if (array_key_exists($field, $changes)) {
					$published = $out[$field] ?? null;
					$original[$field] = $published;
					$out[$field] = $changes[$field];

					// Compare structurally for arrays/objects, stringwise for
					// scalars, so "1" vs 1 (or reordered nothing) isn't a false diff.
					$matches = (is_array($published) || is_array($changes[$field]))
						? json_encode($published) === json_encode($changes[$field])
						: (string)$published === (string)$changes[$field];

					if (!$matches) {
						$changed_fields[] = $field;
					}
				}
			}

			$owner_id = !empty($change["user"]) ? (int)$change["user"] : null;

			$out["changes_applied"] = true;
			$out["pending_change_id"] = (int)$change["id"];
			$out["pending_original"] = $original;
			$out["changed_fields"] = $changed_fields;
			// Attribute the draft so the SPA can label it ("Your draft" vs "Draft by …").
			$out["pending_owner"] = $owner_id;
			$out["pending_owner_name"] = $owner_id
				? SQL::fetchSingle("SELECT name FROM bigtree_users WHERE id = ?", $owner_id)
				: null;
			$out["updated_at"] = $change["date"];
		}

		/**
		 * GET /pages/pending/{pcid} — load a NEW page draft (one that only lives in
		 * bigtree_pending_changes) as an edit-ready page payload. Access derives from
		 * the draft's intended parent.
		 */
		public function getPending(Request $request) {
			$pcid = $request->routeParam("pcid", "int");
			$change = $this->loadPendingNew($pcid);
			$parent = (int)$change["pending_page_parent"];
			$this->enforce($request->user, $parent, "e", "edit draft");

			$out = $this->presentPending($change, $request->user);

			if (!empty($request->query["fields"]) && strpos((string)$request->query["fields"], "lineage") !== false) {
				$out["lineage"] = $this->lineage($parent);
			}

			return Response::ok($out);
		}

		/**
		 * PATCH /pages/pending/{pcid} — re-save a NEW page draft, or (with publish=true
		 * and publisher rights) promote it to a live page. Returns the live page
		 * payload on publish, or a pending marker on draft save.
		 */
		public function updatePending(Request $request) {
			$pcid = $request->routeParam("pcid", "int");
			$change = $this->loadPendingNew($pcid);
			$parent = (int)$change["pending_page_parent"];
			$this->enforce($request->user, $parent, "e", "edit draft");

			$d = $request->body;
			$access = PermissionService::userPageLevel($request->user, $parent);
			$can_publish = PermissionService::isPublisher($request->user, $access);

			if (!empty($d["publish"]) && $can_publish) {
				// Replay the latest form data into the change before promoting it, so
				// publish reflects what's on screen, then publishPendingChange creates
				// the live page and drops the queue row.
				$change["changes"] = $this->stampPendingNew($pcid, $request->user, $parent, $d);
				// publishPendingChange → performCreate fires page.created itself.
				$result = $this->publishPendingChange($change, $request->user);

				return Response::ok($result);
			}

			$this->stampPendingNew($pcid, $request->user, $parent, $d);

			Hooks::fire("page.pending_updated", [
				"pending_change_id" => $pcid, "parent" => $parent,
			]);

			return Response::ok(["pending" => true, "pending_change_id" => $pcid]);
		}

		/** Load a NEW page draft row or 404. */
		private function loadPendingNew(int $pcid): array {
			$change = SQL::fetch(
				"SELECT * FROM bigtree_pending_changes WHERE id = ? AND `table` = 'bigtree_pages' AND type = 'NEW'",
				$pcid
			);

			if (!$change) {
				throw new NotFoundException("Page draft $pcid not found");
			}

			return $change;
		}

		/** Rewrite a NEW draft's stored data from a fresh submission; returns the new changes array. */
		private function stampPendingNew(int $pcid, $user, int $parent, array $d): array {
			[$changes, $tags_changes, $open_graph_changes] = $this->pendingChangeFields($user, $d);
			$changes["parent"] = $parent;

			SQL::update("bigtree_pending_changes", $pcid, [
				"user" => (int)$user->id,
				"date" => "NOW()",
				"changes" => $changes,
				"tags_changes" => $tags_changes,
				"open_graph_changes" => $open_graph_changes,
			]);

			$this->allocatePageResources("p".$pcid, $changes["template"] ?? "", $changes);

			return $changes;
		}

		/** Shape a NEW draft change row into the same payload as a live PageDetail. */
		private function presentPending(array $change, $user): array {
			$changes = Json::decode($change["changes"]);
			$parent = (int)$change["pending_page_parent"];

			return [
				"id" => 0,
				"parent" => $parent,
				"access" => PermissionService::userPageLevel($user, $parent),
				"trunk" => !empty($changes["trunk"]),
				"in_nav" => !empty($changes["in_nav"]),
				"nav_title" => (string)($changes["nav_title"] ?? ""),
				"route" => (string)($changes["route"] ?? ""),
				"path" => "",
				"title" => (string)($changes["title"] ?? ""),
				"meta_keywords" => (string)($changes["meta_keywords"] ?? ""),
				"meta_description" => (string)($changes["meta_description"] ?? ""),
				"seo_invisible" => !empty($changes["seo_invisible"]),
				"template" => (string)($changes["template"] ?? ""),
				"external" => (string)($changes["external"] ?? ""),
				"new_window" => !empty($changes["new_window"]),
				"resources" => $changes["resources"] ?? new \stdClass(),
				"archived" => !empty($changes["archived"]),
				"archived_inherited" => false,
				"publish_at" => $changes["publish_at"] ?? null,
				"expire_at" => $changes["expire_at"] ?? null,
				"max_age" => (int)($changes["max_age"] ?? 0),
				"last_edited_by" => (int)$change["user"],
				"position" => 0,
				"created_at" => $change["date"],
				"updated_at" => $change["date"],
				// A NEW draft has no live row yet, so no cached analytics.
				"ga_page_views" => null,
				"tags" => [],
				"open_graph" => $changes["open_graph"] ?? null,
				"changes_applied" => true,
				"pending" => true,
				"pending_change_id" => (int)$change["id"],
			];
		}

		public function delete(Request $request) {
			$id = $request->id();
			$this->enforce($request->user, $id, "p");
			Entity::assertExists("bigtree_pages", $id, "Page");

			$page = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $id);
			// Clean up rel rows; FK ON DELETE CASCADE handles open_graph + revisions etc.
			SQL::query("DELETE FROM bigtree_tags_rel WHERE `table` = 'bigtree_pages' AND entry = ?", $id);
			SQL::query("DELETE FROM bigtree_open_graph WHERE `table` = 'bigtree_pages' AND entry = ?", $id);
			$this->cascadeDelete($id, $page["path"]);

			// Deleting a trunk page changes the multi-site routing map.
			if (Flag::isOn($page["trunk"])) $this->invalidateMultiSiteCache();

			Hooks::fire("page.deleted", $page);
			EmbeddingService::deletePage((int)$id);

			return Response::noContent();
		}

		public function archive(Request $request) {
			$id = $request->id();
			$this->enforce($request->user, $id, "p");
			SQL::update("bigtree_pages", $id, ["archived" => "on", "updated_at" => "NOW()"]);
			$this->setArchivedInherited($id, "on");
			EmbeddingService::deletePage((int)$id);

			return Response::noContent();
		}

		public function unarchive(Request $request) {
			$id = $request->id();
			$this->enforce($request->user, $id, "p");
			SQL::update("bigtree_pages", $id, ["archived" => "", "updated_at" => "NOW()"]);
			$this->setArchivedInherited($id, "");
			$page = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $id);

			if ($page) {
				EmbeddingService::deferIndex(function () use ($page) {
					EmbeddingService::indexPage($page);
				});
			}

			return Response::noContent();
		}

		public function move(Request $request) {
			$id = $request->id();
			$new_parent = $request->bodyInt("parent");
			$this->enforce($request->user, $id, "p");
			$this->enforce($request->user, $new_parent, "e", "move into parent");

			$page = Entity::findOrFail("bigtree_pages", $id, "Page");

			$parent_path = $new_parent ? SQL::fetchSingle("SELECT path FROM bigtree_pages WHERE id = ?", $new_parent) : "";
			$new_path = ($parent_path ? $parent_path . "/" : "") . $page["route"];

			SQL::update("bigtree_pages", $id, ["parent" => $new_parent, "path" => $new_path, "updated_at" => "NOW()"]);
			$this->repathChildren($page["path"], $new_path);

			// Moving a trunk page changes the multi-site routing map (path-keyed).
			if (Flag::isOn($page["trunk"])) $this->invalidateMultiSiteCache();

			return Response::noContent();
		}

		/**
		 * GET /pages/sites
		 *
		 * Returns the configured multi-site map so the SPA can render a "create page
		 * under [site]" picker. In a single-site install this returns an empty array.
		 * Each entry includes the trunk page id + its current path — the SPA can use
		 * that path to display the site root, and the trunk id as the parent param
		 * when creating pages under that site.
		 */
		public function sites(Request $request) {
			global $bigtree;
			$config = $bigtree["config"]["sites"] ?? [];
			$out = [];
			foreach ($config as $key => $site) {
				$trunk_id = (int)($site["trunk"] ?? 0);
				$trunk_row = $trunk_id ? SQL::fetch("SELECT id, path, nav_title FROM bigtree_pages WHERE id = ?", $trunk_id) : null;
				$out[] = [
					"key" => $key,
					"domain" => $site["domain"] ?? "",
					"www_root" => $site["www_root"] ?? "",
					"static_root" => $site["static_root"] ?? ($site["www_root"] ?? ""),
					"trunk_id" => $trunk_id,
					"trunk_path" => $trunk_row["path"] ?? null,
					"trunk_nav_title" => $trunk_row ? Sanitize::decodeEntities($trunk_row["nav_title"]) : null,
				];
			}
			return Response::ok($out);
		}

		/**
		 * Decide which requested ids may actually be repositioned, given the set of
		 * real child page ids of the target folder. Pending NEW pages carry a
		 * bigtree_pending_changes id (a different table), and ids from other folders
		 * are not children of $parent — both must be ignored so reorder cannot
		 * rewrite an unrelated live page's position.
		 *
		 * Pure helper (no DB) so the corruption-guard logic can be unit-tested
		 * directly. $childIds is the set of genuine child page ids; $requestedIds is
		 * the ordered id list from the request. Returns the requested ids that are
		 * real children, in the requested order.
		 *
		 * @param int[] $childIds
		 * @param int[] $requestedIds
		 * @return int[]
		 */
		public static function filterReorderableIds(array $childIds, array $requestedIds): array
		{
			$valid = array_flip(array_map("intval", $childIds));
			$reorderable = [];

			foreach ($requestedIds as $id) {
				$id = (int)$id;

				if (isset($valid[$id])) {
					$reorderable[] = $id;
				}
			}

			return $reorderable;
		}

		public function reorder(Request $request) {
			$parent = $request->routeParam("parent", "int");
			$this->enforce($request->user, $parent, "e", "reorder children of");
			$ids = $request->bodyList("ids", "int");

			// Only real pages that are actually children of $parent may be repositioned.
			// This rejects pending-change ids (which live in a different table) and ids
			// from other folders, both of which would otherwise corrupt unrelated rows.
			$childIds = [];

			if ($ids) {
				$placeholders = Sanitize::placeholders($ids);
				$rows = SQL::fetchAll(
					"SELECT id FROM bigtree_pages WHERE parent = ? AND id IN ($placeholders)",
					...array_merge([$parent], $ids)
				);
				$childIds = array_map("intval", array_column($rows, "id"));
			}

			$reorderable = array_flip(self::filterReorderableIds($childIds, $ids));
			$pos = count($ids);

			foreach ($ids as $id) {
				if (isset($reorderable[$id])) {
					SQL::update("bigtree_pages", $id, ["position" => $pos--, "updated_at" => "NOW()"]);
				} else {
					// The dropped id still consumes its slot, so the surviving real
					// pages keep their intended relative spacing.
					$pos--;
				}
			}

			return Response::noContent();
		}

		public function listRevisions(Request $request) {
			$id = $request->id();
			$this->enforce($request->user, $id, "v");
			$rows = SQL::fetchAll(
				"SELECT id, page, title, author, saved, saved_description, updated_at FROM bigtree_page_revisions WHERE page = ? ORDER BY updated_at DESC, id DESC",
				$id
			);

			return Response::ok(array_map(function ($r) {

				return [
					"id" => (int)$r["id"],
					"page" => (int)$r["page"],
					"title" => $r["title"],
					"author" => (int)$r["author"],
					"saved" => Flag::isOn($r["saved"]),
					"saved_description" => $r["saved_description"],
					"updated_at" => $r["updated_at"],
				];
			}, $rows));
		}

		public function saveRevision(Request $request) {
			$id = $request->id();
			$this->enforce($request->user, $id, "p");
			$desc = $request->bodyString("description", "", false);
			$page = Entity::findOrFail("bigtree_pages", $id, "Page");

			$rev_id = $this->insertRevisionSnapshot($id, $page, (int)$request->user->id, $desc);

			return Response::created(["id" => $rev_id], null);
		}

		public function deleteRevision(Request $request) {
			$id = $request->id();
			$rev_id = $request->routeParam("rev_id", "int");
			$this->enforce($request->user, $id, "p");
			SQL::delete("bigtree_page_revisions", $rev_id);

			return Response::noContent();
		}

		/**
		 * Restore a saved/auto revision back onto the live page. The SPA edits
		 * pages directly (there is no separate draft layer), so this overwrites
		 * the page's content columns with the revision's. The current published
		 * state is first snapshotted as an auto-revision so the restore is
		 * reversible. Publisher access required.
		 */
		public function restoreRevision(Request $request) {
			$id = $request->id();
			$rev_id = $request->routeParam("rev_id", "int");
			$this->enforce($request->user, $id, "p");

			$page = Entity::findOrFail("bigtree_pages", $id, "Page");

			$revision = Entity::fetchOrFail(
				"SELECT * FROM bigtree_page_revisions WHERE id = ? AND page = ?",
				[$rev_id, $id],
				"Revision $rev_id not found for page $id"
			);

			// Snapshot the current published state so the restore can be undone.
			$this->insertRevisionSnapshot($id, $page, (int)$request->user->id);

			$update = [];

			foreach (self::REVISION_COLUMNS as $col) {
				$update[$col] = $revision[$col];
			}

			$update["last_edited_by"] = $request->user->id;
			$update["updated_at"] = "NOW()";
			SQL::update("bigtree_pages", $id, $update);

			return Response::ok($this->finishPageWrite($id, $update, [], [], $page));
		}

		/**
		 * Search pages by title/nav_title with permission filtering. Overfetches
		 * ×3 so permission-hidden rows don't starve the result set under the
		 * caller's visibility. Shared by GET /pages/search and the federated
		 * SearchService pages domain — both must return the same row shape and
		 * the same overfetch semantics.
		 *
		 * @param string $q     Search query.
		 * @param mixed  $user  Authenticated user (PermissionService).
		 * @param int    $limit Max rows to return after filtering.
		 */
		public function searchRows(string $q, $user, int $limit): array {
			$like = Sanitize::likeTerm($q);
			// LIMIT needs an integer literal; ? substitution would quote it.
			// Overfetch so permission filtering doesn't starve the result set.
			$overfetch = max(1, (int)$limit) * 3;
			$rows = SQL::fetchAll(
				"SELECT id, nav_title, path, archived
				 FROM bigtree_pages
				 WHERE (nav_title LIKE ? OR title LIKE ?)
				 ORDER BY archived ASC, nav_title ASC
				 LIMIT $overfetch",
				$like, $like
			);
			$kept = [];

			foreach ($rows as $r) {
				if (PermissionService::userPageLevel($user, (int)$r["id"]) === "n") {
					continue;
				}

				$kept[] = [
					"id" => (int)$r["id"],
					"nav_title" => Sanitize::decodeEntities($r["nav_title"]),
					"path" => $r["path"],
					"archived" => Flag::isOn($r["archived"]),
				];

				if (count($kept) >= $limit) {
					break;
				}
			}

			return $kept;
		}

		public function search(Request $request) {
			$q = $request->queryString("q");

			if ($q === "") {
				return Response::ok([]);
			}

			return Response::ok($this->searchRows($q, $request->user, 25));
		}

		// — helpers —

		private function enforce($user, $page_id, $min, $action = "access") {
			if (!PermissionService::userHasPageAccess($user, $page_id, $min)) {
				throw new AuthorizationException("Insufficient page permission to $action ($min required)");
			}
		}

		/**
		 * Shared write epilogue for update and restoreRevision: re-fetch the live
		 * row, allocate resources, fire the template publish hook + page.updated
		 * hook, and return the presented row.
		 */
		private function finishPageWrite(int $id, array $update, array $tags, array $og, array $previous): array {
			$fresh = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $id);

			$this->allocatePageResources($id, $fresh["template"], [
				"resources" => Json::decode($fresh["resources"]),
				"external" => $fresh["external"],
			]);

			$this->fireTemplatePublishHook($fresh["template"], $id, $update, $tags, $og);
			Hooks::fire("page.updated", $fresh, ["previous" => $previous]);
			EmbeddingService::deferIndex(function () use ($fresh) {
				EmbeddingService::indexPage($fresh);
			});

			return $this->present($fresh, true);
		}

		/**
		 * Write a snapshot of a page's content columns into bigtree_page_revisions.
		 * A non-empty description marks it a user-saved revision; the default (empty
		 * description) produces the auto-revision used to make a restore reversible.
		 */
		private function insertRevisionSnapshot(int $id, array $page, int $author, string $desc = ""): int {
			$row = [
				"page" => $id,
				"author" => $author,
				"saved" => Flag::checkbox($desc !== ""),
				"saved_description" => $desc,
				"resource_allocation" => "",
				"has_deleted_resources" => "",
			];

			foreach (self::REVISION_COLUMNS as $col) {
				$row[$col] = $page[$col];
			}

			return (int)SQL::insert("bigtree_page_revisions", $row);
		}

		/**
		 * Find a route that doesn't collide with another sibling page. At top level
		 * (parent=0) the route additionally can't collide with one of the reserved-route list's
		 * reserved top-level routes (ajax, css, feeds, js, sitemap.xml, _preview,
		 * _preview-pending, etc.) or with a directory name under site/. We auto-suffix
		 * with -2, -3, ... until clear (mirrors legacy createPage:1593-1612).
		 */
		private function uniqueRoute($parent, $base, $exclude_id = 0) {
			$base = $base ?: "page";
			$route = $base;
			$x = 2;

			// Reserved-route check at top level.
			if ((int)$parent === 0) {
				$reserved = PageService::reservedTopLevelRoutes();
				$site_dirs = $this->reservedSiteDirectories();
				while (in_array($route, $reserved, true) || in_array($route, $site_dirs, true)) {
					$route = $base . "-" . $x++;
				}
			}

			$args = [$parent, $route];
			$sql = "SELECT id FROM bigtree_pages WHERE parent = ? AND route = ?";
			if ($exclude_id) { $sql .= " AND id != ?"; $args[] = $exclude_id; }

			while (SQL::fetchSingle(...array_merge([$sql], $args))) {
				$route = $base . "-" . $x++;
				$args[1] = $route;
			}

			return $route;
		}

		/** Mirrors the legacy createPage check against directory entries in site/. */
		private function reservedSiteDirectories() {
			static $cached = null;
			if ($cached !== null) return $cached;
			$cached = [];
			$site_dir = SERVER_ROOT . "site/";
			if (is_dir($site_dir)) {
				foreach (scandir($site_dir) ?: [] as $entry) {
					if ($entry === "." || $entry === "..") continue;
					if (is_dir($site_dir . $entry)) $cached[] = $entry;
				}
			}
			return $cached;
		}

		/**
		 * Wipe the multi-site path → site map cache. BigTreeCMS rebuilds it on next
		 * frontend boot from bigtree_pages.trunk + $bigtree["config"]["sites"], so
		 * deleting the file is enough; no manual rebuild needed.
		 */
		private function invalidateMultiSiteCache() {
			$path = SERVER_ROOT . "cache/bigtree-multi-site-cache.json";
			if (file_exists($path)) @unlink($path);
		}

		/**
		 * Fire a template's publish hook with the legacy signature:
		 *   function(string $table, int $entry_id, array $data, array $mtm, array $tags, array $open_graph)
		 *
		 * Hook is a function name string stored in the template's JSONDB entry under
		 * hooks.publish. Failures are logged and swallowed — the page write already
		 * succeeded and we don't want a buggy hook to make the API return 500 on a
		 * successful save (matches legacy behavior; legacy lets the call_user_func
		 * warning surface but doesn't abort the page write either).
		 *
		 * If the hook does want to signal an error visibly, it can throw a
		 * BigTree\Api\Exceptions\ApiException — those bubble up through ErrorHandler.
		 */
		private function fireTemplatePublishHook($template_id, $page_id, array $data, $tags, $open_graph) {
			if (!$template_id) return;

			$template = \BigTreeJSONDB::get("templates", $template_id);
			$hook = $template["hooks"]["publish"] ?? null;
			if (!$hook) return;
			if (!is_callable($hook)) {
				// Legacy stores function names as strings; if the function isn't loaded
				// (e.g. an extension that ships an autoloaded hook fn isn't installed
				// in API context yet), log so the dev can investigate, but don't fail.
				BigTree::log("Template publish hook for '$template_id' is not callable: " . print_r($hook, true));
				return;
			}

			try {
				call_user_func(
					$hook,
					"bigtree_pages",
					(int)$page_id,
					$data,
					[],
					is_array($tags) ? $tags : [],
					is_array($open_graph) ? $open_graph : []
				);
			} catch (\BigTree\Api\Exceptions\ApiException $e) {
				// Let API-aware hooks signal failure cleanly.
				throw $e;
			} catch (\Throwable $e) {
				BigTree::log("Template publish hook for '$template_id' threw: " . $e->getMessage());
			}
		}

		/**
		 * (Re)allocate the resources referenced by a page or page pending change. The
		 * API stores page data without running field processors, so we scan the stored
		 * data directly. `$entry` is the live page id or a "p"-prefixed pending change
		 * id; reference-field columns (which store bare resource ids) are resolved from
		 * the template definition. Mirrors the backfill migration's page scan.
		 */
		private function allocatePageResources($entry, $template_id, array $data): void {
			$template = $template_id ? \BigTreeJSONDB::get("templates", (string)$template_id) : null;
			$reference_keys = \BigTree\Services\ResourceAllocationService::getResourceReferenceKeys($template["resources"] ?? []);

			\BigTree\Services\ResourceAllocationService::allocateResourcesFromData("bigtree_pages", $entry, $data, $reference_keys);
		}

		private function repathChildren($old_path, $new_path) {
			$descendants = SQL::fetchAll("SELECT id, path FROM bigtree_pages WHERE path LIKE ?", $old_path . "/%");

			foreach ($descendants as $d) {
				$updated = $new_path . substr($d["path"], strlen($old_path));
				SQL::update("bigtree_pages", $d["id"], ["path" => $updated]);
			}
		}

		private function cascadeDelete($id, $path) {
			$children = SQL::fetchAllSingle("SELECT id FROM bigtree_pages WHERE path LIKE ?", $path . "%");

			foreach ($children as $cid) {
				// Drop resource allocations for the page and any queued drafts before
				// the row goes away, so nothing dangles in bigtree_resource_allocation.
				\BigTree\Services\ResourceAllocationService::deallocateResources("bigtree_pages", (int)$cid);

				foreach (SQL::fetchAllSingle(
					"SELECT id FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = ?", (int)$cid
				) as $change_id) {
					\BigTree\Services\ResourceAllocationService::deallocateResources("bigtree_pages", "p".$change_id);
				}

				EmbeddingService::deletePage((int)$cid);
				SQL::delete("bigtree_pages", (int)$cid);
			}
		}

		private function setArchivedInherited($parent_id, $value) {
			$page = SQL::fetch("SELECT path FROM bigtree_pages WHERE id = ?", $parent_id);

			if (!$page) {
				return;
			}
			SQL::query(
				"UPDATE bigtree_pages SET archived_inherited = ?, updated_at = NOW() WHERE path LIKE ? AND id != ?",
				$value, $page["path"] . "/%", $parent_id
			);
		}

		private function lineage($page_id) {
			$out = [];
			$current = (int)$page_id;

			while ($current > 0) {
				$row = SQL::fetch("SELECT id, parent, nav_title, route FROM bigtree_pages WHERE id = ?", $current);

				if (!$row) {
					break;
				}
				array_unshift($out, [
					"id" => (int)$row["id"],
					"nav_title" => Sanitize::decodeEntities($row["nav_title"]),
					"route" => $row["route"],
				]);
				$current = (int)$row["parent"];
			}

			return $out;
		}

		// — Tag + Open Graph helpers —

		/**
		 * Replace the page's tag-rel rows with the given list of tag ids. Pass null
		 * (don't include the "tags" key) to leave tags untouched on PATCH.
		 */
		private function syncTags($page_id, $tag_ids) {
			if (!is_array($tag_ids)) {
				return;
			}

			$tag_ids = array_values(array_unique(array_filter(array_map("intval", $tag_ids))));

			$existing = SQL::fetchAllSingle("SELECT tag FROM bigtree_tags_rel WHERE `table` = 'bigtree_pages' AND entry = ?", $page_id);
			$existing = array_map("intval", $existing);

			$to_add = array_diff($tag_ids, $existing);
			$to_remove = array_diff($existing, $tag_ids);

			foreach ($to_add as $tag) {
				// Confirm the tag exists before linking — silently skip orphan ids.
				if (SQL::exists("bigtree_tags", $tag)) {
					SQL::insert("bigtree_tags_rel", [
						"table" => "bigtree_pages",
						"entry" => (string)$page_id,
						"tag" => (int)$tag,
					]);
				}
			}

			if ($to_remove) {
				$placeholders = Sanitize::placeholders($to_remove);
				$args = array_merge([(string)$page_id], array_values($to_remove));
				SQL::query("DELETE FROM bigtree_tags_rel WHERE `table` = 'bigtree_pages' AND entry = ? AND tag IN ($placeholders)", ...$args);
			}

			// Recompute usage counts for affected tags so the SPA sees fresh totals.
			$affected = array_values(array_unique(array_merge($to_add, $to_remove)));

			foreach ($affected as $t) {
				$count = (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_tags_rel WHERE tag = ?", $t);
				SQL::update("bigtree_tags", $t, ["usage_count" => $count]);
			}
		}

		/**
		 * Upsert the bigtree_open_graph row for this page. Pass null (omit "open_graph"
		 * key) to leave OG untouched on PATCH; pass [] to explicitly clear.
		 */
		private function syncOpenGraph($page_id, $og) {
			if ($og === null) {
				return;
			}
			SQL::delete("bigtree_open_graph", ["table" => "bigtree_pages", "entry" => $page_id]);

			if (!is_array($og) || $og === []) {
				return;
			}

			SQL::insert("bigtree_open_graph", [
				"table" => "bigtree_pages",
				"entry" => $page_id,
				"title" => BigTree::safeEncode((string)($og["title"] ?? "")),
				"description" => BigTree::safeEncode((string)($og["description"] ?? "")),
				"type" => BigTree::safeEncode((string)($og["type"] ?? "")),
				"image" => BigTree::safeEncode((string)($og["image"] ?? "")),
				"image_width" => (int)($og["image_width"] ?? 0),
				"image_height" => (int)($og["image_height"] ?? 0),
			]);
		}

		private function loadTags($page_id) {
			$rows = SQL::fetchAll(
				"SELECT t.id, t.tag, t.route, t.usage_count
				 FROM bigtree_tags t
				 INNER JOIN bigtree_tags_rel r ON r.tag = t.id
				 WHERE r.`table` = 'bigtree_pages' AND r.entry = ?",
				$page_id
			);

			return array_map([TagService::class, "presentRow"], $rows);
		}

		private function loadOpenGraph($page_id) {
			$row = SQL::fetch(
				"SELECT title, description, type, image, image_width, image_height
				 FROM bigtree_open_graph WHERE `table` = 'bigtree_pages' AND entry = ?",
				$page_id
			);

			if (!$row) {
				return null;
			}
			return [
				"title" => $row["title"],
				"description" => $row["description"],
				"type" => $row["type"],
				"image" => $row["image"],
				"image_width" => (int)$row["image_width"],
				"image_height" => (int)$row["image_height"],
			];
		}

		private function present(array $p, $include_associations = false) {
			$out = [
				"id" => (int)$p["id"],
				"trunk" => Flag::isOn($p["trunk"]),
				"parent" => (int)$p["parent"],
				"in_nav" => Flag::isOn($p["in_nav"]),
				"nav_title" => Sanitize::decodeEntities($p["nav_title"]),
				"route" => $p["route"],
				"path" => $p["path"],
				"title" => Sanitize::decodeEntities($p["title"]),
				"meta_keywords" => Sanitize::decodeEntities($p["meta_keywords"]),
				"meta_description" => Sanitize::decodeEntities($p["meta_description"]),
				"seo_invisible" => Flag::isOn($p["seo_invisible"]),
				"template" => $p["template"],
				"external" => Sanitize::decodeEntities($p["external"]),
				"new_window" => Flag::isOn($p["new_window"]),
				"resources" => json_decode($p["resources"] ?: "{}", true) ?: new \stdClass(),
				"archived" => Flag::isOn($p["archived"]),
				"archived_inherited" => Flag::isOn($p["archived_inherited"]),
				"publish_at" => $p["publish_at"],
				"expire_at" => $p["expire_at"],
				"max_age" => (int)$p["max_age"],
				"last_edited_by" => (int)$p["last_edited_by"],
				"position" => (int)$p["position"],
				"created_at" => $p["created_at"],
				"updated_at" => $p["updated_at"],
				// Cached last-30-days page views, populated per-path by the GA4 sync
				// (BigTreeGoogleAnalytics4::cacheInformation). NULL until first sync /
				// when analytics isn't connected.
				"ga_page_views" => isset($p["ga_page_views"]) && $p["ga_page_views"] !== null
					? (int)$p["ga_page_views"]
					: null,
			];

			if ($include_associations) {
				$out["tags"] = $this->loadTags((int)$p["id"]);
				$out["open_graph"] = $this->loadOpenGraph((int)$p["id"]);
			}

			return $out;
		}
	
		public static function getPageSEORating($page, $content) {
			$template = BigTreeCMS::getTemplate($page["template"]);

			if (empty($template)) {
				return null;
			}

			$tsources = [];
			$h1_field = "";
			$body_fields = [];

			if (is_array($template["resources"])) {
				foreach ($template["resources"] as $item) {
					if (isset($item["seo_body"]) && $item["seo_body"]) {
						$body_fields[] = $item["id"];
					}
					if (isset($item["seo_h1"]) && $item["seo_h1"]) {
						$h1_field = $item["id"];
					}
					$tsources[$item["id"]] = $item;
				}
			}

			if (!$h1_field && !empty($tsources["page_header"])) {
				$h1_field = "page_header";
			}

			if (!count($body_fields) && !empty($tsources["page_content"])) {
				$body_fields[] = "page_content";
			}

			$textStats = new TextStatistics;
			$recommendations = [];

			$score = 0;

			// Check if they have a page title.
			if ($page["title"]) {
				$score += 5;
				// They have a title, let's see if it's unique
				$r = sqlrows(sqlquery("SELECT * FROM bigtree_pages WHERE title = '".sqlescape($page["title"])."' AND id != '".sqlescape($page["id"])."'"));
				if ($r == 0) {
					// They have a unique title
					$score += 5;
				} else {
					$recommendations[] = "Your page title should be unique. ".($r - 1)." other page(s) have the same title.";
				}
				$words = $textStats->word_count($page["title"]);
				$length = mb_strlen($page["title"]);
				if ($words >= 4 && $length <= 72) {
					// Fits the bill!
					$score += 5;
				} else {
					$recommendations[] = "Your page title should be no more than 72 characters and should contain at least 4 words.";
				}
			} else {
				$recommendations[] = "You should enter a page title.";
			}

			// Check for meta description
			if ($page["meta_description"]) {
				$score += 5;
				// They have a meta description, let's see if it's no more than 165 characters.
				if (mb_strlen($page["meta_description"]) <= 165) {
					$score += 5;
				} else {
					$recommendations[] = "Your meta description should be no more than 165 characters. It is currently ".mb_strlen($page["meta_description"])." characters.";
				}
			} else {
				$recommendations[] = "You should enter a meta description.";
			}

			// Check for an H1
			if (!$h1_field || $content[$h1_field]) {
				$score += 10;
			} else {
				$recommendations[] = "You should enter a page header.";
			}
			// Check the content!
			if (!count($body_fields)) {
				// If this template doesn't for some reason have a seo body resource, give the benefit of the doubt.
				$score += 65;
			} else {
				$regular_text = "";
				$stripped_text = "";
				foreach ($body_fields as $field) {
					if (!is_array($content[$field])) {
						$regular_text .= $content[$field]." ";
						$stripped_text .= strip_tags($content[$field])." ";
					}
				}
				// Check to see if there is any content
				if ($stripped_text) {
					$score += 5;
					$words = $textStats->word_count($stripped_text);
					$readability = $textStats->flesch_kincaid_reading_ease($stripped_text);
					if ($readability < 0) {
						$readability = 0;
					}
					$number_of_links = substr_count($regular_text, "<a ");
					$number_of_external_links = substr_count($regular_text, 'href="http://');

					// See if there are at least 300 words.
					if ($words >= 300) {
						$score += 15;
					} else {
						$recommendations[] = "You should enter at least 300 words of page content. You currently have ".$words." word(s).";
					}

					// See if we have any links
					if ($number_of_links) {
						$score += 5;
						// See if we have at least one link per 120 words.
						if (floor($words / 120) <= $number_of_links) {
							$score += 5;
						} else {
							$recommendations[] = "You should have at least one link for every 120 words of page content. You currently have $number_of_links link(s). You should have at least ".floor($words / 120).".";
						}
						// See if we have any external links.
						if ($number_of_external_links) {
							$score += 5;
						} else {
							$recommendations[] = "Having an external link helps build Page Rank.";
						}
					} else {
						$recommendations[] = "You should have at least one link in your content.";
					}

					// Check on our readability score.
					if ($readability >= 90) {
						$score += 20;
					} else {
						$read_score = round(($readability / 90), 2);
						$recommendations[] = "Your readability score is ".($read_score * 100)."%. Using shorter sentences and words with fewer syllables will make your site easier to read by search engines and users.";
						$score += ceil($read_score * 20);
					}
				} else {
					$recommendations[] = "You should enter page content.";
				}

				// Check page freshness
				$updated = strtotime($page["updated_at"]);
				$age = time() - $updated - (60 * 24 * 60 * 60);
				// See how much older it is than 2 months.
				if ($age > 0) {
					$age_score = 10 - floor(2 * ($age / (30 * 24 * 60 * 60)));
					if ($age_score < 0) {
						$age_score = 0;
					}
					$score += $age_score;
					$recommendations[] = "Your content is around ".ceil(2 + ($age / (30 * 24 * 60 * 60)))." months old. Updating your page more frequently will make it rank higher.";
				} else {
					$score += 10;
				}
			}

			$color = "#008000";
			if ($score <= 50) {
				$color = BigTree::colorMesh("#CCAC00", "#FF0000", 100 - (100 * $score / 50));
			} elseif ($score <= 80) {
				$color = BigTree::colorMesh("#008000", "#CCAC00", 100 - (100 * ($score - 50) / 30));
			}

			return ["score" => $score, "recommendations" => $recommendations, "color" => $color];
		}

		public static function getPageIds() {
			$ids = [];
			$q = sqlquery("SELECT id FROM bigtree_pages WHERE archived != 'on' ORDER BY id ASC");
			while ($f = sqlfetch($q)) {
				$ids[] = $f["id"];
			}

			return $ids;
		}

		public static function getPageAdminLinks() {
			global $bigtree;
			$pages = [];
			$q = sqlquery("SELECT * FROM bigtree_pages WHERE REPLACE(resources,'{adminroot}js/embeddable-form.js','') LIKE '%{adminroot}%' OR resources LIKE '%".$bigtree["config"]["admin_root"]."%' OR resources LIKE '%".str_replace($bigtree["config"]["www_root"], "{wwwroot}", $bigtree["config"]["admin_root"])."%'");
			while ($f = sqlfetch($q)) {
				$pages[] = $f;
			}

			return $pages;
		}

	
		/**
		 * Reserved top-level page routes, including the admin path first segment
		 * (mirrors legacy admin constructor append of admin_root).
		 */
		public static function reservedTopLevelRoutes(): array {
			$routes = [
				"ajax",
				"css",
				"feeds",
				"js",
				"sitemap.xml",
				"_preview",
				"_preview-pending",
			];

			if (defined("ADMIN_ROOT") && defined("WWW_ROOT")) {
				$ar = explode("/", str_replace(WWW_ROOT, "", ADMIN_ROOT));

				if (!empty($ar[0]) && !in_array($ar[0], $routes, true)) {
					$routes[] = $ar[0];
				}
			}

			return $routes;
		}
	
		public static function getPageIDForPath($path, $previewing = false) {
			$commands = [];

			// Get any GET variables and hashes and remove them
			$url_parse = parse_url(implode("/", array_values($path)));
			$query_vars = $url_parse["query"] ?? "";
			$hash = $url_parse["fragment"] ?? "";
			$path = !empty($url_parse["path"]) ? explode("/", rtrim($url_parse["path"], "/")) : [];

			if (!$previewing) {
				$publish_at = "AND (publish_at <= NOW() OR publish_at IS NULL) AND (expire_at >= NOW() OR expire_at IS NULL)";
			} else {
				$publish_at = "";
			}

			// See if we have a straight up perfect match to the path.
			$page = SQL::fetch("SELECT id, template FROM bigtree_pages WHERE path = ? AND archived = '' $publish_at", implode("/", $path));

			if ($page) {
				$template = BigTreeJSONDB::get("templates", $page["template"]);

				return [$page["id"], [], $template["routed"] ?? false, $query_vars, $hash];
			}

			// Guess we don't, let's chop off commands until we find a page.
			$x = 0;

			while ($x < count($path)) {
				$x++;
				$commands[] = $path[count($path) - $x];

				// We have additional commands, so we're now making sure the template is also routed, otherwise it's a 404.
				$page = SQL::fetch("SELECT id, template FROM bigtree_pages WHERE path = ? AND archived = '' $publish_at", implode("/", array_slice($path, 0, -1 * $x)));

				if ($page) {
					$template = BigTreeJSONDB::get("templates", $page["template"]);

					if (!empty($template["routed"])) {
						return [$page["id"], array_reverse($commands), "on", $query_vars, $hash];
					}
				}
			}

			return [false, false, false, false, false];
		}
	}
