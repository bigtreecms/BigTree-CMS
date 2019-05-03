<?php
	/*
		Class: BigTree\SessionHandler
			Provides an interface for handling BigTree sessions.
	*/
	
	namespace BigTree;
	
	class SessionHandler
	{
		
		private static $Exists = false;
		private static $Started = false;
		private static $Timeout = 3600;
		
		// These aren't needed as the SQL class handles the connection
		public static function open()
		{
			return true;
		}
		
		public static function close()
		{
			return true;
		}
		
		public static function read($id)
		{
			$session = SQL::fetch("SELECT * FROM bigtree_sessions WHERE id = ?", $id);
			
			if (!$session) {
				return "";
			}
			
			static::$Exists = true;
			
			// Invalidate a session that is too old's data
			if ($session["last_accessed"] < time() - static::$Timeout) {
				SQL::update("bigtree_sessions", $id, ["data" => "", "last_accessed" => time()]);
				
				return "";
			// Invalidate sessions with incorrect user agents of IP addresses
			} elseif ($session["ip_address"] != BigTree::remoteIP() || $session["user_agent"] != $_SERVER["HTTP_USER_AGENT"]) {
				SQL::update("bigtree_sessions", $id, ["data" => "", "last_accessed" => time()]);
				
				return "";
			} else {
				SQL::update("bigtree_sessions", $id, ["last_accessed" => time()]);
				
				return $session["data"];
			}
		}
		
		public static function write($id, $data)
		{
			if (!static::$Exists) {
				SQL::insert("bigtree_sessions", [
					"id" => $id,
					"last_accessed" => time(),
					"data" => $data,
					"ip_address" => Router::getRemoteIP(),
					"user_agent" => $_SERVER["HTTP_USER_AGENT"]
				]);
			} else {
				SQL::update("bigtree_sessions", $id, ["last_accessed" => time(), "data" => $data]);
			}
			
			return true;
		}
		
		public static function destroy($id)
		{
			return SQL::delete("bigtree_sessions", $id);
		}
		
		public static function clean($max_age)
		{
			SQL::query("DELETE FROM bigtree_sessions WHERE last_accessed < ?", time() - $max_age);
			
			return true;
		}
		
		public static function start()
		{
			if (static::$Started) {
				return;
			}
			
			static::$Started = true;
			
			if (!empty(Router::$Config["session_lifetime"])) {
				static::$Timeout = intval(Router::$Config["session_lifetime"]);
			}
			
			if (!empty(Router::$Config["session_handler"]) && Router::$Config["session_handler"] == "db") {
				session_set_save_handler(
					"BigTree\SessionHandler::open",
					"BigTree\SessionHandler::close",
					"BigTree\SessionHandler::read",
					"BigTree\SessionHandler::write",
					"BigTree\SessionHandler::destroy",
					"BigTree\SessionHandler::clean"
				);
			}
			
			session_set_cookie_params(0, str_replace(DOMAIN, "", WWW_ROOT), "", !empty(Router::$Config["ssl_only_session_cookie"]), true);
			session_start(["gc_maxlifetime" => static::$Timeout]);
		}
		
	}
	