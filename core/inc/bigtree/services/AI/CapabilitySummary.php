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

		/**
		 * @param object|array $user
		 */
		public static function userId($user): int {

			return (int)self::field($user, "id");
		}

		/**
		 * How to name the signed-in user in prose: their name, falling back to their
		 * email, falling back to nothing at all.
		 *
		 * @param object|array $user
		 */
		public static function userLabel($user): string {
			$name = self::field($user, "name");

			return $name !== "" ? $name : self::field($user, "email");
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
				// Audit #14 B2: the assistant knew the caller's *role* and not who they
				// were. "Which drafts are mine", "did I make that change", "what am I
				// subscribed to", "use my name in the byline" were all unanswerable, or
				// answerable only by guessing — and search_users is administrator-gated
				// (audit #1), so an editor could not even look themselves up. This is the
				// caller's own record, so there is no new exposure: it is the same data
				// GET /users/me returns at level 0 today. The positive control was
				// PendingChangeService::aiPresentPendingRow, which has computed a
				// per-row `mine` flag server-side all along — the seam-level pattern for
				// "the caller" existed and was never generalized to an identity read.
				"user_id" => self::userId($user),
				"name" => self::field($user, "name"),
				"email" => self::field($user, "email"),
				// Named alongside the identity rather than under the clock: it is what
				// the user's own digest and alert emails are rendered in, and it is one
				// of the fields they may now edit themselves (audit #14 B1).
				"timezone" => self::field($user, "timezone"),
				"level" => $level,
				"role" => self::roleLabel($level),
				"is_administrator" => $level >= 1,
				"is_developer" => $level >= 2,
				"can_manage_users" => $level >= 1,
				// Audit #14 B1. `PATCH /users/{id}` has always been
				// ["any" => [["level" => 1], ["self" => "id"]]] and the Profile screen is
				// that route, so every editor can change their own name, company,
				// timezone and notification preferences today — while the assistant told
				// them to ask an administrator for something they can do in two clicks.
				// Not a level flag: it is true for everyone, and what varies is *whose*
				// record, which is user_id above.
				"can_edit_own_profile" => true,
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
				// Audit #11 C1: building a new module end to end (table, form, view) is
				// a capability now, distinct from restructuring one that already exists,
				// which stays admin-UI-only at every level.
				"can_scaffold_modules" => $level >= 2,
				// create_redirect is administrator-gated like the rest, but the map the
				// SPA and the tests read had no key for it at all.
				"can_create_redirects" => $level >= 1,
				// Audit #11 B1. Two different answers that used to be one: the assistant
				// can point a reference field at a file that already exists (a lookup
				// against a library it can already enumerate), and cannot create one.
				"can_attach_existing_files" => true,
				"can_upload_files" => false,
				// Audit #11 B2, and the same split one step further out: relating an
				// entry to rows that already exist is a lookup against ids the
				// assistant reads from list_module_entries. Building the *structure*
				// those rows live in — a matrix, a callout, a gallery — is not.
				"can_relate_entries" => true,
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
				// Narrowed in audit #11 B1: the assistant still cannot put a file *into*
				// the library, but it can point a reference field at one that is already
				// there, which is what "attach the logo" actually asks for.
				"Uploading files, images and video, or managing resources" =>
					"the Files section, or the upload field on the page or entry itself — the assistant can "
						. "attach a file that is already in the Files library to an image, file or video "
						. "reference field, but cannot upload a new one",
				"Managing resource folders" => "the Files section",
				"Changing user levels, permissions or passwords" =>
					"Users, or the user's own profile screen for their password",
				// The line above reads as being about privileges; authentication
				// credentials are a separate category and the assistant declines all
				// of it explicitly.
				"Managing two-factor authentication or passkeys" =>
					"the user's own profile screen — the assistant never touches authentication credentials",
				// Audit #14 B1/D3. The self path relaxed everything else on the profile
				// to any level, deliberately excluding this one: `PATCH /users/{id}`
				// allows a self email change, but the email address IS the sign-in
				// identity, and changing it from a chat card is a different risk class
				// from changing a timezone. Declared rather than discovered — the wall
				// only works if the model knows it is there.
				"Changing your own email address (an administrator can change another user's)" =>
					"the user's own profile screen — the assistant can edit the rest of your profile, but your email "
						. "address is your sign-in identity and is changed there",
				// Not a route family, so AIRouteFamilyContractTest structurally can't
				// find this one either. DebugEmulator is a real developer feature ("see
				// the admin as this user"), so "log in as Jane and check" is a plausible
				// ask that used to hit no tool and no wall — the nearest line was about
				// levels and permissions, which is a different thing (audit #14 C3).
				"Signing in as, or emulating, another user" =>
					"Developer → Debug → Emulate User — the assistant always acts as you, and can never act as "
						. "somebody else",
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
				// Scoped to an *existing* module since audit #11 C1: building a new one
				// is scaffold_module's job now — the whole plan on a proposal card,
				// developer-only, no DDL until it is approved. Restructuring a module
				// that already holds entries is ALTER TABLE against real rows, and
				// stays where it was.
				"Editing an existing module's tables, forms, views or actions, or its group-based permissions" =>
					"Developer → Modules → Module Designer — the assistant can build a new module end to end with "
						. "scaffold_module, but not restructure one that already exists",
				// The line above is about *editing* a report's definition and says
				// nothing about running one, so "export the events module to CSV" hit
				// no tool and no wall — and the model improvised a five-row
				// search_module_entries sweep and presented it as the report.
				"Running or exporting a module's reports" =>
					"the module's Reports action in the admin — the assistant can list entries with "
						. "list_module_entries, but cannot run or export a report",
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
				// Split in audit #11 B1/B2. "Complex fields" used to swallow reference
				// and relationship fields, whose stored values are nothing more than a
				// bigtree_resources id and a list of entry ids the assistant can
				// already look up — so on any content model with a required photo the
				// best it could produce was a draft a human had to finish. Attaching a
				// file that exists, or relating rows that exist, is a lookup; creating
				// the file is still an upload, and still declined above.
				"Editing composite fields — matrices, callouts, media galleries" =>
					"the page or entry editor; the assistant sets text-like fields, can attach a file that is "
						. "already in the Files library and can relate entries that already exist, but can't "
						. "build structured content",
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
			$label = self::userLabel($user);

			// Audit #14 B2: the role was named and the person was not, so "am I
			// subscribed to that page", "which of these drafts are mine" and "sign the
			// post with my name" had nothing to resolve "me" against — and the one tool
			// that could have looked it up, search_users, is administrator-gated.
			$lines[] = "The signed-in user is " . ($label !== "" ? "“" . $label . "”" : "the current user")
				. ($caps["user_id"] > 0 ? " (user id " . $caps["user_id"] . ")" : "")
				. ", a BigTree " . $caps["role"] . " (permission level " . $caps["level"] . ").";
			$lines[] = "When the user says \"me\", \"my\" or \"mine\", it means that user. Use their id for tool "
				. "arguments that name a user, and call get_my_capabilities if you need their email or timezone.";

			if ($caps["is_developer"]) {
				$lines[] = "They may manage developer resources (templates, modules, callouts), settings, tags, and users, in addition to content.";
			} elseif ($caps["is_administrator"]) {
				$lines[] = "They may manage settings, tags, and users, and content they have page/module permission for. They CANNOT create or edit templates, modules, or callouts — that requires a developer.";
			} else {
				// "cannot manage users" used to swallow the user's own profile, which
				// every editor can edit today — the self-contradiction audit #14 B1
				// closed. The wall is about *other* users now, which is what it always
				// meant.
				$lines[] = "They are a content editor. They may work with pages and module entries they have permission for, including adding and removing tags that already exist. They CANNOT create new tags, or manage OTHER users, settings, templates, modules, or callouts.";
			}

			$lines[] = "Any user, at any level, may edit their OWN profile — name, company, timezone, the daily "
				. "content digest, and their content-alert subscriptions — with update_user targeting their own user "
				. "id. Their email address is the exception and is never changed through you.";
			$lines[] = "Never claim to have done something the user lacks permission for. If an action needs a higher role, say so plainly and offer an alternative (e.g. saving a draft/pending change, or asking an administrator).";

			return implode("\n", $lines);
		}

		/**
		 * A scalar off the acting user, whichever shape the caller was handed.
		 *
		 * Every seam in the AI stack takes `$user` as "object|array", so the identity
		 * reads below need the same tolerance `level()` has always had.
		 *
		 * @param object|array $user
		 */
		private static function field($user, string $key): string {
			if (is_object($user)) {

				return trim((string)($user->$key ?? ""));
			}

			if (is_array($user)) {

				return trim((string)($user[$key] ?? ""));
			}

			return "";
		}
	}
