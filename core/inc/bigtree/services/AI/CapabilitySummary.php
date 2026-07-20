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
				"can_manage_tags" => $level >= 1,
				"can_create_tags" => $level >= 1,
				"can_attach_tags" => true,
				"can_manage_settings" => $level >= 1,
				"can_manage_templates" => $level >= 2,
				"can_manage_modules" => $level >= 2,
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
				"Deleting users, templates, callouts, modules or settings" =>
					"the relevant Developer or Users screen",
				"Creating or deleting settings" => "Developer → Settings",
				"Reading or writing encrypted settings" =>
					"Developer → Settings — encrypted values are never exposed to the assistant",
				"Duplicating or reordering pages" => "the page tree in Pages",
				"Reordering module entries" => "the module's landing view",
				"Editing a module's tables, forms, views or actions" =>
					"Developer → Modules → Module Designer",
				"Changing a module's route" => "Developer → Modules",
				"Installing, updating or removing extensions" => "Developer → Extensions",
				"Managing content locks" => "the lock banner on the item being edited",
				"Editing complex fields — uploads, matrices, relationships, callouts on a page" =>
					"the page or entry editor; the assistant only sets simple text-like fields",
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
