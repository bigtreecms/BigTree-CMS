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
		 * @param object|array<string,mixed>|null $user API user with id/level (or null)
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

			// Hydrate from the API actor when provided. Re-apply when the actor
			// changes (multi-dispatch test harness, or sequential API calls that
			// bridge different users) — previously we only set ID when empty, so a
			// second bridge with a new user kept the stale id (and FK-failed after
			// the first fixture user was deleted).
			if ($user !== null) {
				if (is_object($user)) {
					$user_id = $user->id ?? null;
					$user_level = $user->level ?? 0;
				} else {
					$user_id = $user["id"] ?? null;
					$user_level = $user["level"] ?? 0;
				}

				if ($user_id && (int)$instance->ID !== (int)$user_id) {
					$instance->ID = $user_id;
					$instance->Level = (int) $user_level;

					$row = \SQL::fetch("SELECT permissions, timezone FROM bigtree_users WHERE id = ?", (int) $user_id);

					if ($row) {
						$instance->Permissions = Json::decode($row["permissions"]);
						$instance->Timezone = $row["timezone"];
					}
				} elseif ($user_id && (int)$instance->Level !== (int)$user_level) {
					$instance->Level = (int) $user_level;
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
