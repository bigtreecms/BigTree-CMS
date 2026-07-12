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
		public function open(string $path, string $name): bool {
			return true;
		}

		public function close(): bool {
			return true;
		}

		public function read(string $id): string|false {
			$session = SQL::fetch("SELECT * FROM bigtree_sessions WHERE id = ?", $id);

			if (!$session) {
				return "";
			}

			$this->Exists = true;

			// Invalidate a session that is too old's data
			if ($session["last_accessed"] < time() - self::$Timeout) {
				SQL::update("bigtree_sessions", $id, ["data" => "", "last_accessed" => time()]);

				return "";
			} else {
				SQL::update("bigtree_sessions", $id, ["last_accessed" => time()]);

				return $session["data"] ?? "";
			}
		}

		public function write(string $id, string $data): bool {
			if (!$this->Exists) {
				SQL::query("INSERT INTO bigtree_sessions (`id`, `last_accessed`, `data`, `ip_address`, `user_agent`) VALUES (?, ?, ?, ?, ?)", $id, time(), $data, BigTree::remoteIP(), $_SERVER["HTTP_USER_AGENT"]);
			} else {
				SQL::update("bigtree_sessions", $id, ["last_accessed" => time(), "data" => $data]);
			}

			return true;
		}

		public function destroy(string $id): bool {
			return SQL::delete("bigtree_sessions", $id);
		}

		public function gc(int $max_lifetime): int|false {
			// Return the number of deleted sessions, or false on error
			$affected = SQL::query("DELETE FROM bigtree_sessions WHERE last_accessed < ?", time() - $max_lifetime)->rows();

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
