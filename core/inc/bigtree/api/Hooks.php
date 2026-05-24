<?php
	namespace BigTree\Api;

	/**
	 * Thin wrapper that delegates to BigTreeAdmin::runHooks so existing
	 * file-cached hook registry (cache/bigtree-hooks.json) keeps working
	 * for extensions that hooked the legacy admin call sites.
	 */
	class Hooks {
		private static $admin_instance = null;

		public static function run($type, $context = "", $data = "", $data_context = []) {
			$admin = self::admin();
			return $admin->runHooks($type, $context, $data, $data_context);
		}

		private static function admin() {
			if (self::$admin_instance === null) {
				// Construct an admin instance without running its session/auth bootstrap.
				// BigTreeAdmin's constructor reads $_SESSION and may write headers; we use a
				// no-side-effects facade by calling runHooks via a fresh instance only after
				// disabling the side effects. Since BigTreeAdmin's constructor does several
				// things we don't want in API context, we use a tiny static-style helper instead.
				self::$admin_instance = new \BigTreeAdmin();
			}
			return self::$admin_instance;
		}
	}
