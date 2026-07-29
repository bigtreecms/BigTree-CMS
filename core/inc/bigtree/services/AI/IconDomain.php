<?php
	namespace BigTree\Services\AI;

	use BigTree\Api\ModuleIcons;

	/**
	 * The closed-vocabulary check for a module's `icon`.
	 *
	 * A module icon is one of 54 slugs — `gear`, `truck`, `news`, `twitter` — and
	 * nothing else. The admin never types the field: the Module Designer renders the
	 * vocabulary as a grid and the developer clicks a cell, so the value cannot be
	 * wrong. The assistant is the only writer without that picker, and until audit
	 * #13 A2 the argument was declared as "Optional icon identifier.", trimmed into
	 * the record, and re-written at approval without a second look. The model reaches
	 * for the names it knows — `newspaper`, `calendar-days`, `file-text` — and the
	 * SPA falls back to a generic box with no error anywhere, so the module ships
	 * with the wrong glyph and nothing ever says why.
	 *
	 * This is the same shape as audit #11 A2 (a hand-written type list nobody
	 * derived) and audit #12 B1 (relation candidates with no lookup tool): an
	 * enumerated domain the model could only guess at. So the fix is the same shape
	 * too — derive, never copy. `BigTree\Api\ModuleIcons` is the single source; a
	 * hand-copied list here would be one more thing to drift, which is the whole
	 * lesson of both earlier findings. (The vocabulary used to live only on
	 * `BigTreeAdmin::$IconClasses`; reading it here dragged the whole legacy admin
	 * facade into every module read/write, so it was moved to the modern
	 * `ModuleIcons` class — which `admin.php` now points its property at.)
	 *
	 * Run it at staging *and* at approval, per the standing "never trusted on the way
	 * back out" rule, and offer the list on `get_module` so the model can pick rather
	 * than guess.
	 */
	class IconDomain {
		/**
		 * The vocabulary, from the one place the CMS declares it.
		 *
		 * @return list<string>
		 */
		public static function slugs(): array {

			return array_values(array_map("strval", ModuleIcons::slugs()));
		}

		/**
		 * The canonical form of a proposed icon: trimmed, and folded to the case the
		 * vocabulary is declared in when it names a real slug. "News" is the model
		 * getting the icon right and the capitalization wrong, which is not a thing
		 * worth refusing over — but it is worth storing as `news`, because that is what
		 * every reader of the column matches against.
		 */
		public static function normalize(string $icon): string {
			$icon = trim($icon);

			if ($icon === "") {

				return "";
			}

			foreach (self::slugs() as $slug) {
				if (strcasecmp($slug, $icon) === 0) {

					return $slug;
				}
			}

			return $icon;
		}

		/**
		 * Refuse an icon that isn't in the vocabulary, naming the ones that are.
		 * Returns null when the value will render as something. No icon at all is
		 * always allowed — a module without one is the ordinary case.
		 */
		public static function violation(string $icon): ?string {
			$icon = trim($icon);

			if ($icon === "") {

				return null;
			}

			$slugs = self::slugs();

			foreach ($slugs as $slug) {
				if (strcasecmp($slug, $icon) === 0) {

					return null;
				}
			}

			return "\"{$icon}\" isn't one of this CMS's module icons — the admin picks from a fixed grid, and "
				. "anything outside it renders as a generic box. Choose one of: " . implode(", ", $slugs)
				. ". You can also leave the icon out entirely.";
		}
	}
