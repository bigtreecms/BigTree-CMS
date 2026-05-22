<?php
	/*
		Class: BigTreeSessionHandler
			A session handler for storing BigTree sessions in the database.
	*/

	class BigTreeSessionHandler implements SessionHandlerInterface {

		private static $BotAgents = [
			'Googlebot',
			'Bingbot',
			'Slurp',
			'DuckDuckBot',
			'YandexBot',
			'Baiduspider',
			'Sogou',
			'Exabot',
			'Facebot',
			'MJ12bot',
			'AhrefsBot',
			'SemrushBot',
			'DotBot',
			'BLEXBot',
			'archive.org_bot',
			'Gigabot',
			'Blexbot',
			'Cliqzbot',
			'Embedly',
			'W3C_Validator',
			'Amazon-Route53-Health-Check-Service',
			'ELB-HealthChecker',
			'Chrome Privacy Preserving Prefetch Proxy',
		];
		private static $Started = false;
		private static $Timeout = 3600;
		private $Exists = false;

		// These aren't needed as the SQL class handles the connection
		public function open($save_path, $session_name) {
			return true;
		}

		public function close() {
			return true;
		}

		public function read($session_id) {
			$session = SQL::fetch("SELECT * FROM bigtree_sessions WHERE id = ?", $session_id);

			if (!$session) {
				return "";
			}

			$this->Exists = true;

			// Invalidate a session that is too old's data
			if ($session["last_accessed"] < time() - self::$Timeout) {
				SQL::update("bigtree_sessions", $session_id, ["data" => "", "last_accessed" => time()]);

				return "";
			} else {
				SQL::update("bigtree_sessions", $session_id, ["last_accessed" => time()]);

				return $session["data"] ?? "";
			}
		}

		public function write($session_id, $session_data) {
			if (!$this->Exists) {
				SQL::query("INSERT INTO bigtree_sessions (`id`, `last_accessed`, `data`, `ip_address`, `user_agent`) VALUES (?, ?, ?, ?, ?)", $session_id, time(), $session_data, BigTree::remoteIP(), $_SERVER["HTTP_USER_AGENT"]);
			} else {
				SQL::update("bigtree_sessions", $session_id, ["last_accessed" => time(), "data" => $session_data]);
			}

			return true;
		}

		public function destroy($session_id) {
			return SQL::delete("bigtree_sessions", $session_id);
		}

		public function gc($maxlifetime) {
			// Return the number of deleted sessions, or false on error
			$affected = SQL::query("DELETE FROM bigtree_sessions WHERE last_accessed < ?", time() - $maxlifetime)->rows();

			return $affected !== null ? $affected : false;
		}

		static function start() {
			global $bigtree;

			if (static::$Started || session_status() === PHP_SESSION_ACTIVE) {
				return;
			}

			static::$Started = true;

			if (!empty($bigtree["config"]["session_lifetime"])) {
				static::$Timeout = intval($bigtree["config"]["session_lifetime"]);
			}

			if (!empty($bigtree["config"]["session_handler"]) && $bigtree["config"]["session_handler"] == "db") {
				// Ignore bot user agents from creating session spam
				foreach (static::$BotAgents as $agent) {
					if (stripos($_SERVER["HTTP_USER_AGENT"], $agent) !== false) {
						return;
					}
				}

				$handler = new BigTreeSessionHandler();
				session_set_save_handler($handler, true);
			}

			session_set_cookie_params([
				"lifetime" => 0,
				"path" => str_replace(DOMAIN, "", WWW_ROOT),
				"secure" => !empty($bigtree["config"]["ssl_only_session_cookie"]),
				"httponly" => true,
				"samesite" => !empty($bigtree["config"]["ssl_only_session_cookie"]) ? "None" : "Lax",
			]);

			session_start(array("gc_maxlifetime" => static::$Timeout));
		}

	}
