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
		 * The scalar page fields the assistant can write with update_page — and,
		 * because get_page reads back exactly this list (aiPageDetailFields), the
		 * fields it can read. Keeping one list is the point: the read payload used to
		 * carry six fields against a write surface of fourteen, so "is this page
		 * hidden from search?" or "when does it expire?" could only be answered by
		 * proposing an edit and reading the staged diff's `from` values.
		 *
		 * Resource (template field) editing is deliberately absent — that is
		 * update_page_content's job. `trunk` is deliberately absent too: dev-only and
		 * multi-site-structural.
		 */
		public const AI_PAGE_FIELDS = [
			"nav_title", "title", "meta_description", "meta_keywords", "in_nav", "seo_invisible",
			"template", "route", "publish_at", "expire_at", "external", "new_window",
			"og_title", "og_description", "max_age",
		];

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

			$external = $this->aiNormalizeExternalLink((string)($args["external"] ?? ""));

			if (isset($external["error"])) {

				return $external;
			}

			$external = $external["url"];

			if ($template !== "") {
				if (!BigTreeJSONDB::exists("templates", $template)) {

					return ["error" => "Template \"{$template}\" does not exist."];
				}
			} elseif ($external === "") {
				// No template and no external link. A blank template is only valid for
				// an external link, so default to the same template the admin's "Add
				// page" screen would preselect: the first flexible (non-routed)
				// template, falling back to the first template.
				$template = $this->aiDefaultTemplate();
			}

			$link_error = $this->aiAssertLinkOrTemplate($template, $external);

			if ($link_error !== null) {

				return ["error" => $link_error];
			}

			// Collect the page's content for the template's simple fields. Complex
			// fields (uploads, matrices, relationships) are omitted from the schema and
			// rejected if supplied — the assistant never fabricates a file reference.
			$schema = $this->aiTemplateResourceSchema($template);
			$provided = is_array($args["content"] ?? null) ? $args["content"] : [];

			// An external link renders nothing of its own, so it has no template and no
			// content fields. Supplied content would be silently dropped — say so
			// instead, since the model has clearly misunderstood what it's creating.
			if ($external !== "" && $provided) {

				return ["error" => "An external link is just a navigation entry pointing at another site, so it has "
					. "no content of its own. Drop the content to create the link, or drop the external URL and "
					. "pick a template to create a real page."];
			}

			$sifted = $this->aiSiftResourceContent($schema, $provided, $template);

			if (isset($sifted["error"])) {

				return $sifted;
			}

			$resources = $sifted["data"];

			// "Draft this, marketing reviews it Friday" had no path: the mode was
			// derived purely from rank, so a publisher's approval always went live —
			// the opposite of the framework's own propose-then-approve ethos. REST has
			// carried an explicit publish flag all along. Because the draft path is
			// exactly the "a human completes this" case, the blocked-required-fields
			// refusal relaxes to the same warning an editor gets (it keys off
			// $can_publish below).
			$save_as_draft = !empty($args["save_as_draft"]);
			$can_publish = PermissionService::isPublisher($user, $rank) && !$save_as_draft;
			$blocked = $this->aiRequiredUnsettableResources($template);
			$gate = $this->aiPageCreateGate($template, $resources, $can_publish);

			if ($gate !== null) {

				return ["error" => $gate];
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

			// Drives the dashboard's content-staleness alerts, which get_content_alerts
			// now reads — settable so the model can both see stale content and fix the
			// tracking on it. REST declares it int|min:0; 0 means "never goes stale".
			$max_age = max(0, (int)($args["max_age"] ?? 0));
			$schedule = $this->aiPageSchedule($args);

			if (isset($schedule["error"])) {

				return $schedule;
			}

			// Tagging at create, so "create a page about X and tag it Y" is one
			// proposal. Same permission split as add_tags: attaching an existing tag is
			// editor-level, coining a new one is administrator-only. Names (not ids)
			// are stored and re-checked at approval, exactly as add_tags does.
			$tags = new TagService();
			$tag_names = $tags->aiTagNames($args["tags"] ?? []);
			$new_tags = $tag_names ? $tags->aiNewTagNames($tag_names) : [];

			if ($new_tags && PermissionService::level($user) < 1) {

				return ["denied" => "Only administrators can create new tags. These don't exist yet: "
					. implode(", ", $new_tags) . ". You can still use tags that already exist."];
			}

			$open_graph = [];

			foreach (["og_title" => "title", "og_description" => "description"] as $arg => $key) {
				$value = trim((string)($args[$arg] ?? ""));

				if ($value !== "") {
					$open_graph[$key] = $value;
				}
			}

			$payload = [
				"parent" => $parent,
				"nav_title" => $nav_title,
				"title" => $title,
				"route" => $route,
				"template" => $template,
				"external" => $external,
				"new_window" => $external !== "" && !empty($args["new_window"]),
				"tag_names" => $tag_names,
				"open_graph" => $open_graph,
				"in_nav" => $in_nav,
				"meta_description" => $meta_description,
				"meta_keywords" => $meta_keywords,
				"seo_invisible" => $seo_invisible,
				"max_age" => $max_age,
				"publish_at" => $schedule["publish_at"],
				"expire_at" => $schedule["expire_at"],
				"resources" => $resources,
				"save_as_draft" => $save_as_draft,
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

			if ($max_age > 0) {
				$preview["max_age"] = $max_age;
			}

			if ($schedule["publish_at"] !== null) {
				$preview["publish_at"] = $schedule["publish_at"];
			}

			if ($schedule["expire_at"] !== null) {
				$preview["expire_at"] = $schedule["expire_at"];
			}

			if ($external !== "") {
				$preview["external"] = $external;
				$preview["new_window"] = $payload["new_window"];
			}

			if ($tag_names) {
				$preview["tags"] = $tag_names;
				$preview["new_tags"] = $new_tags;
			}

			foreach (["title" => "og_title", "description" => "og_description"] as $key => $preview_key) {
				if (isset($open_graph[$key])) {
					$preview[$preview_key] = $open_graph[$key];
				}
			}

			$mode_note = $can_publish
				? " It will be published live once you approve."
				: ($save_as_draft
					? " As asked, it will be saved as a draft in the pending queue rather than published — a "
						. "publisher can review and publish it later."
					: " It will be queued as a pending change for a publisher to review.");

			if ($save_as_draft) {
				$preview["save_as_draft"] = true;
			}

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
					// Emptiness is `required`'s business, and the content gates own
					// that; what's left (numeric, email, link) the write path enforces
					// and nothing here used to check.
					"rules" => array_values(array_diff($rules ?: [], ["required"])),
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
		 * Both kinds of rejection used to be a bare `continue`. A non-required complex
		 * resource, or a misspelled resource id, was therefore dropped in silence: the
		 * model believed it had set the value, the user read the model's confirmation,
		 * and the preview quietly omitted it. A typo deserves a correction loop.
		 *
		 * @param array<string,array<string,mixed>> $schema
		 * @param array<string,mixed> $provided
		 * @param string $template The template the content belongs to, for naming complex resources.
		 * @return array<string,mixed> ["data" => array] or ["error" => string]
		 */
		private function aiSiftResourceContent(array $schema, array $provided, string $template = ""): array {
			$data = [];
			$complex = $this->aiComplexTemplateResources($schema, $template);

			foreach ($provided as $id => $value) {
				$id = (string)$id;

				if (!isset($schema[$id])) {
					if (isset($complex[$id])) {

						return ["error" => "Field \"{$id}\" ({$complex[$id]}) can't be set by the assistant — it needs "
							. "the page editor in the admin. Settable fields: " . $this->aiDescribeResourceSchema($schema)];
					}

					return ["error" => "This template has no content field called \"{$id}\". Settable fields: "
						. $this->aiDescribeResourceSchema($schema)];
				}

				if (is_array($value)) {

					return ["error" => "Field \"{$id}\" expects a simple value, not a list or object."];
				}

				if ($schema[$id]["type"] === "checkbox") {
					$data[$id] = !empty($value) && $value !== "false" ? "on" : "";
				} else {
					$data[$id] = is_bool($value) ? ($value ? "1" : "0") : (string)$value;
				}

				$invalid = $this->aiResourceRuleViolation($schema[$id], $data[$id]);

				if ($invalid !== null) {

					return ["error" => $invalid];
				}
			}

			return ["data" => $data];
		}

		/**
		 * Check one sifted resource value against its non-required validation rules,
		 * through the same validator the write path uses so the two can't disagree.
		 * Returns null when the value passes.
		 *
		 * @param array<string,mixed> $field The schema entry.
		 * @param mixed $value
		 */
		private function aiResourceRuleViolation(array $field, $value): ?string {
			$rules = is_array($field["rules"] ?? null) ? $field["rules"] : [];

			if (!$rules || trim((string)$value) === "") {

				return null;
			}

			$rule_string = implode(" ", $rules);

			if (\BigTreeAutoModule::validate($value, $rule_string)) {

				return null;
			}

			$title = (string)($field["title"] ?? $field["id"] ?? "This field");
			// The legacy message opens "This field …"; name the field instead.
			$reason = preg_replace(
				"/^This field /",
				"",
				\BigTreeAutoModule::validationErrorMessage($value, $rule_string)
			);

			return "“{$title}” " . $reason . " \"" . $this->aiPreviewScalarValue($value)
				. "\" would be refused when the page is saved.";
		}

		/**
		 * A template's real resources that aren't in the settable schema, mapped to a
		 * "Title (type)" label — everything the assistant can see exists but cannot
		 * author. Used to tell a complex resource apart from a misspelling.
		 *
		 * @param array<string,array<string,mixed>> $schema
		 * @return array<string,string>
		 */
		private function aiComplexTemplateResources(array $schema, string $template): array {
			$row = $template !== "" ? BigTreeJSONDB::get("templates", $template) : null;

			if (!$row) {

				return [];
			}

			$complex = [];

			foreach ((array)($row["resources"] ?? []) as $resource) {
				$id = (string)($resource["id"] ?? "");

				if ($id === "" || isset($schema[$id])) {

					continue;
				}

				$type = (string)($resource["type"] ?? "");
				$title = (string)($resource["title"] ?? $id);
				$complex[$id] = $type !== "" ? "{$title}, {$type}" : $title;
			}

			return $complex;
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
		 * The data-validity gate for creating a page, shared by staging and approval.
		 *
		 * A required template field with no content mirrors the "Add page" screen's own
		 * required check. A template requiring content the assistant can't author is
		 * refused outright for a publisher (whose approval lands live) but allowed for a
		 * non-publisher (whose write only reaches the pending queue). That verdict
		 * depends on rank, and rank can change during a proposal's 24h life, so it is
		 * re-asked at approval rather than trusted from staging.
		 *
		 * @param array<string,mixed> $resources The sifted content.
		 * @return string|null An error message, or null when the content still passes.
		 */
		private function aiPageCreateGate(string $template, array $resources, bool $can_publish): ?string {
			$schema = $this->aiTemplateResourceSchema($template);
			$missing = $this->aiMissingRequiredResources($schema, $resources);

			if ($missing) {

				return "The “{$template}” template needs content for these required fields before the page "
					. "can be created: " . implode(", ", $missing) . ". Settable fields: "
					. $this->aiDescribeResourceSchema($schema);
			}

			$blocked = $this->aiRequiredUnsettableResources($template);

			if ($blocked && $can_publish) {

				return "The “{$template}” template requires content the assistant can't provide: "
					. implode(", ", $blocked) . ". Create this page in the admin UI instead.";
			}

			return null;
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

			// An explicit "save as draft" forces the pending path however senior the
			// approver is. Stripped from the payload before it's written: everything
			// left in it is replayed onto the page row.
			$save_as_draft = !empty($payload["save_as_draft"]);
			unset($payload["save_as_draft"]);

			$can_publish = PermissionService::isPublisher($user, $rank) && !$save_as_draft;
			$nav_title = (string)($payload["nav_title"] ?? "");
			$template = (string)($payload["template"] ?? "");

			// The template may have been redefined — or the approver's rank raised —
			// since this was staged. Re-ask both questions before writing.
			if ($template !== "" && !BigTreeJSONDB::exists("templates", $template)) {

				return ["mode" => "error", "message" => "The “{$template}” template no longer exists."];
			}

			if ($template !== "") {
				$gate = $this->aiPageCreateGate(
					$template,
					is_array($payload["resources"] ?? null) ? $payload["resources"] : [],
					$can_publish
				);

				if ($gate !== null) {

					return ["mode" => "error", "message" => $gate];
				}
			}

			// Re-checked at approval like every other staged value — the payload sits
			// in the proposal store for up to 24h and is never trusted on the way back
			// out. The create payload spells these fields flat, so the same guard the
			// update path runs over its `changes` map applies directly.
			$invalid = $this->aiPageChangeValueError($payload);

			if ($invalid !== null) {

				return ["mode" => "error", "message" => $invalid];
			}

			// Tags were staged as names. Re-check the admin gate on any still missing —
			// a tag that existed at staging may have been deleted since — then resolve
			// to the ids the write path (and the pending-change replay) expects.
			$tag_names = is_array($payload["tag_names"] ?? null) ? $payload["tag_names"] : [];
			unset($payload["tag_names"]);

			if ($tag_names) {
				$tags = new TagService();

				if ($tags->aiNewTagNames($tag_names) && PermissionService::level($user) < 1) {
					throw new AuthorizationException("Only administrators can create new tags");
				}

				$payload["tags"] = $tags->aiResolveTagIds($tag_names);
			}

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
		 * A page's saved and automatic revisions, newest first, for the
		 * get_page_revisions read tool.
		 *
		 * "Undo what was just done to this page" had no AI path even though restoring
		 * is an ordinary page update underneath. Listing needs only view access, the
		 * same as the admin's own revisions panel.
		 *
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiPageRevisions(int $page_id, int $limit, $user): array {
			$page = $page_id > 0 ? SQL::fetch("SELECT id, nav_title, title FROM bigtree_pages WHERE id = ?", $page_id) : null;

			if (!$page) {

				return ["error" => "Page {$page_id} does not exist."];
			}

			if (!PermissionService::userHasPageAccess($user, $page_id, "v")) {

				return ["denied" => "You do not have permission to view this page."];
			}

			$limit = max(1, min(50, $limit));
			$rows = SQL::fetchAll(
				"SELECT r.id, r.title, r.saved, r.saved_description, r.updated_at, r.author, u.name AS author_name
				 FROM bigtree_page_revisions r
				 LEFT JOIN bigtree_users u ON u.id = r.author
				 WHERE r.page = ? ORDER BY r.updated_at DESC, r.id DESC LIMIT " . $limit,
				$page_id
			);

			$revisions = [];

			foreach ($rows as $row) {
				$revisions[] = [
					"id" => (int)$row["id"],
					"title" => (string)$row["title"],
					// A "saved" revision was deliberately kept by a person and carries a
					// description; the rest are automatic snapshots taken before a write.
					"saved" => Flag::isOn($row["saved"]),
					"description" => (string)($row["saved_description"] ?? ""),
					"updated_at" => $row["updated_at"],
					"author_name" => $row["author_name"] !== null ? (string)$row["author_name"] : null,
				];
			}

			return [
				"page_id" => $page_id,
				"page_title" => trim((string)$page["nav_title"]) ?: trim((string)$page["title"]) ?: "page #{$page_id}",
				"revisions" => $revisions,
			];
		}

		/**
		 * Validate restoring a page revision. Publisher-only, matching the REST route:
		 * a restore overwrites the live page's content columns outright, so unlike an
		 * ordinary edit there is no pending-change form of it.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateRevisionRestore(array $args, $user): array {
			$page_id = (int)($args["page_id"] ?? 0);
			$revision_id = (int)($args["revision_id"] ?? 0);
			$page = $page_id > 0 ? SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $page_id) : null;

			if (!$page) {

				return ["error" => "Page {$page_id} does not exist."];
			}

			if (!PermissionService::userHasPageAccess($user, $page_id, "e")) {

				return ["denied" => "You do not have permission to edit this page."];
			}

			$rank = PermissionService::userPageLevel($user, $page_id);

			// A restore replaces live content wholesale; there's no pending-change
			// equivalent, so an editor can't stage one the way they can an edit.
			if (!PermissionService::isPublisher($user, $rank)) {

				return ["denied" => "Restoring a revision publishes it to the live page immediately, so it needs "
					. "publisher access on this page. Ask a publisher to restore it for you."];
			}

			$revision = $revision_id > 0
				? SQL::fetch("SELECT * FROM bigtree_page_revisions WHERE id = ? AND page = ?", $revision_id, $page_id)
				: null;

			if (!$revision) {

				return ["error" => "Revision {$revision_id} does not belong to page {$page_id}. Use "
					. "get_page_revisions to list the ones that do."];
			}

			$title = trim((string)$page["nav_title"]) ?: trim((string)$page["title"]) ?: "page #{$page_id}";
			$description = trim((string)($revision["saved_description"] ?? ""));
			$diff = [];

			// Show which content columns the restore would actually change, so this
			// isn't approved blind — a revision that differs in nothing is worth
			// seeing as such.
			foreach (self::REVISION_COLUMNS as $column) {
				$from = (string)($page[$column] ?? "");
				$to = (string)($revision[$column] ?? "");

				if ($from !== $to) {
					$diff[$column] = [
						"from" => $this->aiPreviewScalarValue($from),
						"to" => $this->aiPreviewScalarValue($to),
					];
				}
			}

			if (!$diff) {

				return ["error" => "That revision is identical to the page's current content — restoring it would "
					. "change nothing."];
			}

			return [
				"ok" => true,
				"summary" => "Restore “{$title}” to its revision from {$revision["updated_at"]}"
					. ($description !== "" ? " (“{$description}”)" : "")
					. ". This replaces the page's current content live. The current version is snapshotted first, "
					. "so it can be restored back.",
				"preview" => [
					"action" => "restore_page_revision",
					"page_id" => $page_id,
					"page_title" => $title,
					"revision_id" => $revision_id,
					"revision_date" => $revision["updated_at"],
					"changes" => $diff,
					"mode" => "published",
				],
				"payload" => [
					"page_id" => $page_id,
					"revision_id" => $revision_id,
				],
			];
		}

		/**
		 * Execute an approved revision restore. Re-checks publisher access and that the
		 * revision still belongs to the page, then reuses the same
		 * snapshot-then-overwrite the REST restore route does so the restore is itself
		 * reversible.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiRestoreRevision(array $payload, $user): array {
			$page_id = (int)($payload["page_id"] ?? 0);
			$revision_id = (int)($payload["revision_id"] ?? 0);

			if (!PermissionService::userHasPageAccess($user, $page_id, "e")) {
				throw new AuthorizationException("Insufficient page permission to restore a revision (e required)");
			}

			$rank = PermissionService::userPageLevel($user, $page_id);

			if (!PermissionService::isPublisher($user, $rank)) {
				throw new AuthorizationException("Publisher access required to restore a page revision");
			}

			$page = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $page_id);
			$revision = $revision_id > 0
				? SQL::fetch("SELECT * FROM bigtree_page_revisions WHERE id = ? AND page = ?", $revision_id, $page_id)
				: null;

			if (!$page || !$revision) {

				return ["mode" => "error", "message" => "That page or revision no longer exists."];
			}

			// Snapshot the current published state first so the restore can be undone,
			// exactly as the REST route does.
			$this->insertRevisionSnapshot($page_id, $page, (int)$this->aiUserId($user));

			$update = [];

			foreach (self::REVISION_COLUMNS as $column) {
				$update[$column] = $revision[$column];
			}

			$update["last_edited_by"] = $this->aiUserId($user);
			$update["updated_at"] = "NOW()";
			SQL::update("bigtree_pages", $page_id, $update);
			$this->finishPageWrite($page_id, $update, [], [], $page);

			return [
				"mode" => "restored",
				"page_id" => $page_id,
				"revision_id" => $revision_id,
				"title" => trim((string)$page["nav_title"]) ?: ("page #{$page_id}"),
			];
		}

		/**
		 * Score a page's SEO the way the admin's own rating panel does.
		 *
		 * The assistant can edit a page's title, meta description and content but had
		 * no way to answer "how's the SEO on this page?" — so it either guessed from
		 * the raw fields or declined. This is the same computation the REST route and
		 * the legacy admin run, returned verbatim rather than re-derived.
		 *
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiPageSeoRating(int $page_id, $user): array {
			$page = $page_id > 0 ? SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $page_id) : null;

			if (!$page) {

				return ["error" => "Page {$page_id} does not exist."];
			}

			if (!PermissionService::userHasPageAccess($user, $page_id, "v")) {

				return ["denied" => "You do not have permission to view this page."];
			}

			$title = trim((string)$page["nav_title"]) ?: trim((string)$page["title"]) ?: "page #{$page_id}";
			$content = Json::decode($page["resources"] ?? "");
			$seo = self::getPageSEORating($page, is_array($content) ? $content : []);

			// Null means the template couldn't be resolved — an external link, or a
			// template that has since been removed. There is nothing to rate, and
			// saying so beats reporting a fabricated zero.
			if (is_null($seo)) {

				return [
					"page_id" => $page_id,
					"page_title" => $title,
					"available" => false,
					"note" => "This page has no rateable content — it's an external link, or its template no longer "
						. "exists.",
				];
			}

			return [
				"page_id" => $page_id,
				"page_title" => $title,
				"available" => true,
				"score" => (int)$seo["score"],
				"recommendations" => array_values($seo["recommendations"]),
			];
		}

		/**
		 * Validate bookmarking the page's current content as a named revision.
		 *
		 * The assistant could restore a revision but never create one, so there was no
		 * way to say "save where this is now, then rewrite the intro" — the safety net
		 * only existed if someone had already thought to hang it. Publisher-only,
		 * matching the REST route.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateSaveRevision(array $args, $user): array {
			$page_id = (int)($args["page_id"] ?? 0);
			$page = $page_id > 0 ? SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $page_id) : null;

			if (!$page) {

				return ["error" => "Page {$page_id} does not exist."];
			}

			if (!PermissionService::userHasPageAccess($user, $page_id, "e")) {

				return ["denied" => "You do not have permission to edit this page."];
			}

			if (!PermissionService::isPublisher($user, PermissionService::userPageLevel($user, $page_id))) {

				return ["denied" => "Saving a named revision needs publisher access on this page."];
			}

			$description = trim((string)($args["description"] ?? ""));

			// An unnamed revision is stored as an automatic snapshot, which is exactly
			// what this tool exists to be distinguishable from.
			if ($description === "") {

				return ["error" => "A description is required — it's what tells this revision apart from the "
					. "automatic snapshots in the page's history (e.g. \"before the pricing rewrite\")."];
			}

			if (mb_strlen($description) > 255) {

				return ["error" => "That description is too long (255 characters maximum)."];
			}

			$title = trim((string)$page["nav_title"]) ?: trim((string)$page["title"]) ?: "page #{$page_id}";

			return [
				"ok" => true,
				"summary" => "Save the current content of “{$title}” as a named revision (“{$description}”). "
					. "This changes nothing on the live page — it bookmarks what's there now so it can be restored.",
				"preview" => [
					"action" => "save_page_revision",
					"page_id" => $page_id,
					"page_title" => $title,
					"description" => $description,
					"mode" => "published",
				],
				"payload" => [
					"page_id" => $page_id,
					"description" => $description,
				],
			];
		}

		/**
		 * Execute an approved named-revision save. Re-checks publisher access and
		 * reuses the same snapshot helper the REST route does.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiSaveRevision(array $payload, $user): array {
			$page_id = (int)($payload["page_id"] ?? 0);
			$description = trim((string)($payload["description"] ?? ""));

			if (!PermissionService::userHasPageAccess($user, $page_id, "e")) {
				throw new AuthorizationException("Insufficient page permission to save a revision (e required)");
			}

			if (!PermissionService::isPublisher($user, PermissionService::userPageLevel($user, $page_id))) {
				throw new AuthorizationException("Publisher access required to save a page revision");
			}

			$page = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $page_id);

			if (!$page || $description === "") {

				return ["mode" => "error", "message" => "That page no longer exists."];
			}

			$revision_id = $this->insertRevisionSnapshot($page_id, $page, $this->aiUserId($user), $description);

			return [
				"mode" => "saved",
				"page_id" => $page_id,
				"revision_id" => $revision_id,
				"description" => $description,
			];
		}

		/**
		 * The acting user's id, from either the object or array actor shape the AI
		 * seam is called with.
		 *
		 * @param object|array $user
		 */
		private function aiUserId($user): int {

			return (int)(is_object($user) ? ($user->id ?? 0) : ($user["id"] ?? 0));
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
		 * Normalize publish_at/expire_at for an *edit*, where an explicitly empty value
		 * means "clear the schedule" rather than "not supplied".
		 *
		 * The ordering rule is checked against the page's resulting state: moving only
		 * publish_at past an existing expire_at is just as broken as supplying both in
		 * the wrong order.
		 *
		 * @param array<string,mixed> $args
		 * @param array<string,mixed> $page
		 * @return array{error?:string,publish_at?:string,expire_at?:string}
		 */
		private function aiPageScheduleUpdate(array $args, array $page): array {
			$out = [];

			foreach (["publish_at", "expire_at"] as $field) {
				if (!array_key_exists($field, $args)) {
					$out[$field] = (string)($page[$field] ?? "");

					continue;
				}

				$raw = trim((string)$args[$field]);

				if ($raw === "") {
					$out[$field] = "";

					continue;
				}

				$stamp = strtotime($raw);

				if ($stamp === false) {

					return ["error" => "\"{$raw}\" isn't a date I can store for {$field}. Use an explicit date like "
						. "\"2026-08-01\" or \"2026-08-01 09:00:00\"."];
				}

				$out[$field] = date("Y-m-d H:i:s", $stamp);
			}

			if ($out["publish_at"] !== "" && $out["expire_at"] !== "" && $out["expire_at"] <= $out["publish_at"]) {

				return ["error" => "expire_at ({$out["expire_at"]}) must be after publish_at ({$out["publish_at"]})."];
			}

			return $out;
		}

		/**
		 * The scalar Open Graph subset the assistant may set, before and after an edit.
		 *
		 * Only title and description: OG images are file references, which the
		 * assistant never fabricates. syncOpenGraph replaces the whole row, so the
		 * existing record is loaded and merged — otherwise setting a title would wipe
		 * an image someone chose in the admin.
		 *
		 * @param array<string,mixed> $args
		 * @param array<string,mixed> $page
		 * @return array{from:array<string,string>,to:array<string,string>,stored:array<string,mixed>}
		 */
		private function aiPageOpenGraph(array $args, array $page): array {
			$supplied = array_key_exists("og_title", $args) || array_key_exists("og_description", $args);

			// Only read the OG record when an OG field is actually being edited —
			// otherwise every plain page edit would pay for a query it never uses.
			// A draft carries its OG record on the queue row rather than in the OG
			// table — there is no page id to look one up by.
			$stored = $supplied
				? (array_key_exists("ai_open_graph", $page) ? $page["ai_open_graph"] : $this->loadOpenGraph((int)$page["id"]))
				: null;
			$stored = is_array($stored) ? $stored : [];
			$from = [
				"og_title" => (string)($stored["title"] ?? ""),
				"og_description" => (string)($stored["description"] ?? ""),
			];
			$to = $from;

			foreach (["og_title" => "title", "og_description" => "description"] as $arg => $key) {
				if (array_key_exists($arg, $args)) {
					$to[$arg] = trim((string)$args[$arg]);
					$stored[$key] = $to[$arg];
				}
			}

			return ["from" => $from, "to" => $to, "stored" => $stored];
		}

		/**
		 * The read half of AI_PAGE_FIELDS: every scalar update_page can write, plus
		 * the page's current tag names, shaped for get_page's payload.
		 *
		 * Takes an aiResolvePageTarget result rather than a bare row because a draft
		 * carries its Open Graph and tags on the queue row, not in the OG/tag tables —
		 * there is no page id to look either up by.
		 *
		 * @param array<string,mixed> $target
		 * @return array<string,mixed>
		 */
		public function aiPageDetailFields(array $target): array {
			$page = $target["page"];
			$is_pending = !empty($target["is_pending"]);

			if (array_key_exists("ai_open_graph", $page)) {
				$open_graph = is_array($page["ai_open_graph"]) ? $page["ai_open_graph"] : [];
			} else {
				$open_graph = $is_pending ? [] : ($this->loadOpenGraph((int)$page["id"]) ?: []);
			}

			return [
				"nav_title" => Sanitize::decodeEntities((string)$page["nav_title"]),
				"title" => Sanitize::decodeEntities((string)$page["title"]),
				"meta_description" => Sanitize::decodeEntities((string)($page["meta_description"] ?? "")),
				"meta_keywords" => Sanitize::decodeEntities((string)($page["meta_keywords"] ?? "")),
				"in_nav" => Flag::isOn($page["in_nav"] ?? ""),
				"seo_invisible" => Flag::isOn($page["seo_invisible"] ?? ""),
				"template" => (string)($page["template"] ?? ""),
				"route" => (string)($page["route"] ?? ""),
				"publish_at" => (string)($page["publish_at"] ?? ""),
				"expire_at" => (string)($page["expire_at"] ?? ""),
				"external" => (string)($page["external"] ?? ""),
				"new_window" => Flag::isOn($page["new_window"] ?? ""),
				"og_title" => (string)($open_graph["title"] ?? ""),
				"og_description" => (string)($open_graph["description"] ?? ""),
				"max_age" => (int)($page["max_age"] ?? 0),
				"tags" => $this->aiPageTagNames($target),
			];
		}

		/**
		 * A page's current tag names. Nothing listed them before, so the assistant
		 * could attach and remove tags without ever being able to say which a page
		 * already had.
		 *
		 * @param array<string,mixed> $target An aiResolvePageTarget result.
		 * @return list<string>
		 */
		private function aiPageTagNames(array $target): array {
			if (!empty($target["is_pending"])) {
				$stored = Json::decode((string)SQL::fetchSingle(
					"SELECT tags_changes FROM bigtree_pending_changes WHERE id = ?",
					(int)$target["change_id"]
				));
				$ids = array_values(array_map("intval", is_array($stored) ? $stored : []));

				if (!$ids) {

					return [];
				}

				$names = SQL::fetchAllSingle(
					"SELECT tag FROM bigtree_tags WHERE id IN (" . Sanitize::placeholders($ids) . ") ORDER BY tag",
					...$ids
				);

				return array_values(array_map("strval", $names ?: []));
			}

			$names = SQL::fetchAllSingle(
				"SELECT t.tag FROM bigtree_tags_rel r
				 JOIN bigtree_tags t ON t.id = r.tag
				 WHERE r.`table` = 'bigtree_pages' AND r.entry = ? ORDER BY t.tag",
				(int)$target["page_id"]
			);

			return array_values(array_map("strval", $names ?: []));
		}

		/**
		 * Resolve an AI-supplied page id, which may address an unpublished NEW draft
		 * ("p"-prefixed) rather than a live page.
		 *
		 * The entry tools have accepted "p123" since audit #1, for exactly the reason
		 * that applies here: an editor's own create_page lands in the pending queue,
		 * so "actually, change the header on that draft" had no path — the very
		 * scenario that motivated draft addressing in the first place. REST has
		 * addressed page drafts all along (GET/PATCH /pages/pending/{pcid}); this is
		 * the same addressing for the tool seam.
		 *
		 * A draft's permissions are checked against its parent, matching
		 * updatePending's own enforce() — the page itself doesn't exist yet. Callers
		 * do that check themselves, against `parent` when `is_pending`.
		 *
		 * Public because the read side has to address drafts the same way the write
		 * side does: SearchService::getPageDetail resolves get_page's id through this,
		 * so the assistant can read back a draft it is allowed to edit rather than
		 * being told the id doesn't exist.
		 *
		 * @param mixed $raw
		 * @return array{error?:string,is_pending?:bool,page_id?:int,change_id?:int,parent?:int,page?:array<string,mixed>}
		 */
		public function aiResolvePageTarget($raw): array {
			$raw = trim((string)$raw);

			if ($raw === "") {

				return ["error" => "A page id is required."];
			}

			if (strlen($raw) > 1 && $raw[0] === "p" && ctype_digit(substr($raw, 1))) {
				$change_id = (int)substr($raw, 1);
				$change = SQL::fetch(
					"SELECT * FROM bigtree_pending_changes WHERE id = ? AND `table` = 'bigtree_pages' AND type = 'NEW'",
					$change_id
				);

				if (!$change) {

					return ["error" => "Draft {$raw} does not exist. It may already have been published — if so, use "
						. "the live page's numeric id."];
				}

				$changes = Json::decode($change["changes"]);
				$changes = is_array($changes) ? $changes : [];
				$open_graph = Json::decode($change["open_graph_changes"] ?? "");
				$parent = (int)$change["pending_page_parent"];

				return [
					"is_pending" => true,
					"page_id" => 0,
					"change_id" => $change_id,
					"parent" => $parent,
					"page" => $this->aiDraftAsPage($changes, $parent, is_array($open_graph) ? $open_graph : []),
				];
			}

			if (!ctype_digit($raw) || (int)$raw < 1) {

				return ["error" => "\"{$raw}\" isn't a page id. Use the numeric id of a live page, or a \"p\"-prefixed "
					. "id (like \"p12\") for a page that is still an unpublished draft."];
			}

			$page_id = (int)$raw;
			$page = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $page_id);

			if (!$page) {

				return ["error" => "Page {$page_id} does not exist."];
			}

			return [
				"is_pending" => false,
				"page_id" => $page_id,
				"change_id" => 0,
				"parent" => (int)$page["parent"],
				"page" => $page,
			];
		}

		/**
		 * Shape a NEW draft's stored `changes` blob like a live page row, so the
		 * validation path can diff and gate a draft with the same code that handles a
		 * published page. Every column the AI update path reads is defaulted — the
		 * blob only carries what was actually submitted.
		 *
		 * `resources` is re-encoded because the blob stores it as an array while the
		 * live column (and everything that reads it) is JSON.
		 *
		 * @param array<string,mixed> $changes
		 * @param array<string,mixed> $open_graph
		 * @return array<string,mixed>
		 */
		private function aiDraftAsPage(array $changes, int $parent, array $open_graph): array {
			$resources = $changes["resources"] ?? [];

			return [
				"id" => 0,
				"parent" => $parent,
				"nav_title" => (string)($changes["nav_title"] ?? ""),
				"title" => (string)($changes["title"] ?? ""),
				"route" => (string)($changes["route"] ?? ""),
				"meta_description" => (string)($changes["meta_description"] ?? ""),
				"meta_keywords" => (string)($changes["meta_keywords"] ?? ""),
				"in_nav" => (string)($changes["in_nav"] ?? ""),
				"seo_invisible" => (string)($changes["seo_invisible"] ?? ""),
				"new_window" => (string)($changes["new_window"] ?? ""),
				"template" => (string)($changes["template"] ?? ""),
				"external" => (string)($changes["external"] ?? ""),
				"publish_at" => $changes["publish_at"] ?? null,
				"expire_at" => $changes["expire_at"] ?? null,
				"max_age" => (int)($changes["max_age"] ?? 0),
				"resources" => is_string($resources) ? $resources : (string)json_encode($resources),
				// Read by aiPageOpenGraph in place of a loadOpenGraph() lookup: a draft
				// has no page id to look one up by, its OG lives on the change row.
				"ai_open_graph" => $open_graph,
			];
		}

		/**
		 * Amend a NEW page draft in place: merge the approved changes into its stored
		 * blob and re-stamp the queue row. Mirrors stampPendingNew, but merging rather
		 * than replacing, because the assistant only ever supplies the fields it is
		 * actually changing.
		 *
		 * @param array<string,mixed> $changes
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		private function aiAmendPageDraft(int $change_id, array $changes, $user): array {
			$change = SQL::fetch(
				"SELECT * FROM bigtree_pending_changes WHERE id = ? AND `table` = 'bigtree_pages' AND type = 'NEW'",
				$change_id
			);

			if (!$change) {

				return ["mode" => "error", "message" => "That draft no longer exists — it may already have been "
					. "published."];
			}

			$parent = (int)$change["pending_page_parent"];

			if (!PermissionService::userHasPageAccess($user, $parent, "e")) {
				throw new AuthorizationException("Insufficient page permission to edit draft (e required)");
			}

			$stored = Json::decode($change["changes"]);
			$stored = is_array($stored) ? $stored : [];

			// open_graph lives in its own column on the queue row, exactly as it does
			// when the draft is first written.
			$row = ["user" => $this->aiUserId($user), "date" => "NOW()"];

			if (array_key_exists("open_graph", $changes)) {
				$row["open_graph_changes"] = $changes["open_graph"];
				unset($changes["open_graph"]);
			}

			$merged = array_merge($stored, $changes);
			$merged["parent"] = $parent;
			$row["changes"] = $merged;

			SQL::update("bigtree_pending_changes", $change_id, $row);
			$this->allocatePageResources("p".$change_id, (string)($merged["template"] ?? ""), $merged);

			Hooks::fire("page.pending_updated", [
				"pending_change_id" => $change_id, "parent" => $parent, "via" => "ai_assistant",
			]);

			return [
				"mode" => "pending",
				"page_id" => 0,
				"pending_change_id" => $change_id,
				"title" => (string)($merged["nav_title"] ?? "draft"),
			];
		}

		/**
		 * Validate a proposed external link. The assistant may only point a nav entry
		 * at an absolute http(s) URL — anything else (javascript:, data:, a bare word)
		 * would either not work or be a vector.
		 *
		 * @return array{error?:string,url?:string}
		 */
		private function aiNormalizeExternalLink(string $raw): array {
			$url = trim($raw);

			if ($url === "") {

				return ["url" => ""];
			}

			$scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));

			if (!in_array($scheme, ["http", "https"], true) || parse_url($url, PHP_URL_HOST) === null) {

				return ["error" => "\"{$raw}\" isn't a link I can store. Use a full URL starting with http:// or https://."];
			}

			return ["url" => $url];
		}

		/**
		 * A page is either rendered by a template or is a nav entry pointing elsewhere.
		 * A blank template is only legitimate for an external link — that pairing is
		 * the one case the create path's default-template rule must not apply to.
		 *
		 * @return string|null An error message, or null when the pairing is valid.
		 */
		private function aiAssertLinkOrTemplate(string $template, string $external): ?string {
			if ($external !== "" && $template !== "") {

				return "A page is either a normal page with a template or a link to another site, not both. "
					. "Clear the template to make this a link, or clear the external URL to keep it a page.";
			}

			if ($external === "" && $template === "") {

				return "This page needs either a template or an external URL — with neither it would render nothing.";
			}

			return null;
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
			$target = $this->aiResolvePageTarget($args["id"] ?? "");

			if (isset($target["error"])) {

				return $target;
			}

			$page = $target["page"];
			$is_pending = $target["is_pending"];
			$id = $target["page_id"];

			// A draft has no page of its own to hold permissions; its parent is what
			// grants the right to edit it, matching updatePending's own check.
			if (!PermissionService::userHasPageAccess($user, $is_pending ? $target["parent"] : $id, "e")) {

				return ["denied" => $is_pending
					? "You do not have permission to edit drafts under this page."
					: "You do not have permission to edit this page."];
			}

			$editable = self::AI_PAGE_FIELDS;
			$changes = [];
			$diff = [];
			$title_for_error = trim((string)$page["nav_title"]) ?: trim((string)$page["title"]) ?: "page #{$id}";

			// Dates are normalized (and cross-checked against each other, including
			// against whichever bound isn't being changed) before the per-field loop.
			$schedule = $this->aiPageScheduleUpdate($args, $page);

			if (isset($schedule["error"])) {

				return $schedule;
			}

			$open_graph = $this->aiPageOpenGraph($args, $page);

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

				if ($field === "external") {
					$external = $this->aiNormalizeExternalLink((string)$args["external"]);

					if (isset($external["error"])) {

						return $external;
					}

					$args[$field] = $external["url"];
				}

				// nav_title is what the nav, the breadcrumb and the pending-change card
				// all render, and nothing falls back to it — "" is a legitimate-looking
				// diff that leaves a live page labelled by nothing at all. REST's create
				// requires it; so does editing it.
				if ($field === "nav_title" && trim((string)$args[$field]) === "") {

					return ["error" => "A page's navigation title can't be empty — it's what the site's navigation and "
						. "breadcrumbs display. Supply a nav_title, or use in_nav to hide the page from navigation "
						. "instead."];
				}

				// The write path uniquifies the route but never sanitizes it, so "About
				// Us!" would be stored with its space and punctuation intact. Create
				// derives routes through urlify; an edit has to normalize the same way,
				// and the diff then shows what will actually be stored.
				if ($field === "route") {
					$route = BigTreeCMS::urlify(trim((string)$args[$field]));

					if ($route === "") {

						return ["error" => "\"" . trim((string)$args[$field]) . "\" doesn't contain any characters that "
							. "can be used in a URL. Supply a route made of letters, numbers or hyphens."];
					}

					$args[$field] = $route;
				}

				// REST declares max_age as int|min:0; 0 means "never goes stale".
				if ($field === "max_age") {
					$max_age = (int)$args[$field];

					if ($max_age < 0) {

						return ["error" => "max_age is a number of days and can't be negative. Use 0 to stop tracking "
							. "this page's age."];
					}

					$args[$field] = $max_age;
				}

				if (in_array($field, ["in_nav", "seo_invisible", "new_window"], true)) {
					$new = (bool)$args[$field];
					$old = Flag::isOn($page[$field]);
				} elseif ($field === "max_age") {
					$new = (int)$args[$field];
					$old = (int)($page[$field] ?? 0);
				} elseif (in_array($field, ["publish_at", "expire_at"], true)) {
					// "" clears the schedule; the column is nullable, and a null/""
					// mismatch would otherwise read as a change on every edit.
					$new = (string)$schedule[$field];
					$old = (string)($page[$field] ?? "");
				} elseif (in_array($field, ["og_title", "og_description"], true)) {
					$new = (string)$open_graph["to"][$field];
					$old = (string)$open_graph["from"][$field];
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

			// A page is either templated or an external link — never both. Enforce the
			// rule against the page's resulting state, not just what was supplied.
			$link_error = $this->aiAssertLinkOrTemplate(
				array_key_exists("template", $changes) ? (string)$changes["template"] : (string)$page["template"],
				array_key_exists("external", $changes) ? (string)$changes["external"] : (string)$page["external"]
			);

			if ($link_error !== null) {

				return ["error" => $link_error];
			}

			if (!$changes) {

				return ["error" => "No changes were supplied — nothing to update."];
			}

			// og_title/og_description are the assistant's flat spelling of one JSON
			// record; collapse them into the single `open_graph` key the write path
			// (and the pending-change replay) understands.
			if (isset($changes["og_title"]) || isset($changes["og_description"])) {
				unset($changes["og_title"], $changes["og_description"]);
				$changes["open_graph"] = $open_graph["stored"];
			}

			// Nullable datetime columns: "" would store as the zero date.
			foreach (["publish_at", "expire_at"] as $field) {
				if (array_key_exists($field, $changes) && $changes[$field] === "") {
					$changes[$field] = null;
				}
			}

			$rank = PermissionService::userPageLevel($user, $is_pending ? $target["parent"] : $id);
			$save_as_draft = !empty($args["save_as_draft"]);
			$can_publish = PermissionService::isPublisher($user, $rank) && !$save_as_draft;
			$reference = $is_pending ? "p" . $target["change_id"] : (string)$id;
			$title = trim((string)$page["nav_title"]) ?: trim((string)$page["title"])
				?: ($is_pending ? "draft {$reference}" : "page #{$id}");

			// A draft has no live page to publish over: the edit amends the queued
			// change itself, whoever approves it. Saying "published live" there would
			// be a lie, so the note is different.
			if ($is_pending) {
				$mode_note = " It will update the existing draft, which still needs a publisher to approve it.";
			} else {
				$mode_note = $can_publish
					? " It will be published live once you approve."
					: ($save_as_draft
						? " As asked, it will be queued as a pending change rather than published — the live page "
							. "is untouched until a publisher approves it."
						: " It will be queued as a pending change for a publisher to review.");
			}

			$summary = ($is_pending ? "Update draft “{$title}”." : "Update page “{$title}”.") . $mode_note;

			$preview = [
				"page_id" => $id,
				"page_reference" => $reference,
				"page_title" => $title,
				"is_draft" => $is_pending,
				"changes" => $diff,
				"mode" => $is_pending ? "pending" : ($can_publish ? "published" : "pending"),
			];

			if ($save_as_draft && !$is_pending) {
				$preview["save_as_draft"] = true;
			}

			return [
				"ok" => true,
				"summary" => $summary,
				"preview" => $preview,
				"payload" => [
					"id" => $reference,
					"changes" => $changes,
					"save_as_draft" => $save_as_draft,
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
			$target = $this->aiResolvePageTarget($args["id"] ?? "");

			if (isset($target["error"])) {

				return $target;
			}

			$page = $target["page"];
			$is_pending = $target["is_pending"];
			$id = $target["page_id"];
			$reference = $is_pending ? "p" . $target["change_id"] : (string)$id;

			if (!PermissionService::userHasPageAccess($user, $is_pending ? $target["parent"] : $id, "e")) {

				return ["denied" => $is_pending
					? "You do not have permission to edit drafts under this page."
					: "You do not have permission to edit this page."];
			}

			$title = trim((string)$page["nav_title"]) ?: trim((string)$page["title"])
				?: ($is_pending ? "draft {$reference}" : "page #{$id}");
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

			$sifted = $this->aiSiftResourceContent($schema, $provided, $template);

			if (isset($sifted["error"])) {

				return $sifted;
			}

			$changed = $sifted["data"];

			if (!$changed) {

				return ["error" => "None of the supplied fields can be set by the assistant on the “{$template}” "
					. "template. Settable fields: " . $this->aiDescribeResourceSchema($schema)];
			}

			$rank = PermissionService::userPageLevel($user, $is_pending ? $target["parent"] : $id);
			$save_as_draft = !empty($args["save_as_draft"]);
			$can_publish = PermissionService::isPublisher($user, $rank) && !$save_as_draft;
			$merged = $this->aiMergePageContent($page, $changed, $template, $title, $can_publish);

			if (isset($merged["error"])) {

				return $merged;
			}

			$existing = $merged["existing"];
			$blocked = $merged["blocked"];
			$diff = [];

			foreach ($changed as $field_id => $value) {
				$diff[] = [
					"column" => $field_id,
					"title" => (string)($schema[$field_id]["title"] ?? $field_id),
					"from" => $this->aiPreviewScalarValue($existing[$field_id] ?? ""),
					"to" => $this->aiPreviewScalarValue($value),
				];
			}

			// A draft has no live page to publish over, so its edit amends the queued
			// change itself regardless of who approves it.
			if ($is_pending) {
				$mode_note = " It will update the existing draft, which still needs a publisher to approve it.";
			} else {
				$mode_note = $can_publish
					? " It will be published live once you approve."
					: ($save_as_draft
						? " As asked, it will be queued as a pending change rather than published — the live page "
							. "is untouched until a publisher approves it."
						: " It will be queued as a pending change for a publisher to review.");
			}

			// A non-publisher is allowed to stage a template switch that strands
			// required content, because the result only ever lands in the pending
			// queue — but say so plainly, the way the entry tools do, so neither the
			// approver nor the publisher working the queue is surprised by a page
			// that can't go live as-is.
			if ($blocked) {
				$mode_note .= " Note that " . implode(", ", $blocked)
					. " " . (count($blocked) === 1 ? "is required but cannot" : "are required but cannot")
					. " be set by the assistant — a publisher must fill "
					. (count($blocked) === 1 ? "it" : "them") . " in before this page can go live.";
			}

			$switch_note = $template !== $current_template
				? " The page will also switch from the “{$current_template}” template to “{$template}”."
				: "";

			// Store only the fields the assistant is changing, not the merged blob: the
			// merge is redone at approval against whatever the page holds then, so an
			// edit made during the proposal's TTL isn't reverted. See aiUpdatePageContent.
			$payload = [
				"id" => $reference,
				"content" => $changed,
				"save_as_draft" => $save_as_draft,
			];

			if ($template !== $current_template) {
				$payload["template"] = $template;
			}

			$preview = [
				"action" => "update_page_content",
				"page_id" => $id,
				"page_reference" => $reference,
				"page_title" => $title,
				"is_draft" => $is_pending,
				"template" => $template,
				"template_changed" => $template !== $current_template,
				"fields" => $diff,
				"mode" => $is_pending ? "pending" : ($can_publish ? "published" : "pending"),
			];

			if ($blocked) {
				$preview["incomplete_required"] = $blocked;
			}

			if ($save_as_draft && !$is_pending) {
				$preview["save_as_draft"] = true;
			}

			return [
				"ok" => true,
				"summary" => "Update the content of “{$title}” (" . count($changed) . " field"
					. (count($changed) === 1 ? "" : "s") . ")." . $switch_note . $mode_note,
				"preview" => $preview,
				"payload" => $payload,
			];
		}

		/**
		 * Merge an assistant's changed content fields onto a page's current resources
		 * and re-run the content-validity gates on the result. Shared by staging and
		 * approval so both see the same rules; approval passes the page as it stands at
		 * approval time, which is the whole point of re-merging there.
		 *
		 * Returns ["error" => string] when the merged result isn't valid, otherwise the
		 * merged resources, the pre-merge values (for diffing) and any required fields
		 * left empty that the assistant cannot author.
		 *
		 * @param array<string,mixed> $page     The page row.
		 * @param array<string,mixed> $changed  Sifted, settable fields only.
		 * @return array{error?:string,resources?:array<string,mixed>,existing?:array<string,mixed>,blocked?:list<string>}
		 */
		private function aiMergePageContent(array $page, array $changed, string $template, string $title, bool $can_publish): array {
			$current_template = (string)$page["template"];
			$schema = $this->aiTemplateResourceSchema($template);
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

			$blocked = [];

			// A template switch can still strand required content the assistant can't
			// author — allow it only when the merged result actually satisfies it.
			if ($template !== $current_template) {
				$blocked = $this->aiRequiredUnsettableResources($template, $resources);

				if ($blocked && $can_publish) {

					return ["error" => "Switching “{$title}” to the “{$template}” template would leave required "
						. "content empty: " . implode(", ", $blocked) . ". Make this change in the admin UI."];
				}
			}

			return ["resources" => $resources, "existing" => $existing, "blocked" => $blocked];
		}

		/**
		 * Execute an approved page-content edit. Re-checks edit access, re-merges the
		 * proposal's changed fields onto the page's *current* resources, re-runs the
		 * content gates on the result, then reuses the same performUpdate /
		 * pending-change split every other page write uses.
		 *
		 * The re-merge matters because proposals live up to 24h: merging at staging time
		 * and writing the snapshot here would silently revert any edit made to the page
		 * in between — including fields the assistant never touched.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiUpdatePageContent(array $payload, $user): array {
			$reference = trim((string)($payload["id"] ?? ""));
			$is_pending = strlen($reference) > 1 && $reference[0] === "p" && ctype_digit(substr($reference, 1));
			$id = $is_pending ? 0 : (int)$reference;

			// A draft's content is re-merged against the queued change as it stands now,
			// for the same reason a live page's is: the draft may have been edited in
			// the admin during the proposal's life.
			if ($is_pending) {
				$target = $this->aiResolvePageTarget($reference);

				if (isset($target["error"])) {

					return ["mode" => "error", "message" => $target["error"]];
				}

				$page = $target["page"];

				if (!PermissionService::userHasPageAccess($user, $target["parent"], "e")) {
					throw new AuthorizationException("Insufficient page permission to edit draft (e required)");
				}
			} else {
				if (!PermissionService::userHasPageAccess($user, $id, "e")) {
					throw new AuthorizationException("Insufficient page permission to edit page (e required)");
				}

				$page = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $id);
			}

			if (!$page) {

				return ["mode" => "error", "message" => "Page no longer exists."];
			}

			$rank = PermissionService::userPageLevel($user, $is_pending ? $target["parent"] : $id);
			// An explicit "save as draft" forces the pending path however senior the
			// approver is (and relaxes the blocked-required gate inside the merge, the
			// same way it does for an editor).
			$can_publish = PermissionService::isPublisher($user, $rank) && empty($payload["save_as_draft"]);
			$title = trim((string)$page["nav_title"]) ?: ($is_pending ? "draft {$reference}" : "page #{$id}");
			$template = !empty($payload["template"]) ? (string)$payload["template"] : (string)$page["template"];
			$changed = is_array($payload["content"] ?? null) ? $payload["content"] : [];

			// The template may have been redefined, or the page re-templated, since
			// staging; re-check it still exists before merging against its schema.
			if ($template !== (string)$page["template"] && !BigTreeJSONDB::exists("templates", $template)) {

				return ["mode" => "error", "message" => "The “{$template}” template no longer exists."];
			}

			$merged = $this->aiMergePageContent($page, $changed, $template, $title, $can_publish);

			if (isset($merged["error"])) {

				return ["mode" => "error", "message" => $merged["error"]];
			}

			$changes = ["resources" => $merged["resources"]];

			if ($template !== (string)$page["template"]) {
				$changes["template"] = $template;
			}

			if ($is_pending) {

				return $this->aiAmendPageDraft((int)substr($reference, 1), $changes, $user);
			}

			if (!$can_publish) {
				$pending_id = $this->writePendingPageChange($user, "EDIT", $id, $changes);

				Hooks::fire("page.pending_updated", [
					"id" => $id, "pending_change_id" => (int)$pending_id, "via" => "ai_assistant",
				]);

				return ["mode" => "pending", "page_id" => $id, "title" => $title, "pending_change_id" => (int)$pending_id];
			}

			$this->performUpdate($id, $page, $changes, $user);

			return ["mode" => "published", "page_id" => $id, "title" => $title];
		}

		/**
		 * Approval-time re-check of the staged page field values that carry their own
		 * validity rule, shared by the live and draft branches of aiUpdatePage.
		 *
		 * Everything a proposal stages is re-asked at approval rather than trusted
		 * from staging — the payload sits in the store for up to 24h. These two are
		 * the values whose rule is about the value itself rather than about the
		 * world around it (permission, template existence, tag existence), so they
		 * have nowhere else to be re-checked.
		 *
		 * @param array<string,mixed> $changes
		 * @return string|null An error message, or null when the changes still pass.
		 */
		private function aiPageChangeValueError(array $changes): ?string {
			if (array_key_exists("max_age", $changes) && (int)$changes["max_age"] < 0) {

				return "max_age is a number of days and can't be negative.";
			}

			if (array_key_exists("nav_title", $changes) && trim((string)$changes["nav_title"]) === "") {

				return "A page's navigation title can't be empty — it's what the site's navigation and breadcrumbs "
					. "display.";
			}

			return null;
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
			$reference = trim((string)($payload["id"] ?? ""));
			$changes = is_array($payload["changes"] ?? null) ? $payload["changes"] : [];

			// Before the draft split, so both paths get it: the stored payload is
			// never trusted on the way back out, whichever branch consumes it.
			$invalid = $this->aiPageChangeValueError($changes);

			if ($invalid !== null) {

				return ["mode" => "error", "message" => $invalid];
			}

			// A draft edit amends the queued change in place — there is no live page to
			// publish over, so the publisher/editor split below doesn't apply.
			if (strlen($reference) > 1 && $reference[0] === "p" && ctype_digit(substr($reference, 1))) {

				return $this->aiAmendPageDraft((int)substr($reference, 1), $changes, $user);
			}

			$id = (int)$reference;

			if (!PermissionService::userHasPageAccess($user, $id, "e")) {
				throw new AuthorizationException("Insufficient page permission to edit page (e required)");
			}

			$page = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $id);

			if (!$page) {

				return ["mode" => "error", "message" => "Page no longer exists."];
			}

			$rank = PermissionService::userPageLevel($user, $id);
			// An explicit "save as draft" forces the pending path however senior the
			// approver is.
			$can_publish = PermissionService::isPublisher($user, $rank) && empty($payload["save_as_draft"]);
			$title = trim((string)$page["nav_title"]) ?: ("page #{$id}");

			// The template-switch gap check ran at staging only. A template redefined
			// during the proposal's 24h life — or a page whose content changed since —
			// could now be switched into a broken state, so re-ask before writing.
			$template = (string)($changes["template"] ?? "");

			if ($template !== "" && $template !== (string)$page["template"]) {
				if (!BigTreeJSONDB::exists("templates", $template)) {

					return ["mode" => "error", "message" => "The “{$template}” template no longer exists."];
				}

				$unmet = $this->aiTemplateSwitchGaps($template, $page);

				if ($unmet) {

					return ["mode" => "error", "message" => "Switching “{$title}” to the “{$template}” template would now "
						. "leave its required content empty: " . implode(", ", $unmet) . "."];
				}
			}

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

			// array_key_exists, not isset: an explicit null is how a caller clears a
			// schedule, and the columns are nullable.
			if (array_key_exists("publish_at", $d)) {
				$update["publish_at"] = $d["publish_at"] !== "" ? $d["publish_at"] : null;
			}

			if (array_key_exists("expire_at", $d)) {
				$update["expire_at"] = $d["expire_at"] !== "" ? $d["expire_at"] : null;
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
