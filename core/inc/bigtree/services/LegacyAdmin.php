<?php
	namespace BigTree\Services;

	use BigTree\Api\Json;

	/**
	 * The ONLY place the modern layer may touch \BigTreeAdmin. Produces a
	 * request-cached instance hydrated from the API actor (not the PHP
	 * session). BigTreeAutoModule checks get_class($x) === "BigTreeAdmin",
	 * so this must return the real class, never a subclass.
	 */
	class LegacyAdmin {

		/** @var \BigTreeAdmin|null */
		private static $instance = null;

		/**
		 * Return a request-scoped BigTreeAdmin hydrated from the API user when
		 * no PHP session is present. Also assigns $GLOBALS["admin"] so
		 * global $admin consumers (BigTreeAutoModule, field process.php includes)
		 * see the same instance.
		 *
		 * @param object|null $user API user with id/level (or null)
		 * @return \BigTreeAdmin
		 */
		public static function bridge($user = null): \BigTreeAdmin {
			global $admin;

			if (self::$instance instanceof \BigTreeAdmin) {
				$instance = self::$instance;
			} elseif ($admin instanceof \BigTreeAdmin) {
				$instance = $admin;
				self::$instance = $instance;
			} else {
				$instance = new \BigTreeAdmin();
				self::$instance = $instance;
			}

			if (!$instance->ID && $user !== null) {
				$user_id = is_object($user) ? ($user->id ?? null) : ($user["id"] ?? null);
				$user_level = is_object($user) ? ($user->level ?? 0) : ($user["level"] ?? 0);

				if ($user_id) {
					$instance->ID = $user_id;
					$instance->Level = (int) $user_level;

					$row = \SQL::fetch("SELECT permissions, timezone FROM bigtree_users WHERE id = ?", (int) $user_id);

					if ($row) {
						$instance->Permissions = Json::decode($row["permissions"]);
						$instance->Timezone = $row["timezone"];
					}
				}
			}

			if (!isset($GLOBALS["admin"]) || !($GLOBALS["admin"] instanceof \BigTreeAdmin)) {
				$GLOBALS["admin"] = $instance;
			}

			$admin = $instance;

			return $instance;
		}

		/**
		 * Reset the request-scoped cache (tests / multi-dispatch harnesses).
		 */
		public static function clear(): void {
			self::$instance = null;
		}
	}
