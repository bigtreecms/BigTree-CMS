<?php
	namespace BigTree\Api;

	/**
	 * Hook dispatcher for the REST API.
	 *
	 * Reads the existing cache/bigtree-hooks.json registry directly so we don't
	 * have to construct a BigTreeAdmin (which would run session/auth side effects
	 * inappropriate for stateless API requests).
	 *
	 * Registered hook files are plain PHP, included with a $data variable available
	 * (and any $data_context keys hoisted as locals). The included file's last
	 * expression-of-$data is returned to the caller.
	 *
	 * Two namespaces of hooks:
	 *
	 * 1. Legacy compatibility — the existing types/contexts the legacy admin already
	 *    fires (e.g., type="fields" context="form"). Extensions that hooked those
	 *    keep firing transparently when API code uses the same type/context strings.
	 *
	 * 2. API-prefixed types ("api.*") — new extension surface for the REST layer.
	 *    Convention: "api.{domain}.{event}", e.g. "api.page.created", "api.page.updated",
	 *    "api.page.deleted", "api.module_entry.created", "api.resource.uploaded".
	 *    These are NEW; extensions opt into them by dropping a hook file in
	 *    {core,custom,extensions/*}/admin/hooks/api/{domain}/{event}.php
	 */
	class Hooks {
		const CACHE_FILE = "cache/bigtree-hooks.json";

		private static $cache = null;
		private static $default_context = [];

		/**
		 * Sets the request-scoped default context merged under every fire() call.
		 * The Kernel wires this after authentication resolves the actor so hook
		 * call sites don't each have to remember to pass ["user_id" => ...].
		 * Per-call context keys win over these defaults.
		 */
		public static function setDefaultContext(array $context) {
			self::$default_context = $context;
		}

		/** Resets the default context. Called per-dispatch and by the _test harness. */
		public static function clearDefaultContext() {
			self::$default_context = [];
		}

		public static function run($type, $context = "", $data = "", array $data_context = []) {
			$registry = self::loadRegistry();

			if (!$registry) {
				return $data;
			}

			$bucket = null;

			if ($context !== "" && isset($registry[$type][$context]) && is_array($registry[$type][$context])) {
				$bucket = $registry[$type][$context];
			} elseif ($context === "" && isset($registry[$type]) && is_array($registry[$type])) {
				$bucket = $registry[$type];
			}

			if (!$bucket) {
				return $data;
			}

			$invoker = function ($hook_path, $data, $data_context) {
				foreach ($data_context as $key => $value) {
					$$key = $value;
				}

				include SERVER_ROOT . $hook_path;

				return $data;
			};

			foreach ($bucket as $hook_path) {
				$data = $invoker($hook_path, $data, $data_context);
			}

			return $data;
		}

		/**
		 * Convenience wrapper for the new api.* surface. Pass the event name
		 * after the "api." prefix, e.g. fire("page.updated", $page_array).
		 *
		 * Returns whatever the hook chain returns (or $data unchanged if no
		 * hooks are registered for the event).
		 */
		public static function fire($event, $data = null, array $data_context = []) {
			// Per-call context wins over the request-scoped defaults (actor id, etc.).
			$data_context = array_merge(self::$default_context, $data_context);

			return self::run("api." . $event, "", $data, $data_context);
		}

		/** For tests + manifest rebuilds. */
		public static function clearCache() {
			self::$cache = null;
		}

		private static function loadRegistry() {
			if (self::$cache !== null) {
				return self::$cache;
			}
			$path = SERVER_ROOT . self::CACHE_FILE;

			if (!file_exists($path)) {
				// Don't auto-build here — the legacy admin builds this cache via
				// BigTreeAdmin::cacheHooks() and we'd need its $this context. If
				// the cache is missing, no hooks fire (and that's acceptable for
				// API requests on a freshly-installed site).
				self::$cache = [];

				return [];
			}

			$raw = file_get_contents($path);
			$decoded = json_decode($raw, true);
			self::$cache = is_array($decoded) ? $decoded : [];

			return self::$cache;
		}
	}
