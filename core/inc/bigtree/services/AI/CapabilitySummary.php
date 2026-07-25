<?php
	namespace BigTree\Services\AI;

	/**
	 * Renders a user's effective admin capabilities into (a) a structured fact map
	 * and (b) prompt-ready text. This is what makes the assistant permissions-aware
	 * rather than permissions-blocked: the model is told up front what the user can
	 * and cannot do, so it can explain limits and offer the path that works instead
	 * of blindly calling a tool that will be denied.
	 *
	 * Generalizes the ad-hoc can_users / can_tags flags in SearchService's search
	 * prompt. Level-derived capabilities live here; object-scoped capabilities
	 * (which page subtrees a user may write to, etc.) are layered on by the tools
	 * that need them.
	 *
	 * Levels: 0 editor, 1 administrator, 2 developer.
	 */
	class CapabilitySummary {
		/**
		 * @param object|array $user
		 */
		public static function level($user): int {
			if (is_object($user)) {
				return (int)($user->level ?? 0);
			}

			if (is_array($user)) {
				return (int)($user["level"] ?? 0);
			}

			return 0;
		}

		public static function roleLabel(int $level): string {
			if ($level >= 2) {
				return "developer";
			}

			if ($level >= 1) {
				return "administrator";
			}

			return "editor";
		}

		/**
		 * Structured capability facts. Keep keys stable — tests and the SPA read them.
		 *
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public static function forUser($user): array {
			$level = self::level($user);

			return [
				"level" => $level,
				"role" => self::roleLabel($level),
				"is_administrator" => $level >= 1,
				"is_developer" => $level >= 2,
				"can_manage_users" => $level >= 1,
				// Tagging is split: attaching a tag that already exists is something
				// any editor can do (matching the page editor), while coining a new
				// one grows the site's shared vocabulary and stays administrator-only.
				//
				// "Manage" here means create, merge and rename — deleting a tag record
				// is still admin-UI-only, and outOfScope() says so, because this key
				// read as full management back when the catalog only offered attach
				// and detach.
				"can_manage_tags" => $level >= 1,
				"can_create_tags" => $level >= 1,
				"can_attach_tags" => true,
				"can_manage_settings" => $level >= 1,
				"can_manage_templates" => $level >= 2,
				// get_my_capabilities' own description promises callouts; the map never
				// had a key for them, so "can you edit callouts?" had no answer in the
				// one place built to answer it.
				"can_manage_callouts" => $level >= 2,
				"can_manage_modules" => $level >= 2,
				// create_redirect is administrator-gated like the rest, but the map the
				// SPA and the tests read had no key for it at all.
				"can_create_redirects" => $level >= 1,
			];
		}

		/**
		 * Things the assistant cannot do at ANY permission level, each paired with
		 * where in the admin the user should go instead.
		 *
		 * forUser() answers "what may this user do?"; this answers the different
		 * question "what can the assistant do at all?" — and the model had no source
		 * for it. It discovered each wall by failing at it mid-conversation, or worse
		 * improvised a workaround. Naming them up front turns every gap the catalog
		 * deliberately doesn't fill into a good answer instead of a failure.
		 *
		 * Keep this list honest: an entry here is a promise the catalog doesn't cover
		 * it. Anything that gains a tool must be removed in the same change.
		 *
		 * @return array<string,string> Capability => where to do it instead.
		 */
		public static function outOfScope(): array {

			return [
				"Uploading or managing files, images and video" =>
					"the Files section, or the upload field on the page or entry itself",
				"Managing resource folders" => "the Files section",
				"Changing user levels, permissions or passwords" =>
					"Users, or the user's own profile screen for their password",
				// The line above reads as being about privileges; authentication
				// credentials are a separate category and the assistant declines all
				// of it explicitly.
				"Managing two-factor authentication or passkeys" =>
					"the user's own profile screen — the assistant never touches authentication credentials",
				"Deleting users, templates, callouts, modules or settings" =>
					"the relevant Developer or Users screen",
				// "Redefining" covers the level-2 definition edit SettingService::update
				// accepts — name, description, type, options, locked, encrypted, even the
				// setting's own id. update_setting writes the *value* only, and there is
				// no tool for the definition: retyping a setting reinterprets every
				// stored value with no migration, and toggling `encrypted` rewrites the
				// column. Without this word the wall was undiscoverable (audit #9 B2).
				"Creating, deleting or redefining settings (changing a setting's name, type, options or "
					. "encryption)" => "Developer → Settings — the assistant can change a setting's value, "
						. "but not what the setting is",
				"Reading or writing encrypted settings" =>
					"Developer → Settings — encrypted values are never exposed to the assistant",
				"Duplicating or reordering pages" => "the page tree in Pages",
				"Reordering module entries" => "the module's landing view",
				"Reordering templates, callouts or modules" =>
					"the relevant Developer screen — the assistant can create and edit these, but not reorder them",
				// Group-based permissions belong on this line rather than under user
				// permissions above: it is part of the module's own definition, and
				// create_module/update_module deliberately can't express it — turning it
				// on is several interdependent choices, and getting them wrong hides
				// every existing entry from every editor scoped to a group.
				"Editing a module's tables, forms, views or actions, or its group-based permissions" =>
					"Developer → Modules → Module Designer",
				// The line above is about *editing* a report's definition and says
				// nothing about running one, so "export the events module to CSV" hit
				// no tool and no wall — and the model improvised a five-row
				// search_module_entries sweep and presented it as the report.
				"Running or exporting a module's reports" =>
					"the module's Reports action in the admin — the assistant can list entries with "
						. "list_module_entries, but cannot run or export a report",
				// create_module makes a bare record; the table/columns/forms/views are
				// DDL with heavy shape-guessing, which every prior audit declined.
				"Creating a module's database table, forms and views (scaffolding)" =>
					"Developer → Modules → Module Designer — the assistant can create the module record, but "
						. "cannot build its table or screens",
				"Managing a module's embedded forms" => "Developer → Modules → Module Designer",
				"Changing a module's route" => "Developer → Modules",
				// aiGetTemplate returns both, and POST /templates accepts both, but
				// aiCreateTemplate hardcodes them — read-only for values writable
				// elsewhere, with nothing saying so.
				"Binding a template to a module, or configuring its publish hooks" =>
					"Developer → Templates — the assistant can create and edit a template's fields, but not its "
						. "module binding or hooks",
				"Installing, updating or removing extensions" => "Developer → Extensions",
				// The assistant can't take, hold or release a lock — but it does read
				// them, and says so on the proposal when someone else is holding one.
				// The old line ("the lock banner on the item being edited") read as if
				// locks were simply none of its business, which stopped being true.
				"Taking over or releasing another user's content lock" =>
					"the lock banner on the item being edited — the assistant will tell you when someone else has "
						. "an item open, but approving is what takes it over",
				"Editing complex fields — uploads, matrices, relationships, callouts on a page" =>
					"the page or entry editor; the assistant only sets simple text-like fields",
				// Deleting a page is a guaranteed ask ("delete the old pricing page") and
				// archiving is the reversible answer to it, so the line steers rather
				// than just refusing.
				"Deleting a page" =>
					"the page tree in Pages — the assistant can archive a page instead, which takes it off the "
						. "site reversibly",
				"Deleting tags" => "Settings → Tags — the assistant can merge or rename tags, but not delete them",
				"Editing 404 monitoring, or removing redirects" =>
					"Developer → 404s — the assistant can create a redirect but not manage the log",
				"Creating, editing or deleting feeds" => "Developer → Feeds",
				"Creating or editing custom field types" => "Developer → Field Types",
				"Reading or sending internal messages" => "the Messages section",
				"Renaming, reordering or deleting module and callout groups" =>
					"Developer → Modules and Developer → Callouts — the assistant can create a group, but not "
						. "change one afterwards",
				// Group *membership* is a different thing from the group record: the
				// assistant can file a callout in a group at create or update, and can
				// move a module between groups with update_module, but cannot
				// rearrange a group's contents. Listing the module move here as
				// out-of-scope contradicted update_module's own `group` argument — and
				// every decline line is spliced into the system prompt beside "do not
				// improvise a workaround", so the contradiction was load-bearing.
				"Reordering the callouts inside a group" =>
					"Developer → Callouts — the assistant can fill a group when it creates one, and move a callout "
						. "into a group with create_callout or update_callout, but not reorder a group's contents",
				"System maintenance — clearing caches, backups, upgrades, security policy, IP bans" =>
					"Developer → System",
				"Configuring integrations — email, geocoding, cloud storage, analytics, payments, media presets, "
					. "and the AI assistant's own settings" => "Developer → System",
				"Running integrity scans, or configuring analytics" =>
					"the Dashboard — the assistant can list stale content with get_content_alerts, but not scan "
						. "the site or set analytics up",
				"Deleting a page revision" =>
					"the page's revisions panel — the assistant can list, save and restore revisions, but not "
						. "delete one",
				// `trunk` is deliberately absent from every page write tool (dev-only,
				// multi-site-structural): setting it restructures multi-site routing and
				// invalidates the path cache. The page tree does surface which pages are
				// sites (a `trunk` flag) so the assistant can reason about them, but it
				// cannot set or clear one.
				"Making a page a site trunk, or changing the multi-site structure" =>
					"Developer → Pages — the assistant can see which pages are site trunks but cannot set or move one",
				// Not a route family, so AIRouteFamilyContractTest structurally cannot
				// find this wall (audit #9's "running a report" was the first of the
				// kind). Every mutating tool targets exactly one record: there is no
				// batch argument, no multi-target proposal, and no grouping in the
				// proposal store. The only faithful execution of "all forty event pages"
				// is forty tool calls producing forty cards, which the bounded agent loop
				// cuts off part-way through with some pages done, some not, and no way to
				// report which. The line steers rather than just refusing, the way the
				// page-delete line does (audit #10 C4).
				"Applying the same change to many records at once" =>
					"the module's own bulk actions in the admin — the assistant proposes one change at a time, so "
						. "ask for the specific pages or entries you want changed",
			];
		}

		/**
		 * The out-of-scope list as prompt lines.
		 *
		 * @return list<string>
		 */
		public static function outOfScopeLines(): array {
			$lines = [];

			foreach (self::outOfScope() as $capability => $where) {
				$lines[] = "- " . $capability . " — point the user to " . $where . ".";
			}

			return $lines;
		}

		/**
		 * Prompt-ready lines describing the user's role and hard limits, for
		 * inclusion in an assistant system prompt.
		 *
		 * @param object|array $user
		 */
		public static function promptText($user): string {
			$caps = self::forUser($user);
			$lines = [];
			$lines[] = "The current user is a BigTree " . $caps["role"] . " (permission level " . $caps["level"] . ").";

			if ($caps["is_developer"]) {
				$lines[] = "They may manage developer resources (templates, modules, callouts), settings, tags, and users, in addition to content.";
			} elseif ($caps["is_administrator"]) {
				$lines[] = "They may manage settings, tags, and users, and content they have page/module permission for. They CANNOT create or edit templates, modules, or callouts — that requires a developer.";
			} else {
				$lines[] = "They are a content editor. They may work with pages and module entries they have permission for, including adding and removing tags that already exist. They CANNOT create new tags, or manage users, settings, templates, modules, or callouts.";
			}

			$lines[] = "Never claim to have done something the user lacks permission for. If an action needs a higher role, say so plainly and offer an alternative (e.g. saving a draft/pending change, or asking an administrator).";

			return implode("\n", $lines);
		}
	}
