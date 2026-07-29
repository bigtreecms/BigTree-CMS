<?php
	namespace BigTree\Api;

	/**
	 * The canonical module-icon vocabulary.
	 *
	 * A module's `icon` is one of a fixed set of slugs — `gear`, `truck`, `news`,
	 * `twitter` — and nothing else; the admin picks one from a grid rather than
	 * typing it. Historically this list lived only as `BigTreeAdmin::$IconClasses`,
	 * a public static on the 9,800-line legacy admin facade, so anything that
	 * needed the vocabulary — the AI IconDomain, a validator, an endpoint — had to
	 * drag all of admin.php into the request to read it, in direct violation of the
	 * rule that the modern layer touches `\BigTreeAdmin` only through
	 * `LegacyAdmin::bridge()`.
	 *
	 * The list is data, not admin behaviour, so it belongs here. `admin.php` now
	 * declares `$IconClasses = ModuleIcons::SLUGS` (kept for backward compatibility
	 * with any extension or custom admin subclass that still reads the property),
	 * `IconDomain` reads `ModuleIcons::slugs()`, and `GET /module-icons` serves the
	 * same list to the SPA's icon picker — one source, no legacy load.
	 *
	 * The order is the one the legacy designer's grid offered, preserved so the
	 * SPA picker reads the same left-to-right.
	 */
	class ModuleIcons {
		/** @var list<string> */
		public const SLUGS = [
			"gear",
			"truck",
			"token",
			"export",
			"redirect",
			"help",
			"error",
			"ignored",
			"world",
			"server",
			"clock",
			"network",
			"car",
			"key",
			"folder",
			"calendar",
			"search",
			"setup",
			"page",
			"computer",
			"picture",
			"news",
			"events",
			"blog",
			"form",
			"category",
			"map",
			"user",
			"question",
			"sports",
			"credit_card",
			"cart",
			"cash_register",
			"lock_key",
			"bar_graph",
			"comments",
			"email",
			"weather",
			"pin",
			"planet",
			"mug",
			"atom",
			"shovel",
			"cone",
			"lifesaver",
			"target",
			"ribbon",
			"dice",
			"ticket",
			"pallet",
			"camera",
			"video",
			"twitter",
			"facebook",
		];

		/**
		 * The vocabulary as a list. A method as well as the const so callers that
		 * want the runtime value (tests, the endpoint, IconDomain) have one accessor
		 * to reach for, and the const stays available for the property default in
		 * admin.php, which must be a constant expression.
		 *
		 * @return list<string>
		 */
		public static function slugs(): array {

			return self::SLUGS;
		}
	}
