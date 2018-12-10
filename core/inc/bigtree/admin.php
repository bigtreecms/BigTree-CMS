<?php
	/*
		Class: BigTreeAdmin
			The main class used by the admin for manipulating and retrieving data.
	*/

	class BigTreeAdminBase {

		public static $IRLPrefixes = [];
		public static $IRLsCreated = [];
		public static $PerPage = 15;
		
		// Open Graph Types
		public static $OpenGraphTypes = [
			"website",
			"article",
			"book",
			"business.business",
			"fitness.course",
			"game.achievement",
			"music.album",
			"music.playlist",
			"music.radio_station",
			"music.song",
			"place",
			"product",
			"profile",
			"restaurant.menu",
			"restaurant.menu_item",
			"restaurant.menu_section",
			"restaurant.restaurant",
			"video.episode",
			"video.movie",
			"video.other",
			"video.tv_show"
		];

		// !View Types
		public static $ViewTypes = array(
			"searchable" => "Searchable List",
			"draggable" => "Draggable List",
			"nested" => "Nested Draggable List",
			"grouped" => "Grouped List",
			"images" => "Image List",
			"images-grouped" => "Grouped Image List"
		);

		// !Reserved Column Names
		public static $ReservedColumns = array(
			"id",
			"position",
			"archived",
			"approved"
		);

		// !Reserved Top Level Routes
		public static $ReservedTLRoutes = array(
			"ajax",
			"css",
			"feeds",
			"js",
			"sitemap.xml",
			"_preview",
			"_preview-pending"
		);

		// !View Actions
		public static $ViewActions = array(
			"approve" => array(
				"key" => "approved",
				"name" => "Approve",
				"class" => "icon_approve icon_approve_on"
			),
			"archive" => array(
				"key" => "archived",
				"name" => "Archive",
				"class" => "icon_archive"
			),
			"feature" => array(
				"key" => "featured",
				"name" => "Feature",
				"class" => "icon_feature icon_feature_on"
			),
			"edit" => array(
				"key" => "id",
				"name" => "Edit",
				"class" => "icon_edit"
			),
			"delete" => array(
				"key" => "id",
				"name" => "Delete",
				"class" => "icon_delete"
			)
		);

		// !Icon Classes
		public static $IconClasses = array("gear","truck","token","export","redirect","help","error","ignored","world","server","clock","network","car","key","folder","calendar","search","setup","page","computer","picture","news","events","blog","form","category","map","user","question","sports","credit_card","cart","cash_register","lock_key","bar_graph","comments","email","weather","pin","planet","mug","atom","shovel","cone","lifesaver","target","ribbon","dice","ticket","pallet","camera","video","twitter","facebook");
		public static $ActionClasses = array("add","delete","list","edit","refresh","gear","truck","token","export","redirect","help","error","ignored","world","server","clock","network","car","key","folder","calendar","search","setup","page","computer","picture","news","events","blog","form","category","map","user","question","sports","credit_card","cart","cash_register","lock_key","bar_graph","comments","email","weather","pin","planet","mug","atom","shovel","cone","lifesaver","target","ribbon","dice","ticket","pallet","lightning","camera","video","twitter","facebook");

		/*
			Constructor:
				Initializes the user's permissions.
		*/

		public function __construct() {
			$this->checkPOSTError();

			if (isset($_SESSION["bigtree_admin"]["email"]) && isset($_SESSION["bigtree_admin"]["csrf_token"])) {
				$f = sqlfetch(sqlquery("SELECT * FROM bigtree_users WHERE id = '".$_SESSION["bigtree_admin"]["id"]."' AND email = '".sqlescape($_SESSION["bigtree_admin"]["email"])."'"));
				if ($f) {
					$this->ID = $f["id"];
					$this->User = $f["email"];
					$this->Level = $f["level"];
					$this->Name = $f["name"];
					$this->Permissions = json_decode($f["permissions"],true);
					$this->CSRFToken = $_SESSION["bigtree_admin"]["csrf_token"];
					$this->CSRFTokenField = $_SESSION["bigtree_admin"]["csrf_token_field"];
					$this->Timezone = $f["timezone"];
				}
			} elseif (isset($_COOKIE["bigtree_admin"]["email"])) {
				$user = sqlescape($_COOKIE["bigtree_admin"]["email"]);

				// Get chain and session broken out
				list($session,$chain) = json_decode($_COOKIE["bigtree_admin"]["login"], true);

				// See if this is the current chain and session
				$chain_entry = sqlfetch(sqlquery("SELECT * FROM bigtree_user_sessions WHERE email = '$user' AND chain = '".sqlescape($chain)."'"));

				if ($chain_entry && $chain_entry["csrf_token"]) {
					// If both chain and session are legit, log them in
					if ($chain_entry["id"] == $session) {
						$f = sqlfetch(sqlquery("SELECT * FROM bigtree_users WHERE email = '$user'"));
						if ($f) {
							// Generate a random CSRF token
							$csrf_token = base64_encode(openssl_random_pseudo_bytes(32));
							$csrf_token_field = "__csrf_token_".BigTree::randomString(32)."__";

							// Setup session
							$this->ID = $f["id"];
							$this->User = $user;
							$this->Level = $f["level"];
							$this->Name = $f["name"];
							$this->Permissions = json_decode($f["permissions"],true);
							$this->CSRFToken = $csrf_token;
							$this->CSRFTokenField = $csrf_token_field;
							$this->Timezone = $f["timezone"];

							// Regenerate session ID on user state change
							$old_session_id = session_id();
							session_regenerate_id();
							
							if (!empty($bigtree["config"]["session_handler"]) && $bigtree["config"]["session_handler"] == "db") {
								SQL::update("bigtree_sessions", $old_session_id, [
									"id" => session_id(), 
									"is_login" => "on", 
									"logged_in_user" => $f["id"]
								]);
							}

							$_SESSION["bigtree_admin"]["id"] = $f["id"];
							$_SESSION["bigtree_admin"]["email"] = $f["email"];
							$_SESSION["bigtree_admin"]["name"] = $f["name"];
							$_SESSION["bigtree_admin"]["level"] = $f["level"];
							$_SESSION["bigtree_admin"]["csrf_token"] = $csrf_token;
							$_SESSION["bigtree_admin"]["csrf_token_field"] = $csrf_token_field;

							// Delete existing session
							sqlquery("DELETE FROM bigtree_user_sessions WHERE id = '".sqlescape($session)."'");

							// Generate a random session id
							$session = uniqid("session-",true);
							while (sqlrows(sqlquery("SELECT id FROM bigtree_user_sessions WHERE id = '".sqlescape($session)."'"))) {
								$session = uniqid("session-",true);
							}

							// Create a new session with the same chain
							sqlquery("INSERT INTO bigtree_user_sessions (`id`,`chain`,`email`,`csrf_token`,`csrf_token_field`) VALUES ('".sqlescape($session)."','".sqlescape($chain)."','$user','$csrf_token','$csrf_token_field')");
							setcookie('bigtree_admin[login]',json_encode(array($session,$chain)),strtotime("+1 month"),str_replace(DOMAIN,"",WWW_ROOT),"",false,true);
						}
					// Chain is legit and session isn't -- someone has taken your cookies
					} else {
						// Delete existing cookies
						setcookie("bigtree_admin[email]","",time()-3600,str_replace(DOMAIN,"",WWW_ROOT));
						setcookie("bigtree_admin[login]","",time()-3600,str_replace(DOMAIN,"",WWW_ROOT));

						// Delete all sessions for this user
						sqlquery("DELETE FROM bigtree_user_sessions WHERE email = '$user'");
					}
				}

				// Clean up
				unset($user,$f,$session,$chain,$chain_entry);
			}

			// Check the permissions to see if we should show the pages tab.
			if (!$this->Level) {
				$this->HidePages = true;
				if (is_array($this->Permissions["page"])) {
					foreach ($this->Permissions["page"] as $k => $v) {
						if ($v != "n" && $v != "i") {
							$this->HidePages = false;
						}
					}
				}
			} else {
				$this->HidePages = false;
			}

			// Update the reserved top level routes with the admin's route
			$ar = explode("/",str_replace(WWW_ROOT,"",rtrim(ADMIN_ROOT,"/")));
			static::$ReservedTLRoutes[] = $ar[0];
			unset($ar);

			// Check for Per Page value
			$pp = static::getSetting("bigtree-internal-per-page",false);
			$v = intval($pp["value"]);
			if ($v) {
				static::$PerPage = $v;
			}
		}

		/*
			Function: allocateResources
				Assigns resources from $this->IRLsCreated

			Parameters:
				table - Table in which the entry resides
				entry - Entry ID to assign to
		*/

		public static function allocateResources($table, $entry) {
			SQL::delete("bigtree_resource_allocation", ["table" => $table, "entry" => $entry]);

			foreach (static::$IRLsCreated as $resource) {
				SQL::insert("bigtree_resource_allocation", [
					"table" => $table,
					"entry" => $entry,
					"resource" => $resource,
					"updated_at" => "NOW()"
				]);
			}
		}

		/*
			Function: archivePage
				Archives a page.

			Parameters:
				page - Either a page id or page entry.

			Returns:
				true if successful. false if the logged in user doesn't have permission.

			See Also:
				<archivePageChildren>
		*/

		public function archivePage($page) {
			if (is_array($page)) {
				$page = sqlescape($page["id"]);
			} else {
				$page = sqlescape($page);
			}

			$access = $this->getPageAccessLevel($page);
			if ($access == "p" && $this->canModifyChildren(BigTreeCMS::getPage($page))) {
				sqlquery("UPDATE bigtree_pages SET archived = 'on' WHERE id = '$page'");
				$this->archivePageChildren($page);
				static::growl("Pages","Archived Page");
				$this->track("bigtree_pages",$page,"archived");
				return true;
			}
			return false;
		}

		/*
			Function: archivePageChildren
				Archives a page's children and sets the archive status to inherited.

			Parameters:
				page - A page id.

			See Also:
				<archivePage>
		*/

		public function archivePageChildren($page) {
			$page = sqlescape($page);
			$q = sqlquery("SELECT * FROM bigtree_pages WHERE parent = '$page' AND archived != 'on'");
			while ($f = sqlfetch($q)) {
				$this->track("bigtree_pages",$f["id"],"archived-inherited");
				$this->archivePageChildren($f["id"]);
			}
			sqlquery("UPDATE bigtree_pages SET archived = 'on', archived_inherited = 'on' WHERE parent = '$page' AND archived != 'on'");
		}

		/*
			Function: assign2FASecret
				Assigns a two factor auth token to a user and then logs them in.

			Parameters:
				secret - A Google Authenticator secret
		*/

		public function assign2FASecret($secret) {
			$user = sqlfetch(sqlquery("SELECT 2fa_login_token FROM bigtree_users WHERE id = '".$_SESSION["bigtree_admin"]["2fa_id"]."'"));

			if ($user["2fa_login_token"] == $_SESSION["bigtree_admin"]["2fa_login_token"]) {
				sqlquery("UPDATE bigtree_users SET 2fa_secret = '".sqlescape($secret)."' WHERE id = '".$_SESSION["bigtree_admin"]["2fa_id"]."'");
			}

			$this->login2FA(null, true);
		}

		/*
			Function: autoIPL
				Automatically converts links to internal page links.

			Parameters:
				html - A string of contents that may contain URLs

			Returns:
				A string with hard links converted into internal page links.
		*/

		public static function autoIPL($html) {
			// If this string is actually just a URL, IPL it.
			if ((substr($html,0,7) == "http://" || substr($html,0,8) == "https://") && strpos($html,"\n") === false && strpos($html,"\r") === false) {
				$html = static::makeIPL($html);
			// Otherwise, switch all the image srcs and javascripts srcs and whatnot to {wwwroot}.
			} else {
				$html = preg_replace_callback('/href="([^"]*)"/',array("BigTreeAdmin","autoIPLCallbackHref"),$html);
				$html = preg_replace_callback('/src="([^"]*)"/',array("BigTreeAdmin","autoIPLCallbackSrc"),$html);
				$html = BigTreeCMS::replaceHardRoots($html);
			}
			return $html;
		}

		private static function autoIPLCallbackHref($matches) {
			$href = static::makeIPL(BigTreeCMS::replaceRelativeRoots($matches[1]));
			return 'href="'.$href.'"';
		}
		private static function autoIPLCallbackSrc($matches) {
			$src = static::makeIPL(BigTreeCMS::replaceRelativeRoots($matches[1]));
			return 'src="'.$src.'"';
		}

		/*
			Function: backupDatabase
				Backs up the entire database to a given file.

			Parameters:
				file - Full file path to dump the database to.

			Returns:
				true if successful.
		*/

		public static function backupDatabase($file) {
			if (!BigTree::isDirectoryWritable($file)) {
				return false;
			}

			$pointer = fopen($file,"w");
			fwrite($pointer,"SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO';\n");
			fwrite($pointer,"SET foreign_key_checks = 0;\n\n");

			// We need to dump the bigtree tables in the proper order or they will not properly be recreated with the right foreign keys
			$q = sqlquery("SHOW TABLES");
			while ($f = sqlfetch($q)) {
				$table = current($f);

				// Write the drop / create statements
				fwrite($pointer,"DROP TABLE IF EXISTS `$table`;\n");
				$definition = sqlfetch(sqlquery("SHOW CREATE TABLE `$table`"));
				fwrite($pointer,str_replace(array("\n	","\n"),"",end($definition)).";\n");

				// Get all the table contents, write them out
				$rows = BigTree::tableContents($table);
				foreach ($rows as $row) {
					fwrite($pointer,$row.";\n");
				}

				// Separate it from the next table
				fwrite($pointer,"\n");
			}

			fwrite($pointer,"\nSET foreign_key_checks = 1;");
			fclose($pointer);

			return true;
		}

		/*
			Function: cacheHooks
				Caches extension hooks.
		*/

		public function cacheHooks() {
			$hooks = [];
			$extensions = BigTreeJSONDB::getAll("extensions");

			foreach ($extensions as $extension) {
				$base_dir = SERVER_ROOT."extensions/".$extension["id"]."/hooks/";

				if (file_exists($base_dir)) {
					$hook_files = BigTree::directoryContents($base_dir, true, "php");

					foreach ($hook_files as $file) {
						$parts = explode("/", str_replace($base_dir, "", substr($file, 0, -4)));

						if (count($parts) == 2) {
							$hooks[$parts[0]][$parts[1]][] = str_replace(SERVER_ROOT, "", $file);
						} elseif (count($parts) == 1) {
							$hooks[$parts[0]][] = str_replace(SERVER_ROOT, "", $file);
						}
					}
				}
			}

			BigTree::putFile(SERVER_ROOT."cache/bigtree-hooks.json", BigTree::json($hooks));
		}

		/*
			Function: canAccessGroup
				Returns whether or not the logged in user can access a module group.
				Utility for form field types / views -- we already know module group permissions are enabled so we skip some overhead

			Parameters:
				module - A module entry.
				group - A group id.

			Returns:
				The permission level if the user can access this group, otherwise false.
		*/

		public function canAccessGroup($module,$group) {
			if ($this->Level > 0) {
				return "p";
			}

			$id = $module["id"];
			$level = false;

			if ($this->Permissions["module"][$id] && $this->Permissions["module"][$id] != "n") {
				$level = $this->Permissions["module"][$id];
			}

			if (is_array($this->Permissions["module_gbp"][$id])) {
				$gp = $this->Permissions["module_gbp"][$id][$group];
				if ($gp != "n") {
					if ($gp == "p" || !$level) {
						$level = $gp;
					}
				}
			}

			return $level;
		}

		/*
			Function: canModifyChildren
				Checks whether the logged in user can modify all child pages of a page.
				Assumes we already know that we're a publisher of the parent.

			Parameters:
				page - The page entry to check children for.

			Returns:
				true if the user can modify all the page children, otherwise false.
		*/

		public function canModifyChildren($page) {
			if ($this->Level > 0) {
				return true;
			}

			$q = sqlquery("SELECT id FROM bigtree_pages WHERE path LIKE '".sqlescape($page["path"])."%'");
			while ($f = sqlfetch($q)) {
				$perm = $this->Permissions["page"][$f["id"]];
				if ($perm == "n" || $perm == "e") {
					return false;
				}
			}

			return true;
		}

		/*
			Function: changePassword
				Changes a user's password via a password change hash and redirects to a success page.

			Parameters:
				hash - The unique hash generated by <forgotPassword>.
				password - The user's new password.

			See Also:
				<forgotPassword>

		*/

		public static function changePassword($hash, $password) {
			global $bigtree;

			$user = SQL::fetch("SELECT * FROM bigtree_users WHERE change_password_hash = ?", $hash);

			if (!$user) {
				return false;
			}

			SQL::update("bigtree_users", $user["id"], [
				"password" => password_hash(trim($password), PASSWORD_DEFAULT),
				"new_hash" => "on",
				"change_password_hash" => ""
			]);
			SQL::query("UPDATE bigtree_login_bans SET expires = DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE user = ?", $user["id"]);

			// Clean existing sessions
			SQL::delete("bigtree_sessions", ["logged_in_user" => $user["id"]]);
			SQL::delete("bigtree_user_sessions", ["email" => $user["email"]]);

			BigTree::redirect(($bigtree["config"]["force_secure_login"] ? str_replace("http://", "https://", ADMIN_ROOT) : ADMIN_ROOT)."login/reset-success/");
		}

		/*
			Function: checkAccess
				Determines whether the logged in user has access to a module or not.

			Parameters:
				module - Either a module id or module entry.
				action - Optionally, a module action array to also check levels against.

			Returns:
				true if the user can access the module, otherwise false.
		*/

		public function checkAccess($module,$action = false) {
			if (is_array($module)) {
				$module = $module["id"];
			}

			if (is_array($action) && $action["level"] > $this->Level) {
				return false;
			}

			if ($this->Level > 0) {
				return true;
			}

			if ($this->Permissions["module"][$module] && $this->Permissions["module"][$module] != "n") {
				return true;
			}

			if (isset($this->Permissions["module_gbp"])) {
				if (is_array($this->Permissions["module_gbp"][$module])) {
					foreach ($this->Permissions["module_gbp"][$module] as $p) {
						if ($p != "n") {
							return true;
						}
					}
				}
			}

			return false;
		}

		/*
			Function: checkPOSTError
				Checks if an error occurred during a POST and redirects back to the originating page with a session var.
		*/

		public function checkPOSTError() {
			global $bigtree;

			if (is_null($bigtree["php_boot_error"])) {
				return;
			}

			$error = false;
			$message = $bigtree["php_boot_error"]["message"];

			if (strpos($message, "POST Content-Length") !== false) {
				$error = "post_max_size";
			}

			if (strpos($message, "max_input_vars") !== false) {
				$error = "max_input_vars";
			}

			if ($error) {
				$_SESSION["bigtree_admin"]["post_error"] = $error;

				BigTree::redirect($_SERVER["HTTP_REFERER"]);
			}
		}

		/*
			Function: checkHTML
				Checks a block of HTML for broken links/images

			Parameters:
				relative_path - The starting path of the page containing the HTML (so that relative links, i.e. "good/" know where to begin)
				html - A string of HTML
				external - Whether to check external links (slow) or not

			Returns:
				An array containing two possible keys (a and img) which each could contain an array of errors.
		*/

		public static function checkHTML($relative_path,$html,$external = false) {
			if (!$html) {
				return array();
			}
			$errors = array();
			$doc = new DOMDocument();
			@$doc->loadHTML($html); // Silenced because the HTML could be invalid.
			// Check A tags.
			$links = $doc->getElementsByTagName("a");
			foreach ($links as $link) {
				$href = $link->getAttribute("href");
				$href = BigTreeCMS::replaceRelativeRoots($href);

				if ($href == WWW_ROOT || $href == STATIC_ROOT || $href == ADMIN_ROOT) {
					continue;
				}

				// See if the link matches something local
				$local = false;

				if (substr($href, 0, 4) == "http") {
					foreach (BigTreeCMS::$ReplaceableRootKeys as $local_key) {
						if (strpos($href, $local_key) === 0) {
							$local = true;
						}
					}
				}

				if ((substr($href,0,2) == "//" || substr($href,0,4) == "http") && !$local) {
					// External link, not much we can do but alert that it's dead
					if ($external) {
						if (!static::urlExists($href)) {
							$errors["a"][] = $href;
						}
					}
				} elseif (substr($href,0,6) == "ipl://") {
					if (!static::iplExists($href)) {
						$errors["a"][] = $href;
					}
				} elseif (substr($href,0,6) == "irl://") {
					if (!static::irlExists($href)) {
						$errors["a"][] = $href;
					}
				} elseif (substr($href,0,7) == "mailto:" || substr($href,0,1) == "#" || substr($href,0,5) == "data:" || substr($href,0,4) == "tel:") {
					// Don't do anything, it's a page mark, data URI, or email address
				} elseif (substr($href,0,4) == "http") {
					// It's a local hard link
					if (!static::urlExists($href)) {
						$errors["a"][] = $href;
					}
				} else {
					// Local file.
					$local = $relative_path.$href;
					if (!static::urlExists($local)) {
						$errors["a"][] = $local;
					}
				}
			}
			// Check IMG tags.
			$images = $doc->getElementsByTagName("img");
			foreach ($images as $image) {
				$href = $image->getAttribute("src");
				$href = BigTreeCMS::replaceRelativeRoots($href);

				// See if the link matches something local
				$local = false;

				if (substr($href, 0, 4) == "http") {
					foreach (BigTreeCMS::$ReplaceableRootKeys as $local_key) {
						if (strpos($href, $local_key) === 0) {
							$local = true;
						}
					}
				}

				if ((substr($href,0,2) == "//" || substr($href, 0, 4) == "http") && !$local) {
					// External link, not much we can do but alert that it's dead
					if ($external) {
						if (!static::urlExists($href)) {
							$errors["img"][] = $href;
						}
					}
				} elseif (substr($href,0,6) == "irl://") {
					if (!static::irlExists($href)) {
						$errors["img"][] = $href;
					}
				} elseif (substr($href,0,5) == "data:") {
					// Do nothing, it's a data URI
				} elseif (substr($href,0,4) == "http") {
					// It's a local hard link
					if (!static::urlExists($href)) {
						$errors["img"][] = $href;
					}
				} else {
					// Local file.
					$local = $relative_path.$href;
					if (!static::urlExists($local)) {
						$errors["img"][] = $local;
					}
				}
			}
			return $errors;
		}

		/*
			Function: clearCache
				Removes all page cache files in the cache directory.
		*/

		public static function clearCache() {
			$d = opendir(SERVER_ROOT."cache/");

			while ($f = readdir($d)) {
				if (substr($f, -5, 5) == ".page") {
					unlink(SERVER_ROOT."cache/".$f);
				}
			}
		}

		/*
			Function: clearDead404s
				Removes all 404s that don't have 301 redirects.
		*/

		public function clearDead404s() {
			sqlquery("DELETE FROM bigtree_404s WHERE redirect_url = ''");

			$this->track("bigtree_404s","All","Cleared Empty");
			static::growl("404 Report","Cleared 404s");
		}

		/*
			Function: convertTimestampFromUser
				Converts a timestamp from the logged in user's timezone frame of reference to the server's frame of reference

			Parameters:
				time - A timestamp readable by strtotime
				format - Return format, defaults to "Y-m-d H:i:s"

			Returns:
				An adjusted timestamp in Y-m-d H:i:s format
		*/

		function convertTimestampFromUser($time, $format = null) {
			if (!$this->Timezone) {
				return date("Y-m-d H:i:s", strtotime($time));
			}

			$time = strtotime($time);
			$date = date("Y-m-d H:i:s", $time);

			$user_tz = new DateTimeZone($this->Timezone);
			$system_tz = new DateTimeZone(date_default_timezone_get());

			$user_offset = $user_tz->getOffset(new DateTime($date));
			$system_offset = $system_tz->getOffset(new DateTime($date));

			$time += ($system_offset - $user_offset);

			return date($format ?: "Y-m-d H:i:s", $time);
		}

		/*
			Function: convertTimestampToUser
				Converts a timestamp from the logged in user's timezone frame of reference to the server's frame of reference

			Parameters:
				time - A timestamp readable by strtotime
				format - A date format (defaults to the $bigtree["config"]["date_format"] value)

			Returns:
				An adjusted timestamp
		*/

		function convertTimestampToUser($time, $format = null, $timezone = null) {
			global $bigtree;

			if (is_null($format)) {
				$format = !empty($bigtree["config"]["date_format"]) ? $bigtree["config"]["date_format"]." g:i a" : "Y-m-d H:i:s";
			}

			if (is_null($timezone)) {
				$timezone = $this->Timezone;
			}

			if (!$timezone) {
				return date($format, strtotime($time));
			}

			$time = strtotime($time);
			$date = date("Y-m-d H:i:s", $time);

			$user_tz = new DateTimeZone($timezone);
			$system_tz = new DateTimeZone(date_default_timezone_get());

			$user_offset = $user_tz->getOffset(new DateTime($date));
			$system_offset = $system_tz->getOffset(new DateTime($date));

			$time += ($user_offset - $system_offset);

			return date($format, $time);

		}

		/*
			Function: create301
				Creates a 301 redirect.

			Parameters:
				from - The 404 path
				to - The 301 target
				site_key - The site key for a multi-site environment (defaults to null)
		*/

		public function create301($from, $to, $site_key = null) {
			global $bigtree;

			// See if the from already exists
			$sanitized_input = $this->parse404SourceURL($from, $site_key);
			$from = $sanitized_input["url"];
			$get_vars = $sanitized_input["get_vars"];
			$to = sqlescape(htmlspecialchars($this->autoIPL(trim($to))));
			$existing = $this->getExisting404($from, $get_vars, $site_key);
			
			SQL::delete("bigtree_route_history", ["old_route" => $from]);

			if ($existing) {
				sqlquery("UPDATE bigtree_404s SET `redirect_url` = '$to' WHERE id = '".$existing["id"]."'");
				$this->track("bigtree_404s", $existing["id"], "updated");
			} else {
				if (!is_null($site_key)) {
					sqlquery("INSERT INTO bigtree_404s (`broken_url`, `get_vars`, `redirect_url`, `site_key`) VALUES ('$from', '$get_vars', '$to', '".sqlescape($site_key)."')");
				} else {
					sqlquery("INSERT INTO bigtree_404s (`broken_url`, `get_vars`, `redirect_url`) VALUES ('$from', '$get_vars', '$to')");
				}

				$this->track("bigtree_404s", sqlid(), "created");
			}
		}

		/*
			Function: createCallout
				Creates a callout and its files.

			Parameters:
				id - The id.
				name - The name.
				description - The description.
				level - Access level (0 for everyone, 1 for administrators, 2 for developers).
				resources - An array of resources.
				display_field - The field to use as the display field describing a user's callout
				display_default - The text string to use in the event the display_field is blank or non-existent
		*/

		public function createCallout($id,$name,$description,$level,$resources,$display_field,$display_default) {
			// Check to see if it's a valid ID
			if (!ctype_alnum(str_replace(array("-","_"),"",$id)) || strlen($id) > 127) {
				return false;
			}

			// If we're creating a new file, let's populate it with some convenience things to show what resources are available.
			$file_contents = '<?php
	/*
		Resources Available:
';

			$cached_types = $this->getCachedFieldTypes();
			$types = $cached_types["callouts"];

			$clean_resources = array();
			foreach ($resources as $resource) {
				// "type" is still a reserved keyword due to the way we save callout data when editing.
				if ($resource["id"] && $resource["id"] != "type") {
					$settings = json_decode($resource["settings"] ?: $resource["options"], true);
					$field = array(
						"id" => BigTree::safeEncode($resource["id"]),
						"type" => BigTree::safeEncode($resource["type"]),
						"title" => BigTree::safeEncode($resource["title"]),
						"subtitle" => BigTree::safeEncode($resource["subtitle"]),
						"settings" => $settings
					);

					// Backwards compatibility with BigTree 4.1 package imports
					foreach ($resource as $k => $v) {
						if (!in_array($k, array("id", "title", "subtitle", "type", "settings", "options"))) {
							$field["settings"][$k] = $v;
						}
					}

					$field["settings"] = BigTree::arrayFilterRecursive($field["settings"]);
					$clean_resources[] = $field;

					$file_contents .= '		"'.$resource["id"].'" = '.$resource["title"].' - '.$types[$resource["type"]]["name"]."\n";
				}
			}

			$file_contents .= '	*/
';

			// Clean up the post variables
			$callout = [
				"id" => BigTree::safeEncode($id),
				"name" => BigTree::safeEncode($name),
				"description" => BigTree::safeEncode($description),
				"level" => intval($level),
				"resources" => $clean_resources,
				"display_default" => BigTree::safeEncode($display_default),
				"display_field" => BigTree::safeEncode($display_field)
			];

			if (!file_exists(SERVER_ROOT."templates/callouts/".$id.".php")) {
				BigTree::putFile(SERVER_ROOT."templates/callouts/".$id.".php",$file_contents);
			}

			BigTreeJSONDB::incrementPosition("callouts");
			BigTreeJSONDB::insert("callouts", $callout);

			$this->track("jsondb -> callouts", $id, "created");

			return $id;
		}

		/*
			Function: createCalloutGroup
				Creates a callout group.

			Parameters:
				name - The name of the group.
				callouts - An array of callout IDs to assign to the group.

			Returns:
				The id of the newly created group.
		*/

		public function createCalloutGroup($name,$callouts) {
			sort($callouts);

			$id = BigTreeJSONDB::insert("callout-groups", [
				"name" => BigTree::safeEncode($name),
				"callouts" => $callouts
			]);
			$this->track("jsondb -> callout-groups",$id,"created");

			return $id;
		}

		/*
			Function: createFeed
				Creates a feed.

			Parameters:
				name - The name.
				description - The description.
				table - The data table.
				type - The feed type.
				settings - The feed type settings.
				fields - The fields.

			Returns:
				The route to the new feed.
		*/

		public function createFeed($name, $description, $table, $type, $settings, $fields) {
			if (!is_array($settings)) {
				$settings = array_filter((array) json_decode($settings, true));
			}

			// Get a unique route!
			$route = BigTreeCMS::urlify($name);
			$x = 2;
			$oroute = $route;

			while (BigTreeJSONDB::exists("feeds", $route, "route")) {
				$route = $oroute."-".$x;
				$x++;
			}

			$id = BigTreeJSONDB::insert("feeds", [
				"name" => BigTree::safeEncode($name),
				"description" => BigTree::safeEncode($description),
				"table" => $table,
				"type" => $type,
				"settings" => $settings,
				"fields" => $fields,
				"route" => $route
			]);
			
			$this->track("jsondb -> feeds", $id, "created");

			return $route;
		}

		/*
			Function: createFieldType
				Creates a field type and its files.

			Parameters:
				id - The id of the field type.
				name - The name.
				use_cases - Associate array of sections in which the field type can be used (i.e. array("pages" => "on", "modules" => "","callouts" => "","settings" => ""))
				self_draw - Whether this field type will draw its <fieldset> and <label> ("on" or a falsey value)
		*/

		public function createFieldType($id,$name,$use_cases,$self_draw) {
			// Check to see if it's a valid ID
			if (!ctype_alnum(str_replace(array("-","_"),"",$id)) || strlen($id) > 127) {
				return false;
			}

			BigTreeJSONDB::insert("field-types", [
				"id" => $id,
				"name" => BigTree::safeEncode($name),
				"use_cases" => $use_cases,
				"self_draw" => $self_draw ? "on" : null
			]);

			// Make the files for draw and process and settings if they don't exist.
			if (!file_exists(SERVER_ROOT."custom/admin/field-types/$id/draw.php")) {
				BigTree::putFile(SERVER_ROOT."custom/admin/field-types/$id/draw.php", '<?php
	/*
		When drawing a field type you are provided with the $field array with the following keys:
			"title" — The title given by the developer to draw as the label (drawn automatically)
			"subtitle" — The subtitle given by the developer to draw as the smaller part of the label (drawn automatically)
			"key" — The value you should use for the "name" attribute of your form field
			"value" — The existing value for this form field
			"id" — A unique ID you can assign to your form field for use in JavaScript
			"tabindex" — The current tab index you can use for the "tabindex" attribute of your form field
			"settings" — An array of settings provided by the developer
			"required" — A boolean value of whether this form field is required or not
	*/

	include BigTree::path("admin/field-types/text/draw.php");
');
				BigTree::setPermissions(SERVER_ROOT."custom/admin/field-types/$id/draw.php");
			}

			if (!file_exists(SERVER_ROOT."custom/admin/field-types/$id/process.php")) {
				BigTree::putFile(SERVER_ROOT."custom/admin/field-types/$id/process.php", '<?php
	/*
		When processing a field type you are provided with the $field array with the following keys:
			"key" — The key of the field (this could be the database column for a module or the ID of the template or callout resource)
			"settings" — An array of settings provided by the developer
			"input" — The end user\'s $_POST data input for this field
			"file_input" — The end user\'s uploaded files for this field in a normalized entry from the $_FILES array in the same formatting you\'d expect from "input"

		BigTree expects you to set $field["output"] to the value you wish to store. If you want to ignore this field, set $field["ignore"] to true.
		Almost all text that is meant for drawing on the front end is expected to be run through PHP\'s htmlspecialchars function as seen in the example below.
		If you intend to allow HTML tags you will want to run htmlspecialchars in your drawing file on your value and leave it off in the process file.
	*/

	$field["output"] = htmlspecialchars($field["input"]);
');
				BigTree::setPermissions(SERVER_ROOT."custom/admin/field-types/$id/process.php");
			}

			if (!file_exists(SERVER_ROOT."custom/admin/field-types/$id/settings.php")) {
				BigTree::putFile(SERVER_ROOT."custom/admin/field-types/$id/settings.php", '<?php
	/*
		This file should draw form fields to save for usage by your draw and process files.
		Field settings are set on a per instance basis.
		The $settings variable has the current settings for the instance of this field.
	*/');
				BigTree::setPermissions(SERVER_ROOT."custom/admin/field-types/$id/settings.php");
			}

			unlink(SERVER_ROOT."cache/bigtree-form-field-types.json");

			$this->track("jsondb -> field-types", $id, "created");

			return $id;
		}

		/*
			Function: createMessage
				Creates a message in message center.

			Parameters:
				subject - The subject line.
				message - The message.
				recipients - The recipients.
				in_response_to - The message being replied to.
		*/

		public function createMessage($subject,$message,$recipients,$in_response_to = 0) {
			// Clear tags out of the subject, sanitize the message body of XSS attacks.
			$subject = sqlescape(htmlspecialchars(strip_tags($subject)));
			$message = sqlescape(strip_tags($message,"<p><b><strong><em><i><a>"));
			$in_response_to = sqlescape($in_response_to);

			// We build the send_to field this way so that we don't have to create a second table of recipients.
			// Is it faster database wise using a LIKE over a JOIN? I don't know, but it makes for one less table.
			$send_to = "|";
			foreach ($recipients as $r) {
				// Make sure they actually put in a number and didn't try to screw with the $_POST
				$send_to .= intval($r)."|";
			}

			$send_to = sqlescape($send_to);

			sqlquery("INSERT INTO bigtree_messages (`sender`,`recipients`,`subject`,`message`,`date`,`response_to`) VALUES ('".$this->ID."','$send_to','$subject','$message',NOW(),'$in_response_to')");
		}

		/*
			Function: createModule
				Creates a module and its class file.

			Parameters:
				name - The name of the module.
				group - The group for the module.
				class - The module class to create.
				table - The table this module relates to.
				permissions - The group-based permissions.
				icon - The icon to use.
				route - Desired route to use (defaults to auto generating if this is left false).

			Returns:
				The new module id.
		*/

		public function createModule($name,$group,$class,$table,$permissions,$icon,$route = false) {
			// Find an available module route.
			$route = $route ?: BigTreeCMS::urlify($name);
			
			if (!ctype_alnum(str_replace("-", "", $route)) || strlen($route) > 127) {
				return false;
			}

			$id = BigTreeJSONDB::insert("modules" ,[
				"name" => BigTree::safeEncode($name),
				"route" => $this->getUniqueModuleRoute($route),
				"class" => $class,
				"group" => $group ?: null,
				"gbp" => $permissions,
				"icon" => $icon,
				"actions" => [],
				"embeddable-forms" => [],
				"forms" => [],
				"reports" => [],
				"views" => []
			]);
			
			if ($class) {
				// Create class module.
				$f = fopen(SERVER_ROOT."custom/inc/modules/$route.php","w");
				fwrite($f,"<?php\n");
				fwrite($f,"	class $class extends BigTreeModule {\n\n");
				fwrite($f,'		public $Table = "'.$table.'";'."\n\n");
				fwrite($f,"	}\n\n");
				fclose($f);
				BigTree::setPermissions(SERVER_ROOT."custom/inc/modules/$route.php");

				// Remove cached class list.
				unlink(SERVER_ROOT."cache/bigtree-module-class-list.json");
			}

			$this->track("jsondb -> modules", $id, "created");

			return $id;
		}

		/*
			Function: createModuleAction
				Creates a module action.

			Parameters:
				module - The module to create an action for.
				name - The name of the action.
				route - The action route.
				in_nav - Whether the action is in the navigation.
				icon - The icon class for the action.
				form - The associated form.
				view - The associated view.
				report - The associated report.
				level - The required access level.
				position - The position in navigation.

			Returns:
				The action's route.
		*/

		public function createModuleAction($module,$name,$route,$in_nav,$icon,$form = 0,$view = 0,$report = 0,$level = 0,$position = 0) {
			if ($route && (!ctype_alnum(str_replace("-", "", $route)) || strlen($route) > 127)) {
				return false;
			}

			$position = intval($position);
			$route = $this->uniqueModuleActionRoute($module, $route);
			$context = BigTreeJSONDB::getSubset("modules", $module);

			if ($position === 0) {
				$context->incrementPosition("actions");
			}

			$id = $context->insert("actions", [
				"route" => $route,
				"in_nav" => $in_nav ? "on" : "",
				"class" => $icon,
				"name" => BigTree::safeEncode($name),
				"form" => $form ?: null,
				"view" => $view ?: null,
				"report" => $report ?: null,
				"level" => intval($level),
				"position" => $position,
				"route"
			]);

			$this->track("jsondb -> module-actions", $id, "created");

			return $route;
		}

		/*
			Function: createModuleEmbedForm
				Creates an embeddable form.

			Parameters:
				module - The module ID that this form relates to.
				title - The title of the form.
				table - The table for the form data.
				fields - The form fields.
				hooks - An array of "pre", "post", and "publish" keys that can be function names to call
				default_position - Default position for entries to the form (if the view is positioned).
				default_pending - Whether the submissions to default to pending or not ("on" or "").
				css - URL of a CSS file to include.
				redirect_url - The URL to redirect to upon completion of submission.
				thank_you_message - The message to display upon completeion of submission.

			Returns:
				The embed code.
		*/

		public function createModuleEmbedForm($module,$title,$table,$fields,$hooks = array(),$default_position = "",$default_pending = "",$css = "",$redirect_url = "",$thank_you_message = "") {			
			$clean_fields = [];
			
			foreach ($fields as $key => $field) {
				$field["settings"] = json_decode($field["settings"], true);
				$field["settings"] = BigTree::arrayFilterRecursive($field["settings"]);
				$field["column"] = $key;

				$clean_fields[] = $field;
			}

			$modules = BigTreeJSONDB::getAll("modules");
			$exists = true;

			while ($exists) {
				$hash = uniqid();
				$exists = false;

				foreach ($modules as $module_loop) {
					foreach ($module_loop["embeddable-forms"] as $form) {
						if ($form["hash"] == $hash) {
							$exists = true;
						}
					}
				}
			}

			$context = BigTreeJSONDB::getSubset("modules", $module);
			$id = $context->insert("embeddable-forms", [
				"title" => BigTree::safeEncode($title),
				"table" => $table,
				"fields" => $clean_fields,
				"hooks" => is_array($hooks) ? $hooks : json_decode($hooks, true),
				"default_position" => $default_position,
				"default_pending" => $default_pending ? "on" : "",
				"css" => BigTree::safeEncode($css),
				"redirect_url" => BigTree::safeEncode($redirect_url),
				"thank_you_message" => $thank_you_message,
				"hash" => $hash
			]);

			$this->track("jsondb -> module-embeddable-forms", $id, "created");

			return htmlspecialchars('<div id="bigtree_embeddable_form_container_'.$id.'">'.BigTree::safeEncode($title).'</div>'."\n".'<script type="text/javascript" src="'.ADMIN_ROOT.'js/embeddable-form.js?id='.$id.'&hash='.$hash.'"></script>');
		}

		/*
			Function: createModuleForm
				Creates a module form.

			Parameters:
				module - The module ID that this form relates to.
				title - The title of the form.
				table - The table for the form data.
				fields - The form fields.
				hooks - An array of "pre", "post", and "publish" keys that can be function names to call
				default_position - Default position for entries to the form (if the view is positioned).
				return_view - The view to return to after completing the form.
				return_url - The alternative URL to return to after completing the form.
				tagging - Whether or not to enable tagging.
				open_graph - Whether to enable open graph attributes.

			Returns:
				The new form id.
		*/

		public function createModuleForm($module,$title,$table,$fields,$hooks = array(),$default_position = "",$return_view = false,$return_url = "",$tagging = "",$open_graph = "") {
			$clean_fields = [];

			if (is_string($hooks)) {
				$hooks = json_decode($hooks, true);
			}

			foreach ($fields as $key => $data) {
				$settings = $data["settings"] ?: $data["options"];
				$field = [
					"column" => $data["column"] ? $data["column"] : $key,
					"type" => BigTree::safeEncode($data["type"]),
					"title" => BigTree::safeEncode($data["title"]),
					"subtitle" => BigTree::safeEncode($data["subtitle"]),
					"settings" => BigTree::translateArray(is_array($settings) ? $settings : json_decode($settings, true))
				];
				
				$field["settings"] = BigTree::arrayFilterRecursive($field["settings"]);

				// Backwards compatibility with BigTree 4.1 package imports
				foreach ($data as $k => $v) {
					if (!in_array($k, array("title", "subtitle", "type", "options", "settings"))) {
						$field["settings"][$k] = $v;
					}
				}

				$clean_fields[] = $field;
			}

			$context = BigTreeJSONDB::getSubset("modules", $module);
			$id = $context->insert("forms", [
				"title" => BigTree::safeEncode($title),
				"table" => $table,
				"fields" => $clean_fields,
				"default_position" => $default_position,
				"return_view" => $return_view ?: null,
				"return_url" => $return_url,
				"tagging" => $tagging ? "on" : "",
				"open_graph" => $open_graph ? "on" : "",
				"hooks" => is_array($hooks) ? $hooks : json_decode($hooks, true),
			]);

			$this->track("jsondb -> module-forms",$id,"created");

			// Get related views for this table and update numeric status
			static::updateModuleViewColumnNumericStatusForTable($table);

			return $id;
		}

		/*
			Function: createModuleGroup
				Creates a module group.

			Parameters:
				name - The name of the group.

			Returns:
				The id of the newly created group.
		*/

		public function createModuleGroup($name) {
			// Get a unique route
			$x = 2;
			$route = BigTreeCMS::urlify($name);
			$oroute = $route;

			while (BigTreeJSONDB::exists("module-groups", $route, "route")) {
				$route = $oroute."-".$x;
				$x++;
			}

			$id = BigTreeJSONDB::insert("module-groups", [
				"name" => BigTree::safeEncode($name),
				"route" => $route
			]);

			$this->track("jsondb -> module-groups",$id,"created");

			return $id;
		}

		/*
			Function: createModuleReport
				Creates a module report and the associated module action.

			Parameters:
				module - The module ID that this report relates to.
				title - The title of the report.
				table - The table for the report data.
				type - The type of report (csv or view).
				filters - The filters a user can use to create the report.
				fields - The fields to show in the CSV export (if type = csv).
				parser - An optional parser function to run on the CSV export data (if type = csv).
				view - A module view ID to use (if type = view).

			Returns:
				The id of the report.
		*/

		public function createModuleReport($module,$title,$table,$type,$filters,$fields = "",$parser = "",$view = "") {
			$context = BigTreeJSONDB::getSubset("modules", $module);
			$id = $context->insert("reports", [
				"title" => BigTree::safeEncode($title),
				"table" => $table,
				"type" => $type,
				"filters" => $filters,
				"fields" => $fields,
				"parser" => $parser,
				"view" => $view ?: null
			]);

			$this->track("jsondb -> module-reports", $id, "created");

			return $id;
		}

		/*
			Function: createModuleView
				Creates a module view.

			Parameters:
				module - The module ID that this view relates to.
				title - View title.
				description - Description.
				table - Data table.
				type - View type.
				settings - View settings array.
				fields - Field array.
				actions - Actions array.
				related_form - Form ID to handle edits.
				preview_url - Optional preview URL.

			Returns:
				The id for view.
		*/

		public function createModuleView($module,$title,$description,$table,$type,$settings,$fields,$actions,$related_form,$preview_url = "") {
			$context = BigTreeJSONDB::getSubset("modules", $module);
			$id = $context->insert("views", [
				"title" => BigTree::safeEncode($title),
				"description" => BigTree::safeEncode($description),
				"table" => $table,
				"type" => $type,
				"settings" => $settings,
				"fields" => $fields,
				"actions" => $actions,
				"related_form" => $related_form ?: null,
				"preview_url" => BigTree::safeEncode($preview_url)
			]);
			
			static::updateModuleViewColumnNumericStatusForTable($table);
			$this->track("jsondb -> module-views", $id, "created");

			return $id;
		}

		/*
			Function: createPage
				Creates a page.
				Does not check permissions.

			Parameters:
				data - An array of page information.
				publishing_change - Set to change ID if publishing a change (causes audit trail to reflect original user as author, defaults false)

			Returns:
				The id of the newly created page.
		*/

		public function createPage($data, $publishing_change = false) {
			// Defaults
			$parent = 0;
			$title = $nav_title = $meta_description = $external = $template = $in_nav = "";
			$seo_invisible = $publish_at = $expire_at = $trunk = $new_window = $max_age = null;
			$resources = array();

			// Loop through the posted data, make sure no session hijacking is done.
			foreach ($data as $key => $val) {
				if (substr($key,0,1) != "_") {
					$$key = $val;
				}
			}

			// If there's an external link, make sure it's a relative URL
			if ($external) {
				$external = $this->makeIPL($external);
			}

			// Who knows what they may have put in for a route, so we're not going to use the sqlescape version.
			$route = $data["route"];
			
			if (!$route) {
				// If they didn't specify a route use the navigation title
				$route = BigTreeCMS::urlify($data["nav_title"]);
			} else {
				// Otherwise sanitize the one they did provide.
				$route = BigTreeCMS::urlify($route);
			}

			// We need to figure out a unique route for the page. Make sure it doesn't match a directory in /site/
			$original_route = $route;
			$x = 2;

			// Reserved paths.
			if ($parent == 0) {
				while (file_exists(SERVER_ROOT."site/".$route."/")) {
					$route = $original_route."-".$x;
					$x++;
				}

				while (in_array($route,static::$ReservedTLRoutes)) {
					$route = $original_route."-".$x;
					$x++;
				}
			}

			// Make sure it doesn't have the same route as any of its siblings.
			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_pages WHERE `route` = '$route' AND parent = '$parent'"));
			
			while ($f) {
				$route = $original_route."-".$x;
				$f = sqlfetch(sqlquery("SELECT * FROM bigtree_pages WHERE `route` = '$route' AND parent = '$parent'"));
				$x++;
			}

			// Make sure route isn't longer than 255
			$route = substr($route,0,255);

			// If we have a parent, get the full navigation path, otherwise, just use this route as the path since it's top level.
			if ($parent) {
				$path = $this->getFullNavigationPath($parent)."/".$route;
			} else {
				$path = $route;
			}

			// If we set a publish at date, make it the proper MySQL format.
			if ($publish_at && $publish_at != "NULL") {
				$publish_at = $this->convertTimestampFromUser($publish_at);
			} else {
				$publish_at = null;
			}

			// If we set an expiration date, make it the proper MySQL format.
			if ($expire_at && $expire_at != "NULL") {
				$expire_at = $this->convertTimestampFromUser($expire_at);
			} else {
				$expire_at = null;
			}

			// Set the trunk flag back to no if the user isn't a developer
			if ($this->Level < 2) {
				$trunk = "";
			}

			$insert = [
				"trunk" => $trunk ? "on" : "",
				"parent" => $parent,
				"nav_title" => BigTree::safeEncode($nav_title),
				"route" => $route,
				"path" => $path,
				"in_nav" => $in_nav,
				"title" => BigTree::safeEncode($title),
				"template" => $template,
				"external" => BigTree::safeEncode($external),
				"new_window" => $new_window,
				"resources" => $resources,
				"meta_description" => BigTree::safeEncode($meta_description),
				"seo_invisible" => $seo_invisible ? "on" : "",
				"last_edited_by" => $this->ID,
				"created_at" => "NOW()",
				"updated_at" => "NOW()",
				"expire_at" => $expire_at,
				"publish_at" => $publish_at,
				"max_age" => $max_age
			];

			if ($publishing_change) {
				$pending_change = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $publishing_change);

				if ($pending_change) {
					$changes = BigTree::untranslateArray(json_decode($pending_change["changes"], true));
					$exact = true;

					foreach ($changes as $key => $value) {
						if ($key == "route" && empty($value)) {
							continue;
						}

						if (isset($insert[$key]) && $insert[$key] != $value) {
							$exact = false;
						}
					}

					if ($exact) {
						$insert["last_edited_by"] = $pending_change["user"];
					}

					SQL::delete("bigtree_pending_changes", $publishing_change);
				}
			} else {
				$pending_change = false;
			}

			// Make the page!
			$id = SQL::insert("bigtree_pages", $insert);

			// Audit trail
			if ($pending_change && $pending_change["user"] != $this->ID && $exact) {	
				$this->track("bigtree_pages", $id, "created via publisher", $pending_change["user"]);
				$this->track("bigtree_pages", $id, "published");
			} else {
				$this->track("bigtree_pages", $id, "created");
			}

			// Handle tags
			if (is_array($data["_tags"])) {
				$data["_tags"] = array_unique($data["_tags"]);

				foreach ($data["_tags"] as $tag) {
					sqlquery("INSERT INTO bigtree_tags_rel (`table`,`entry`,`tag`) VALUES ('bigtree_pages','$id','$tag')");
				}
			}

			if (is_array($data["_tags"]) && count($data["_tags"])) {
				$this->updateTagReferenceCounts($data["_tags"]);
			}

			// Handle open graph
			$this->handleOpenGraph("bigtree_pages", $id, $data["_open_graph_"]);

			// See if this template has a publish hook
			$template = BigTreeCMS::getTemplate($insert["template"]);

			if (!empty($template["hooks"]["publish"])) {
				call_user_func($template["hooks"]["publish"], "bigtree_pages", $id, $insert, [], $data["_tags"], $data["_open_graph_"]);
			}

			// If there was an old page that had previously used this path, dump its history so we can take over the path.
			sqlquery("DELETE FROM bigtree_route_history WHERE old_route = '$path'");

			// Dump the cache, we don't really know how many pages may be showing this now in their nav.
			$this->clearCache();

			// Let search engines know this page now exists.
			$this->pingSearchEngines();

			return $id;
		}

		/*
			Function: createPendingChange
				Creates a pending change.

			Parameters:
				table - The table the change applies to.
				item_id - The entry the change applies to's id.
				changes - The changes to the fields in the entry.
				mtm_changes - Many to Many changes.
				tags_changes - Tags changes.
				module - The module id for the change.

			Returns:
				The change id.
		*/

		public function createPendingChange($table,$item_id,$changes,$mtm_changes = array(),$tags_changes = array(),$module = "") {
			$table = sqlescape($table);
			$item_id = ($item_id !== false) ? "'".sqlescape($item_id)."'" : "NULL";
			$changes = BigTree::json($changes,true);
			$mtm_changes = BigTree::json($mtm_changes,true);
			$tags_changes = BigTree::json($tags_changes,true);
			$module = sqlescape($module);

			sqlquery("INSERT INTO bigtree_pending_changes (`user`,`date`,`table`,`item_id`,`changes`,`mtm_changes`,`tags_changes`,`module`) VALUES ('".$this->ID."',NOW(),'$table',$item_id,'$changes','$mtm_changes','$tags_changes','$module')");
			
			return sqlid();
		}

		/*
			Function: createPendingPage
				Creates a pending page entry in bigtree_pending_changes

			Parameters:
				data - An array of page information.

			Returns:
				The id of the pending change.
		*/

		public function createPendingPage($data) {
			// Make a relative URL for external links.
			if ($data["external"]) {
				$data["external"] = $this->makeIPL($data["external"]);
			}

			// Make the nav title, title, external link, keywords, and description htmlspecialchar'd for displaying on the front end / the form again.
			$data["nav_title"] = htmlspecialchars($data["nav_title"]);
			$data["title"] = htmlspecialchars($data["title"]);
			$data["external"] = htmlspecialchars($data["external"]);
			$data["meta_description"] = htmlspecialchars($data["meta_description"]);

			// Set the trunk flag back to no if the user isn't a developer
			if ($this->Level < 2) {
				$data["trunk"] = "";
			} else {
				$data["trunk"] = sqlescape($data["trunk"]);
			}

			$parent = sqlescape($data["parent"]);

			// Handle open graph and tags
			$open_graph = $this->handleOpenGraph("bigtree_pages", null, $data["_open_graph_"], true);
			$tags = array_unique($data["_tags"]) ?: "[]";

			// Remove POST vars that shouldn't be stored
			unset($data["MAX_FILE_SIZE"]);
			unset($data["ptype"]);
			unset($data["_open_graph_"]);
			unset($data["_tags"]);

			// Convert times from user's timezone
			if ($data["publish_at"] && $data["publish_at"] != "NULL") {
				$data["publish_at"] = $this->convertTimestampFromUser($data["publish_at"]);
			}

			if ($data["expire_at"] && $data["expire_at"] != "NULL") {
				$data["expire_at"] = $this->convertTimestampFromUser($data["expire_at"]);
			}

			$id = SQL::insert("bigtree_pending_changes", [
				"user" => $this->ID,
				"date" => "NOW()",
				"title" => "New Page Created",
				"table" => "bigtree_pages",
				"changes" => $data,
				"tags_changes" => $tags,
				"open_graph_changes" => $open_graph,
				"type" => "NEW",
				"module" => "",
				"pending_page_parent" => $parent
			]);

			// Audit trail
			$this->track("bigtree_pages","p$id","created-pending");

			return $id;
		}

		/*
			Function: createResource
				Creates a resource.

			Parameters:
				folder - The folder to place it in.
				file - The file path or a video URL.
				name - The file name.
				type - "file", "image", or "video"
				crops - An array of crop prefixes
				thumbs - An array of thumb prefixes
				video_data - An array of video data
				metadata - An array of metadata

			Returns:
				The new resource id.
		*/

		public function createResource($folder, $file, $name, $type = "file", $crops = [], $thumbs = [], $video_data = [], $metadata = []) {
			$perm = $this->getResourceFolderPermission($folder);

			if ($perm != "p") {
				die("You don't have permission to create a resource in the chosen folder.");
			}

			$width = null;
			$height = null;

			if ($type != "video") {
				$storage = new BigTreeStorage;
				$location = $storage->Cloud ? "cloud" : "local";
				$file_extension = pathinfo($file, PATHINFO_EXTENSION);

				// Local storage will let us lookup file size and md5 already
				if ($location == "local") {
					$file_path = str_replace(STATIC_ROOT, SITE_ROOT, BigTreeCMS::replaceRelativeRoots($file));
				} else {
					$file_path = SITE_ROOT."files/temporary/".$this->ID."/".uniqid(true).".".$file_extension;
					BigTree::copyFile($file, $file_path);
				}

				$file_size = filesize($file_path);
				$md5 = md5($file_path);
				$mimetype = function_exists("mime_content_type") ? mime_content_type($file_path) : "";

				if ($type == "image") {
					list($width, $height) = getimagesize($file_path);
				}

				if ($location != "local") {
					unlink($file_path);
				}
			} else {
				$location = $video_data["service"];
				$file_extension = "video";
				$name = $video_data["title"];
				$file = $video_data["url"];
				$file_size = null;
				$md5 = null;
				$mimetype = null;
			}

			$data = [
				"folder" => $folder ?: null,
				"file" => $file,
				"name" => BigTree::safeEncode($name),
				"type" => $file_extension,
				"mimetype" => $mimetype,
				"is_image" => ($type == "image") ? "on" : "",
				"is_video" => ($type == "video") ? "on" : "",
				"md5" => $md5,
				"size" => $file_size,
				"width" => $width,
				"height" => $height,
				"date" => date("Y-m-d H:i:s"),
				"crops" => $crops,
				"thumbs" => $thumbs,
				"location" => $location,
				"video_data" => $video_data,
				"metadata" => $metadata
			];

			$id = SQL::insert("bigtree_resources", BigTree::translateArray($data));
			$this->track("bigtree_resources",$id,"created");

			return $id;
		}

		/*
			Function: createResourceFolder
				Creates a resource folder.
				Checks permissions.

			Parameters:
				parent - The parent folder.
				name - The name of the new folder.

			Returns:
				The new folder id.
		*/

		public function createResourceFolder($parent,$name) {
			$perm = $this->getResourceFolderPermission($parent);
			if ($perm != "p") {
				die("You don't have permission to make a folder here.");
			}

			$parent = sqlescape($parent);
			$name = sqlescape(htmlspecialchars(trim($name)));

			if (!$name) {
				return false;
			}

			sqlquery("INSERT INTO bigtree_resource_folders (`name`,`parent`) VALUES ('$name','$parent')");
			$id = sqlid();
			$this->track("bigtree_resource_folders",$id,"created");

			return $id;
		}

		/*
			Function: createSetting
				Creates a setting.

			Parameters:
				data - An array of settings information. Available fields: "id", "name", "description", "type", "settings", locked", "module", "encrypted", "system"

			Returns:
				True if successful, false if a setting already exists with the ID given.
		*/

		public function createSetting($data) {
			$extension = $data["extension"] ?: null;
			$id = $data["id"];

			// If an extension is creating a setting, make it a reference back to the extension
			if (defined("EXTENSION_ROOT")) {
				$extension = rtrim(str_replace(SERVER_ROOT."extensions/","",EXTENSION_ROOT),"/");
				
				// Don't append extension again if it's already being called via the namespace
				if (strpos($id, "$extension*") === false) {
					$id = "$extension*$id";
				}
			}

			if (strpos($id, "bigtree-internal-") === 0) {
				return false;
			}

			if (SQL::exists("bigtree_settings", $id) || BigTreeJSONDB::exists("settings", $id)) {
				return false;
			}

			$settings = $data["settings"] ?: $data["options"];

			if (!empty($settings)) {
				if (is_string($settings)) {
					$settings = json_decode($settings, true);
				}

				foreach ($settings as $key => $value) {
					if (($key == "settings" || $key == "options") && is_string($value)) {
						$settings["settings"][$key] = json_decode($value, true);
					}
				}

				$settings = BigTree::arrayFilterRecursive($settings);
			}

			BigTreeJSONDB::insert("settings", [
				"id" => $data["id"],
				"name" => BigTree::safeEncode($data["name"]),
				"description" => $data["description"],
				"type" => $data["type"],
				"settings" => $settings,
				"locked" => !empty($data["locked"]) ? "on" : "",
				"system" => !empty($data["system"]) ? "on" : "",
				"encrypted" => !empty($data["encrypted"]) ? "on" : "",
				"extension" => $extension
			]);
			SQL::insert("bigtree_settings", ["id" => $data["id"], "encrypted" => !empty($data["encrypted"]) ? "on" : "", "value" => ""]);

			$this->track("jsondb -> settings", $id, "created");

			return true;
		}

		/*
			Function: createTag
				Creates a new tag, or returns the id of an existing one.

			Parameters:
				tag - The tag.

			Returns:
				If the tag exists, returns the existing tag's id.
				Otherwise, returns the new tag id.
		*/

		public function createTag($tag) {
			$tag = strtolower(html_entity_decode(trim($tag)));
			// Check if the tag exists already.
			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_tags WHERE tag = '".sqlescape($tag)."'"));

			if (!$f) {
				$meta = metaphone($tag);
				$route = BigTreeCMS::urlify($tag);
				$oroute = $route;
				$x = 2;
				while ($f = sqlfetch(sqlquery("SELECT * FROM bigtree_tags WHERE route = '$route'"))) {
					$route = $oroute."-".$x;
					$x++;
				}
				sqlquery("INSERT INTO bigtree_tags (`tag`,`metaphone`,`route`) VALUES ('".sqlescape($tag)."','$meta','$route')");
				$id = sqlid();
			} else {
				$id = $f["id"];
			}

			$this->track("bigtree_tags",$id,"created");

			return $id;
		}

		/*
			Function: createTemplate
				Creates a template and its default files/directories.

			Parameters:
				id - Id for the template.
				name - Name
				routed - Basic ("") or Routed ("on")
				level - Access level (0 for everyone, 1 for administrators, 2 for developers)
				module - Related module id
				resources - An array of resources
				hooks - An array of hooks
		*/

		public function createTemplate($id,$name,$routed,$level,$module,$resources,$hooks = []) {
			// Check to see if it's a valid ID
			if (!ctype_alnum(str_replace(array("-","_"),"",$id)) || strlen($id) > 127) {
				return false;
			}

			// If we're creating a new file, let's populate it with some convenience things to show what resources are available.
			$file_contents = "<?php\n	/*\n		Resources Available:\n";

			$types = $this->getCachedFieldTypes();
			$types = $types["templates"];

			$clean_resources = [];

			foreach ($resources as $resource) {
				if ($resource["id"]) {
					$settings = json_decode($resource["settings"] ?: $resource["options"], true);
					$field = array(
						"id" => BigTree::safeEncode($resource["id"]),
						"type" => BigTree::safeEncode($resource["type"]),
						"title" => BigTree::safeEncode($resource["title"]),
						"subtitle" => BigTree::safeEncode($resource["subtitle"]),
						"settings" => $settings
					);

					// Backwards compatibility with BigTree 4.1 package imports
					foreach ($resource as $k => $v) {
						if (!in_array($k,array("id", "title", "subtitle", "type", "options", "settings"))) {
							$field["settings"][$k] = $v;
						}
					}

					$field["settings"] = BigTree::arrayFilterRecursive($field["settings"]);
					$clean_resources[] = $field;

					$file_contents .= '		$'.$resource["id"].' = '.$resource["title"].' - '.$types[$resource["type"]]["name"]."\n";
				}
			}

			$file_contents .= '	*/
';
			if (!count($clean_resources)) {
				$file_contents = "";
			}

			if ($routed == "on") {
				if (!file_exists(SERVER_ROOT."templates/routed/".$id."/default.php")) {
					BigTree::putFile(SERVER_ROOT."templates/routed/".$id."/default.php",$file_contents);
				}
			} elseif (!file_exists(SERVER_ROOT."templates/basic/".$id.".php")) {
				BigTree::putFile(SERVER_ROOT."templates/basic/".$id.".php",$file_contents);
			}

			// Increase the count of the positions on all templates by 1 so that this new template is for sure in last position.
			BigTreeJSONDB::incrementPosition("templates");
			BigTreeJSONDB::insert("templates", [
				"id" => $id,
				"name" => BigTree::safeEncode($name),
				"module" => $module,
				"resources" => $clean_resources,
				"level" => $level,
				"routed" => $routed,
				"hooks" => is_array($hooks) ? $hooks : array_filter((array) json_decode($hooks, true))
			]);

			$this->track("jsondb -> templates", $id, "created");

			return $id;
		}

		/*
			Function: createUser
				Creates a user.
				Checks for developer access.

			Parameters:
				data - An array of user data. ("email", "password", "name", "company", "level", "permissions","alerts")

			Returns:
				id of the newly created user or false if a user already exists with the provided email.
		*/

		public function createUser($data) {
			global $bigtree;

			// See if the user already exists
			if (SQL::exists("bigtree_users", ["email" => $data["email"]])) {
				return false;
			}

			// Don't allow the level to be set higher than the logged in user's level
			$level = intval($data["level"]);

			if ($level > $this->Level) {
				$level = $this->Level;
			}

			$insert = [
				"email" => BigTree::safeEncode($data["email"]),
				"level" => $level,
				"name" => BigTree::safeEncode($data["name"]),
				"company" => BigTree::safeEncode($data["company"]),
				"daily_digest" => !empty($data["daily_digest"]) ? "on" : "",
				"alerts" => is_array($data["alerts"]) ? $data["alerts"] : [],
				"permissions" => is_array($data["permissions"]) ? $data["permissions"] : [],
				"timezone" => $data["timezone"] ?: ""
			];

			// Only store a password if we aren't sending an invitation
			if (empty($bigtree["security-policy"]["password"]["invitations"])) {
				$insert["password"] = password_hash(trim($data["password"]), PASSWORD_DEFAULT);
				$insert["new_hash"] = "on";
			} else {
				$insert["change_password_hash"] = $hash = BigTree::randomString(64);

				while (SQL::exists("bigtree_users", ["change_password_hash" => $insert["change_password_hash"]])) {
					$insert["change_password_hash"] = BigTree::randomString(64);
				}
				
				$site_title = SQL::fetchSingle("SELECT `nav_title` FROM `bigtree_pages` WHERE id = 0");
				$login_root = ($bigtree["config"]["force_secure_login"] ? str_replace("http://","https://",ADMIN_ROOT) : ADMIN_ROOT)."login/";

				$html = file_get_contents(BigTree::path("admin/email/welcome.html"));
				$html = str_ireplace("{www_root}", WWW_ROOT, $html);
				$html = str_ireplace("{admin_root}", ADMIN_ROOT, $html);
				$html = str_ireplace("{site_title}", $site_title, $html);
				$html = str_ireplace("{person}", $this->Name, $html);
				$html = str_ireplace("{reset_link}", $login_root."reset-password/$hash/?welcome", $html);

				$email_service = new BigTreeEmailService;

				// Only use a custom email service if a from email has been set
				if ($email_service->Settings["bigtree_from"]) {
					$reply_to = "no-reply@".(isset($_SERVER["HTTP_HOST"]) ? str_replace("www.", "", $_SERVER["HTTP_HOST"]) : str_replace(array("http://www.", "https://www.", "http://", "https://"), "", DOMAIN));
					$email_service->sendEmail("$site_title - Set Your Password", $html, $insert["email"], $email_service->Settings["bigtree_from"], "BigTree CMS", $reply_to);
				} else {
					BigTree::sendEmail($insert["email"], "$site_title - Set Your Password", $html);
				}
			}

			$id = SQL::insert("bigtree_users", $insert);
			$this->track("bigtree_users",$id,"created");

			return $id;
		}

		/*
			Function: deallocateResources
				Removes resource allocation from a deleted entry.

			Parameters:
				table - The table of the entry
				entry - The ID of the entry
		*/

		public static function deallocateResources($table, $entry) {
			SQL::delete("bigtree_resource_allocation", ["table" => $table, "entry" => $entry]);
		}

		/*
			Function: delete404
				Deletes a 404 error.
				Checks permissions.

			Parameters:
				id - The id of the reported 404.
		*/

		public function delete404($id) {
			$this->requireLevel(1);
			$id = sqlescape($id);
			sqlquery("DELETE FROM bigtree_404s WHERE id = '$id'");
			$this->track("bigtree_404s",$id,"deleted");
		}

		/*
			Function: deleteCallout
				Deletes a callout and removes its file.

			Parameters:
				id - The id of the callout.
		*/

		public function deleteCallout($id) {
			// Delete the callout and its related file
			BigTreeJSONDB::delete("callouts", $id);
			unlink(SERVER_ROOT."templates/callouts/$id.php");

			// Remove the callout from any groups it lives in
			$groups = $this->getCalloutGroups();

			foreach ($groups as $group) {
				foreach ($group["callouts"] as $callout_index => $callout_id) {
					if ($callout_id == $id) {
						unset($group["callouts"][$callout_index]);
						BigTreeJSONDB::update("callout-groups", $group["id"], $group);
					}
				}
			}

			// Track deletion
			$this->track("jsondb -> callouts", $id, "deleted");
		}

		/*
			Function: deleteCalloutGroup
				Deletes a callout group.

			Parameters:
				id - The id of the callout group.
		*/

		public function deleteCalloutGroup($id) {
			BigTreeJSONDB::delete("callout-groups", $id);
			$this->track("jsondb -> callout-groups",$id,"deleted");
		}

		/*
			Function: deleteExtension
				Uninstalls an extension from BigTree and removes its related components and files.

			Parameters:
				id - The extension ID.
		*/

		public function deleteExtension($id) {
			$extension = $this->getExtension($id);

			if (!$extension) {
				return;
			}

			$j = $extension["manifest"];

			// Don't delete the whole directory if the manifest fails to load
			if ($j["id"]) {
				// Delete site files
				BigTree::deleteDirectory(SITE_ROOT."extensions/".$j["id"]."/");
				// Delete extensions directory
				BigTree::deleteDirectory(SERVER_ROOT."extensions/".$j["id"]."/");
			}

			// Delete components
			foreach ($j["components"] as $type => $list) {
				if ($type == "tables") {
					// Turn off foreign key checks since we're going to be dropping tables.
					SQL::query("SET SESSION foreign_key_checks = 0");

					foreach ($list as $table => $create_statement) {
						SQL::query("DROP TABLE IF EXISTS `$table`");
					}

					SQL::query("SET SESSION foreign_key_checks = 1");
				} else {
					foreach ($list as $item) {
						BigTreeJSONDB::delete(str_replace("_", "-", $type), $item["id"]);
					}
				}
			}

			// Delete extension entry
			BigTreeJSONDB::delete("extensions", $id);

			$this->track("jsondb -> extensions", $extension["id"], "deleted");
		}

		/*
			Function: deleteFeed
				Deletes a feed.

			Parameters:
				id - The id of the feed.
		*/

		public function deleteFeed($id) {
			BigTreeJSONDB::delete("feeds", $id);
			$this->track("jsondb -> feeds", $id, "deleted");
		}

		/*
			Function: deleteFieldType
				Deletes a field type and erases its files.

			Parameters:
				id - The id of the field type.
		*/

		public function deleteFieldType($id) {
			@unlink(SERVER_ROOT."custom/admin/form-field-types/draw/$id.php");
			@unlink(SERVER_ROOT."custom/admin/form-field-types/process/$id.php");
			@unlink(SERVER_ROOT."custom/admin/ajax/developer/field-options/$id.php");
			@unlink(SERVER_ROOT."cache/bigtree-form-field-types.json");
			@unlink(SERVER_ROOT."custom/admin/field-types/$id/draw/.php");
			@unlink(SERVER_ROOT."custom/admin/field-types/$id/process.php");
			@unlink(SERVER_ROOT."custom/admin/field-types/$id/settings.php");
			@unlink(SERVER_ROOT."custom/admin/field-types/$id/");
			@unlink(SERVER_ROOT."cache/bigtree-form-field-types.json");

			BigTreeJSONDB::delete("field-types", $id);
			$this->track("jsondb -> field-types", $id, "deleted");
		}

		/*
			Function: deleteModule
				Deletes a module.

			Parameters:
				id - The id of the module.
		*/

		public function deleteModule($id) {
			$module = $this->getModule($id);

			unlink(SERVER_ROOT."custom/inc/modules/".$module["route"].".php");
			BigTree::deleteDirectory(SERVER_ROOT."custom/admin/modules/".$module["route"]."/");
			BigTreeJSONDB::delete("modules", $id);

			$this->track("jsondb -> modules", $id, "deleted");
		}

		/*
			Function: deleteModuleAction
				Deletes a module action.
				Also deletes the related form or view if no other action is using it.

			Parameters:
				id - The id of the action to delete.
		*/

		public function deleteModuleAction($id) {
			$modules = BigTreeJSONDB::getAll("modules");

			foreach ($modules as $module) {
				foreach ($module["actions"] as $action) {
					if ($action["id"] == $id) {
						$context = BigTreeJSONDB::getSubset("modules", $module["id"]);
						$context->delete("actions", $id);

						if ($action["form"]) {
							$found = false;

							foreach ($module["actions"] as $other_action) {
								if ($other_action["form"] == $action["form"]) {
									$found = true;
								}
							}

							if (!$found) {
								$context->delete("forms", $action["form"]);
							}
						 } elseif ($action["view"]) {
							$found = false;

							foreach ($module["actions"] as $other_action) {
								if ($other_action["view"] == $action["view"]) {
									$found = true;
								}
							}

							if (!$found) {
								$context->delete("views", $action["view"]);
							}
						} elseif ($action["report"]) {
							$found = false;

							foreach ($module["actions"] as $other_action) {
								if ($other_action["report"] == $action["report"]) {
									$found = true;
								}
							}

							if (!$found) {
								$context->delete("reports", $action["report"]);
							}
						}
					}
				}
			}

			$this->track("jsondb -> module-actions", $id, "deleted");
		}

		/*
			Function: deleteModuleEmbedForm
				Deletes an embeddable module form.

			Parameters:
				id - The id of the embeddable form.
		*/

		public function deleteModuleEmbedForm($id) {
			$modules = BigTreeJSONDB::getAll("modules");

			foreach ($modules as $module) {
				foreach ($module["embeddable-forms"] as $form) {
					if ($form["id"] == $id) {
						$context = BigTreeJSONDB::getSubset("modules", $module["id"]);
						$context->delete("embeddable-forms", $id);
					}
				}
			}

			$this->track("jsondb -> module-embeddable-forms", $id, "deleted");
		}

		/*
			Function: deleteModuleForm
				Deletes a module form and its related actions.

			Parameters:
				id - The id of the module form.
		*/

		public function deleteModuleForm($id) {
			$modules = BigTreeJSONDB::getAll("modules");

			foreach ($modules as $module) {
				foreach ($module["forms"] as $form) {
					if ($form["id"] == $id) {
						$context = BigTreeJSONDB::getSubset("modules", $module["id"]);
						$context->delete("forms", $id);

						foreach ($module["actions"] as $action) {
							if ($action["form"] == $id) {
								$context->delete("actions", $action["id"]);
							}
						}
					}
				}
			}

			$this->track("jsondb -> module-forms", $id, "deleted");
		}

		/*
			Function: deleteModuleGroup
				Deletes a module group. Sets modules in the group to Misc.

			Parameters:
				id - The id of the module group.
		*/

		public function deleteModuleGroup($id) {
			BigTreeJSONDB::delete("module-groups", $id);
			$this->track("jsondb -> module-groups",$id,"deleted");
		}

		/*
			Function: deleteModuleReport
				Deletes a module report and its related actions.

			Parameters:
				id - The id of the module report.
		*/

		public function deleteModuleReport($id) {
			$modules = BigTreeJSONDB::getAll("modules");

			foreach ($modules as $module) {
				foreach ($module["reports"] as $report) {
					if ($report["id"] == $id) {
						$context = BigTreeJSONDB::getSubset("modules", $module["id"]);
						$context->delete("reports", $id);

						foreach ($module["actions"] as $action) {
							if ($action["report"] == $id) {
								$context->delete("actions", $action["id"]);
							}
						}
					}
				}
			}

			$this->track("jsondb -> module-reports", $id, "deleted");
		}

		/*
			Function: deleteModuleView
				Deletes a module view and its related actions.

			Parameters:
				id - The id of the module view.
		*/

		public function deleteModuleView($id) {
			$modules = BigTreeJSONDB::getAll("modules");

			foreach ($modules as $module) {
				foreach ($module["views"] as $view) {
					if ($view["id"] == $id) {
						$context = BigTreeJSONDB::getSubset("modules", $module["id"]);
						$context->delete("views", $id);

						foreach ($module["actions"] as $action) {
							if ($action["view"] == $id) {
								$context->delete("actions", $action["id"]);
							}
						}
					}
				}
			}

			$this->track("jsondb -> module-views", $id, "deleted");
		}

		/*
			Function: deletePage
				Deletes a page or a pending page.
				Checks permissions.

			Parameters:
				page - A page id or a pending page id prefixed with a "p"

			Returns:
				true if successful. Stops page execution if permission issues occur.
		*/

		public function deletePage($page) {
			$page = sqlescape($page);
			$r = $this->getPageAccessLevel($page);
			
			if ($r == "p" && $this->canModifyChildren(BigTreeCMS::getPage($page))) {
				// If the page isn't numeric it's most likely prefixed by the "p" so it's pending.
				if (!is_numeric($page)) {
					sqlquery("DELETE FROM bigtree_pending_changes WHERE id = '".sqlescape(substr($page,1))."'");
					static::growl("Pages","Deleted Page");
					$this->track("bigtree_pages","p$page","deleted-pending");
				} else {
					sqlquery("DELETE FROM bigtree_pages WHERE id = '$page'");
					// Delete the children as well.
					$this->deletePageChildren($page);
					static::growl("Pages","Deleted Page");
					$this->track("bigtree_pages",$page,"deleted");
				}

				$this->deallocateResources("bigtree_pages", $page);

				return true;
			}

			$this->stop("You do not have permission to delete this page.");
			
			return false;
		}

		/*
			Function: deletePageChildren
				Deletes the children of a page and recurses downward.
				Does not check permissions.

			Parameters:
				id - The parent id to delete children for.
		*/

		public function deletePageChildren($id) {
			$child_ids = SQL::fetchAllSingle("SELECT id FROM bigtree_pages WHERE parent = ?", $id);

			foreach ($child_ids as $child_id) {
				$this->deallocateResources("bigtree_pages", $child_id);
				$this->deletePageChildren($child_id);
				$this->track("bigtree_pages", $child_id, "deleted-inherited");
			}

			SQL::delete("bigtree_pages", ["parent" => $id]);
		}

		/*
			Function: deletePageDraft
				Deletes a page draft.
				Checks permissions.

			Parameters:
				id - The page id to delete the draft for.
		*/

		public function deletePageDraft($id) {
			$id = sqlescape($id);
			// Get the version, check if the user has access to the page the version refers to.
			$access = $this->getPageAccessLevel($id);
			if ($access != "p") {
				$this->stop("You must be a publisher to manage revisions.");
			}

			// Get draft copy's ID
			$draft = sqlfetch(sqlquery("SELECT id FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND `item_id` = '$id'"));

			// Delete draft copy
			sqlquery("DELETE FROM bigtree_pending_changes WHERE id = '".$draft["id"]."'");
			$this->track("bigtree_pending_changes",$draft["id"],"deleted");
		}

		/*
			Function: deletePageRevision
				Deletes a page revision.
				Checks permissions.

			Parameters:
				id - The page version id.
		*/

		public function deletePageRevision($id) {
			// Get the version, check if the user has access to the page the version refers to.
			$revision = $this->getPageRevision($id);
			$access = $this->getPageAccessLevel($revision["page"]);
			if ($access != "p") {
				$this->stop("You must be a publisher to manage revisions.");
			}

			// Delete the revision
			sqlquery("DELETE FROM bigtree_page_revisions WHERE id = '".$revision["id"]."'");
			$this->track("bigtree_page_revisions",$id,"deleted");
		}

		/*
			Function: deletePendingChange
				Deletes a pending change.

			Parameters:
				id - The id of the change.
		*/

		public function deletePendingChange($id) {
			$id = sqlescape($id);
			sqlquery("DELETE FROM bigtree_pending_changes WHERE id = '$id'");
			$this->track("bigtree_pending_changes",$id,"deleted");
		}

		/*
			Function: deleteResource
				Deletes a resource.

			Parameters:
				id - The id of the resource.
		*/

		public function deleteResource($id) {
			$resource = $this->getResource($id);

			if ($resource) {
				SQL::delete("bigtree_resources", $id);
	
				$storage = new BigTreeStorage;
				$storage->delete($resource["file"]);

				if ($resource["is_image"]) {
					$storage->delete(BigTree::prefixFile($resource["file"],"list-preview/"));
				}

				foreach ($resource["crops"] as $prefix => $data) {
					$storage->delete(BigTree::prefixFile($resource["file"], $prefix));
				}
				
				foreach ($resource["thumbs"] as $prefix => $data) {
					$storage->delete(BigTree::prefixFile($resource["file"], $prefix));
				}

				// Update any page revisions that used this as containing deleted content
				SQL::query("UPDATE bigtree_page_revisions SET has_deleted_resources = 'on' 
							WHERE resource_allocation LIKE '%\"".$resource["id"]."\"%' OR resources LIKE '%\"".$resource["id"]."\"%'");

				$this->track("bigtree_resources", $id, "deleted");
			}
		}

		/*
			Function: deleteResourceFolder
				Deletes a resource folder and all of its sub folders and resources.

			Parameters:
				id - The id of the resource folder.
		*/

		public function deleteResourceFolder($id) {
			$items = $this->getContentsOfResourceFolder($id);
			foreach ($items["folders"] as $folder) {
				$this->deleteResourceFolder($folder["id"]);
			}
			foreach ($items["resources"] as $resource) {
				$this->deleteResource($resource["id"]);
			}
			sqlquery("DELETE FROM bigtree_resource_folders WHERE id = '".sqlescape($id)."'");
			$this->track("bigtree_resource_folders",$id,"deleted");
		}

		/*
			Function: deleteSetting
				Deletes a setting.

			Parameters:
				id - The id of the setting.
		*/

		public function deleteSetting($id) {
			$id = BigTreeCMS::extensionSettingCheck($id);

			BigTreeJSONDB::delete("settings", $id);
			SQL::delete("bigtree_settings", $id);

			$this->deallocateResources("bigtree_settings", $id);
			$this->track("jsondb -> settings", $id, "deleted");
		}

		/*
			Function: deleteTag
				Deletes a tag.

			Parameters:
				id - A tag ID
		*/

		public function deleteTag($id) {
			sqlquery("DELETE FROM bigtree_tags WHERE id = '".sqlescape($id)."'");
		}

		/*
			Function: deleteTemplate
				Deletes a template and its related files.

			Parameters:
				id - The id of the template.

			Returns:
				true if successful.
		*/

		public function deleteTemplate($id) {
			$template = BigTreeCMS::getTemplate($id);
			
			if (!$template) {
				return false;
			}
			
			if ($template["routed"]) {
				BigTree::deleteDirectory(SERVER_ROOT."templates/routed/".$template["id"]."/");
			} else {
				@unlink(SERVER_ROOT."templates/basic/".$template["id"].".php");
			}

			BigTreeJSONDB::delete("templates", $id);
			$this->track("jsondb -> templates", $template["id"], "deleted");

			return true;
		}

		/*
			Function: deleteUser
				Deletes a user.
				Checks for developer access.

			Parameters:
				id - The user id to delete.

			Returns:
				true if successful. false if the logged in user does not have permission to delete the user.
		*/

		public function deleteUser($id) {
			$id = sqlescape($id);

			// If this person has higher access levels than the person trying to update them, fail.
			$target_user = static::getUser($id);

			if ($target_user["level"] > $this->Level || $id == $this->ID) {
				return false;
			}

			sqlquery("DELETE FROM bigtree_users WHERE id = '$id'");
			$this->track("bigtree_users",$id,"deleted");

			// Add the user to the deleted users cache
			$deleted_users = BigTreeCMS::getSetting("bigtree-internal-deleted-users");
			$deleted_users[$target_user["id"]] = array(
				"name" => $target_user["name"],
				"email" => $target_user["email"],
				"company" => $target_user["company"]
			);
			$this->updateInternalSettingValue("bigtree-internal-deleted-users", $deleted_users);

			return true;
		}

		/*
			Function: disconnectGoogleAnalytics
				Turns of Google Analytics settings in BigTree and deletes cached information.
		*/

		public function disconnectGoogleAnalytics() {
			unlink(SERVER_ROOT."cache/analytics.json");
			sqlquery("UPDATE bigtree_pages SET ga_page_views = NULL");
			sqlquery("DELETE FROM bigtree_caches WHERE identifier = 'org.bigtreecms.api.analytics.google'");
			static::growl("Analytics","Disconnected");
		}

		/*
			Function: doesModuleActionExist
				Checks to see if an action exists for a given route and module.

			Parameters:
				module - The module to check.
				route - The route of the action to check.

			Returns:
				true if an action exists, otherwise false.
		*/

		public static function doesModuleActionExist($module, $route) {
			$module = BigTreeJSONDB::get("modules", $module);

			if (!$module) {
				return false;
			}

			foreach ($module["actions"] as $action) {
				if ($action["route"] == $route) {
					return true;
				}
			}

			return false;
		}

		/*
			Function: doesModuleEditActionExist
				Determines whether there is already an edit action for a module.

			Parameters:
				module - The module id to check.

			Returns:
				1 or 0, for true or false.
		*/

		public static function doesModuleEditActionExist($module) {
			return static::doesModuleActionExist($module, "edit");
		}

		/*
			Function: doesModuleLandingActionExist
				Determines whether there is already a landing action for a module.

			Parameters:
				module - The module id to check.

			Returns:
				1 or 0, for true or false.
		*/

		public static function doesModuleLandingActionExist($module) {
			return static::doesModuleActionExist($module, "");
		}

		/*
			Function: drawArrayLevel
				An internal function used for drawing callout and matrix resource data.
		*/

		public static function drawArrayLevel($keys,$level,$field = false) {
			// Backwards compatibility in case any external developers used this
			if ($field === false) {
				global $field;
			}
			foreach ($level as $key => $value) {
				if (is_array($value)) {
					static::drawArrayLevel(array_merge($keys,array($key)),$value,$field);
				} else {
?>
<input type="hidden" name="<?=$field["key"]?>[<?=implode("][",$keys)?>][<?=$key?>]" value="<?=BigTree::safeEncode($value)?>" />
<?php
				}
			}
		}

		/*
			Function: drawField
				A helper function that draws a field type.

			Parameters:
				field - Field array
		*/

		public static function drawField($field) {
			global $admin,$bigtree,$cms;

			// Give the field a unique id
			$bigtree["field_counter"]++;
			$field["id"] = $bigtree["field_namespace"].$bigtree["field_counter"];

			// Make sure options is an array to prevent warnings, load from options as a fallback for < 4.3
			if (!is_array($field["settings"])) {
				if (is_array($field["options"]) && array_filter($field["options"])) {
					$field["settings"] = $field["options"];
				} else {
					$field["settings"] = [];
				}
			}

			// Setup Validation Classes
			$label_validation_class = "";
			$field["required"] = false;

			if (!empty($field["settings"]["validation"])) {
				if (strpos($field["settings"]["validation"],"required") !== false) {
					$label_validation_class = ' class="required"';
					$field["required"] = true;
				}
			}

			// Backwards compatibility
			$field["options"] = &$field["settings"];

			// Prevent path abuse
			$field["type"] = BigTree::cleanFile($field["type"]);

			// Save current context
			$bigtree["saved_extension_context"] = $bigtree["extension_context"];

			// Get path and set context
			if (strpos($field["type"],"*") !== false) {
				list($extension,$field_type) = explode("*",$field["type"]);

				$bigtree["extension_context"] = $extension;
				$field_type_path = SERVER_ROOT."extensions/$extension/field-types/$field_type/draw.php";
			} else {
				// < 4.3 location - we prefer it to allow old overrides to work still
				$field_type_path = BigTree::path("admin/form-field-types/draw/".$field["type"].".php");

				if (!file_exists($field_type_path)) {
					$field_type_path = BigTree::path("admin/field-types/".$field["type"]."/draw.php");
				}
			}
			
			if (file_exists($field_type_path)) {
				// Don't draw the fieldset for field types that are declared as self drawing.
				if ($bigtree["field_types"][$field["type"]]["self_draw"]) {
					include $field_type_path;
				} else {
?>
<fieldset<?php if ($field["matrix_title_field"]) { ?> class="matrix_title_field"<?php } ?>>
	<?php if ($field["title"] && $field["type"] != "checkbox") { ?>
	<label<?=$label_validation_class?>><?=$field["title"]?><?php if ($field["subtitle"]) { ?> <small><?=$field["subtitle"]?></small><?php } ?></label>
	<?php } ?>
	<?php include $field_type_path; ?>
</fieldset>
<?php
					$bigtree["tabindex"]++;
				}

				$bigtree["last_resource_type"] = $field["type"];
			}

			// Restore context
			$bigtree["extension_context"] = $bigtree["saved_extension_context"];
		}

		/*
			Function: drawCSRFToken
				Draws an input field for the CSRF token.
		*/

		public function drawCSRFToken() {
			echo '<input type="hidden" value="'.htmlspecialchars($this->CSRFToken).'" name="'.$this->CSRFTokenField.'" />';
		}

		/*
			Function: drawCSRFTokenGET
				Draws a GET variable in a URL for the CSRF token.
		*/

		public function drawCSRFTokenGET() {
			echo '&'.$this->CSRFTokenField.'='.urlencode($this->CSRFToken);
		}

		/*
			Function: drawPOSTErrorMessage
				If a POST error occurred, draws a message for the form.

			Returns:
				true if a message was displayed
		*/

		public static function drawPOSTErrorMessage($dont_unset = false) {
			if (!empty($_SESSION["bigtree_admin"]["post_error"])) {
				$error_code = $_SESSION["bigtree_admin"]["post_error"];

				if ($dont_unset == false) {
					unset($_SESSION["bigtree_admin"]["post_error"]);
				}

				if ($error_code == "max_input_vars") {
					$message = "The maximum number of input variables was exceeded and the submission failed.<br>Please ask your system administrator to increase the max_input_vars limit in php.ini";
				} elseif ($error_code == "post_max_size") {
					$message = "The submission exceeded the web server's maximum submission size.<br>If you uploaded multiple files, try uploading one at a time or ask your system administrator to increase the post_max_size and upload_max_filesize settings in php.ini";
				}

				if (!$message) {
					$message = "An unknown error occurred.";
				}

				echo '<p class="warning_message">'.$message.'</p>';
				echo '<hr>';

				return true;
			} else {
				return false;
			}
		}

		/*
			Function: emailDailyDigest
				Sends out a daily digest email to all who have subscribed.
		*/

		public function emailDailyDigest() {
			global $bigtree;

			$home_page = sqlfetch(sqlquery("SELECT `nav_title` FROM `bigtree_pages` WHERE id = 0"));
			$site_title = $home_page["nav_title"];
			$image_root = $bigtree["config"]["admin_root"]."images/email/";

			$qusers = sqlquery("SELECT * FROM bigtree_users where daily_digest = 'on'");
			while ($user = sqlfetch($qusers)) {
				$changes = $this->getPublishableChanges($user["id"]);
				$alerts = $this->getContentAlerts($user["id"]);
				$messages = $this->getMessages($user["id"]);
				$unread = $messages["unread"];

				// Start building the email
				$body_alerts = $body_changes = $body_messages = "";

				// Alerts
				if (is_array($alerts) && count($alerts)) {
					foreach ($alerts as $alert) {
						$body_alerts .= '<tr>';
						$body_alerts .= '<td style="border-bottom: 1px solid #eee; padding: 10px 0 10px 15px;">'.$alert["nav_title"].'</td>';
						$body_alerts .= '<td style="border-bottom: 1px solid #eee; padding: 10px 20px 10px 15px; text-align: right;">'.$alert["current_age"].' Days</td>';

						$body_alerts .= '<td style="border-bottom: 1px solid #eee; padding: 10px 0; text-align: center;"><a href="'.$bigtree["config"]["www_root"].$alert["path"].'/"><img src="'.$image_root.'launch.gif" alt="Launch" /></a></td>';

						$body_alerts .= '<td style="border-bottom: 1px solid #eee; padding: 10px 0; text-align: center;"><a href="'.$bigtree["config"]["admin_root"]."pages/edit/".$alert["id"].'/"><img src="'.$image_root.'edit.gif" alt="Edit" /></a></td>';
						$body_alerts .= '</tr>';
					}
				} else {
					$body_alerts = '<tr><td colspan="4" style="border-bottom: 1px solid #eee; color: #999; padding: 10px 0 10px 15px;"><p>No Content Age Alerts</p></td></tr>';
				}

				// Changes
				if (is_array($changes) && count($changes)) {
					foreach ($changes as $change) {
						$body_changes .= '<tr>';
						$body_changes .= '<td style="border-bottom: 1px solid #eee; padding: 10px 0 10px 15px;">'.$change["user"]["name"].'</td>';
						if ($change["title"]) {
							$body_changes .= '<td style="border-bottom: 1px solid #eee; padding: 10px 0 10px 15px;">Pages</td>';
						} else {
							$body_changes .= '<td style="border-bottom: 1px solid #eee; padding: 10px 0 10px 15px;">'.$change["mod"]["name"].'</td>';
						}
						if (is_null($change["item_id"])) {
							$body_changes .= '<td style="border-bottom: 1px solid #eee; padding: 10px 0 10px 15px;">Addition</td>';
						} else {
							$body_changes .= '<td style="border-bottom: 1px solid #eee; padding: 10px 0 10px 15px;">Edit</td>';
						}
						$body_changes .= '<td style="border-bottom: 1px solid #eee; padding: 10px 0; text-align: center;"><a href="'.static::getChangeEditLink($change).'"><img src="'.$image_root.'launch.gif" alt="Launch" /></a></td>' . "\r\n";
						$body_changes .= '</tr>';
					}
				} else {
					$body_changes = '<tr><td colspan="4" style="border-bottom: 1px solid #eee; color: #999; padding: 10px 0 10px 15px;"><p>No Pending Changes</p></td></tr>';
				}

				// Messages
				if (is_array($unread) && count($unread)) {
					foreach ($unread as $message) {
						$body_messages .= '<tr>';
						$body_messages .= '<td style="border-bottom: 1px solid #eee; padding: 10px 0 10px 15px;">'.$message["sender_name"].'</td>';
						$body_messages .= '<td style="border-bottom: 1px solid #eee; padding: 10px 0 10px 15px;">'.$message["subject"].'</td>';
						$body_messages .= '<td style="border-bottom: 1px solid #eee; padding: 10px 0 10px 15px;">'.$this->convertTimestampToUser($message["date"], $bigtree["config"]["date_format"]." @ g:i a", $user["timezone"]).'</td>';
						$body_messages .= '</tr>';
					}
				} else {
					$body_messages = '<tr><td colspan="3" style="border-bottom: 1px solid #eee; color: #999; padding: 10px 0 10px 15px;"><p>No Unread Messages</p></td></tr>';
				}

				// Send it
				$es = new BigTreeEmailService;
				if ((is_array($alerts) && count($alerts)) || count($changes) || count($unread)) {
					$body = file_get_contents(BigTree::path("admin/email/daily-digest.html"));
					$body = str_ireplace("{www_root}", $bigtree["config"]["www_root"], $body);
					$body = str_ireplace("{admin_root}", $bigtree["config"]["admin_root"], $body);
					$body = str_ireplace("{site_title}", $site_title, $body);
					$body = str_ireplace("{date}", date("F j, Y",time()), $body);
					$body = str_ireplace("{content_alerts}", $body_alerts, $body);
					$body = str_ireplace("{pending_changes}", $body_changes, $body);
					$body = str_ireplace("{unread_messages}", $body_messages, $body);

					// If we don't have a from email set, third parties most likely will fail so we're going to use local sending
					if ($es->Settings["bigtree_from"]) {
						$reply_to = "no-reply@".(isset($_SERVER["HTTP_HOST"]) ? str_replace("www.","",$_SERVER["HTTP_HOST"]) : str_replace(array("http://www.","https://www.","http://","https://"),"",DOMAIN));
						$es->sendEmail("$site_title Daily Digest",$body,$user["email"],$es->Settings["bigtree_from"],"BigTree CMS",$reply_to);
					} else {
						BigTree::sendEmail($user["email"],"$site_title Daily Digest",$body);
					}
				}
			}
		}

		/*
			Function: forgotPassword
				Creates a new password change hash and sends an email to the user.

			Parameters:
				email - The user's email address

			Returns:
				Redirects if the email address was found, returns false if the user doesn't exist.

			See Also:
				<changePassword>
		*/

		public static function forgotPassword($email) {
			global $bigtree;

			$home_page = sqlfetch(sqlquery("SELECT `nav_title` FROM `bigtree_pages` WHERE id = 0"));
			$site_title = $home_page["nav_title"];

			$email = sqlescape($email);
			$user = sqlfetch(sqlquery("SELECT * FROM bigtree_users WHERE email = '$email'"));
			if (!$user) {
				return false;
			}

			$hash = sqlescape(md5(md5($user["password"]).md5(uniqid("bigtree-hash".microtime(true)))));
			sqlquery("UPDATE bigtree_users SET change_password_hash = '$hash' WHERE id = '".$user["id"]."'");

			$login_root = ($bigtree["config"]["force_secure_login"] ? str_replace("http://","https://",ADMIN_ROOT) : ADMIN_ROOT)."login/";

			$html = file_get_contents(BigTree::path("admin/email/reset-password.html"));
			$html = str_ireplace("{www_root}",WWW_ROOT,$html);
			$html = str_ireplace("{admin_root}",ADMIN_ROOT,$html);
			$html = str_ireplace("{site_title}",$site_title,$html);
			$html = str_ireplace("{reset_link}",$login_root."reset-password/$hash/",$html);

			$es = new BigTreeEmailService;

			// Only use a custom email service if a from email has been set
			if ($es->Settings["bigtree_from"]) {
				$reply_to = "no-reply@".(isset($_SERVER["HTTP_HOST"]) ? str_replace("www.","",$_SERVER["HTTP_HOST"]) : str_replace(array("http://www.","https://www.","http://","https://"),"",DOMAIN));
				$es->sendEmail("Reset Your Password",$html,$user["email"],$es->Settings["bigtree_from"],"BigTree CMS",$reply_to);
			} else {
				BigTree::sendEmail($user["email"],"Reset Your Password",$html);
			}

			return true;
		}

		/*
			Function: get404Total
				Get the total number of 404s of a certain type.

			Parameters:
				type - The type to retrieve the count for (301, ignored, 404)
				site_key - The site key to return 404 count for (defaults to all sites)

			Returns:
				The number of 404s in the table of the given type.
		*/

		public static function get404Total($type, $site_key = null) {
			if ($site_key) {
				$site_key_query = "AND site_key = '".sqlescape($site_key)."'";
			} else {
				$site_key_query = "";
			}

			if ($type == "404") {
				$total = sqlfetch(sqlquery("SELECT COUNT(id) AS `total` FROM bigtree_404s WHERE ignored = '' AND redirect_url = '' $site_key_query"));
			} elseif ($type == "301") {
				$total = sqlfetch(sqlquery("SELECT COUNT(id) AS `total` FROM bigtree_404s WHERE ignored = '' AND redirect_url != '' $site_key_query"));
			} elseif ($type == "ignored") {
				$total = sqlfetch(sqlquery("SELECT COUNT(id) AS `total` FROM bigtree_404s WHERE ignored = 'on' $site_key_query"));
			}

			if (!empty($total)) {
				return $total["total"];
			} else {
				return false;
			}
		}

		/*
			Function: getAccessGroups
				Returns a list of all groups the logged in user has access to in a module.

			Parameters:
				module - A module id or module entry.

			Returns:
				An array of groups if a user has limited access to a module or "true" if the user has access to all groups.
		*/

		public function getAccessGroups($module) {
			if ($this->Level > 0) {
				return true;
			}

			if (is_array($module)) {
				$module = $module["id"];
			}

			if ($this->Permissions["module"][$module] && $this->Permissions["module"][$module] != "n") {
				return true;
			}

			$groups = array();
			if (is_array($this->Permissions["module_gbp"][$module])) {
				foreach ($this->Permissions["module_gbp"][$module] as $group => $permission) {
					if ($permission && $permission != "n") {
						$groups[] = $group;
					}
				}
			}
			return $groups;
		}

		/*
			Function: getAccessLevel
				Returns the permission level for a given module and item.

			Parameters:
				module - The module id or entry to check access for.
				item - (optional) The item of the module to check access for.
				table - (optional) The group based table.
				user - (optional) User array if checking for a user other than the logged in user.

			Returns:
				The permission level for the given item or module (if item was not passed).

			See Also:
				<getCachedAccessLevel>
		*/

		public function getAccessLevel($module,$item = array(),$table = "",$user = false) {
			if (!$user) {
				$level = $this->Level;
				$permissions = $this->Permissions;
			} else {
				$level = $user["level"];
				$permissions = $user["permissions"];
			}

			if ($level > 0) {
				return "p";
			}

			$id = is_array($module) ? $module["id"] : $module;

			$perm = $permissions["module"][$id];

			// If group based permissions aren't on or we're a publisher of this module it's an easy solution… or if we're not even using the table.
			if (!$item || !$module["gbp"]["enabled"] || $perm == "p" || $table != $module["gbp"]["table"]) {
				return $perm;
			}

			if (is_array($permissions["module_gbp"][$id])) {
				$gv = $item[$module["gbp"]["group_field"]];
				$gp = $permissions["module_gbp"][$id][$gv];

				if ($gp != "n") {
					return $gp;
				}
			}

			return $perm;
		}

		/*
			Function: getActionClass
				Returns the button class for the given action and item.

			Parameters:
				action - The action for the item (edit, feature, approve, etc)
				item - The entry to check the action for.

			Returns:
				Class name for the <a> tag.

				For example, if the item is already featured, this returns "icon_featured icon_featured_on" for the "feature" action.
				If the item isn't already featured, it would simply return "icon_featured" for the "feature" action.
		*/

		public static function getActionClass($action,$item) {
			$class = "";
			if (isset($item["bigtree_pending"]) && $action != "edit" && $action != "delete") {
				return "icon_disabled js-disabled-hook";
			}
			if ($action == "feature") {
				$class = "icon_feature js-feature-hook";

				if ($item["featured"]) {
					$class .= " icon_feature_on";
				}
			}
			if ($action == "edit") {
				$class = "icon_edit";
			}
			if ($action == "delete") {
				$class = "icon_delete js-delete-hook";
			}
			if ($action == "approve") {
				$class = "icon_approve js-approve-hook";
				if ($item["approved"]) {
					$class .= " icon_approve_on";
				}
			}
			if ($action == "archive") {
				$class = "icon_archive js-archive-hook";
				if ($item["archived"]) {
					$class .= " icon_archive_on";
				}
			}
			if ($action == "preview") {
				$class = "icon_preview";
			}
			return $class;
		}

		/*
			Function: getArchivedNavigationByParent
				Returns an alphabetic list of navigation that is archived under the given parent.

			Parameters:
				parent - The ID of the parent page

			Returns:
				An array of page entries.
		*/

		public static function getArchivedNavigationByParent($parent) {
			$nav = array();
			$q = sqlquery("SELECT id,nav_title as title,parent,external,new_window,template,publish_at,expire_at,path,ga_page_views FROM bigtree_pages WHERE parent = '$parent' AND archived = 'on' ORDER BY nav_title asc");
			while ($nav_item = sqlfetch($q)) {
				$nav_item["external"] = BigTreeCMS::replaceRelativeRoots($nav_item["external"]);
				$nav[] = $nav_item;
			}
			return $nav;
		}

		/*
			Function: getAutoModuleActions
				Return a list of module forms and views.
				Used by the API for reconstructing forms and views.

			Parameters:
				module - The module id to pull forms/views for.

			Returns:
				An array of module actions with "form" and "view" columns replaced with form and view data.

			See Also:
				<BigTreeAutoModule.getForm>
				<BigTreeAutoModule.getView>
		*/

		public static function getAutoModuleActions($module) {
			$actions = [];
			$context = BigTreeJSONDB::getSubset("modules", $module);
			$module_actions = $context->getAll("actions", "position");

			foreach ($module_actions as $action) {
				if ($action["in_nav"]) {
					if ($action["view"]) {
						$actions[] = [
							"type" => "view",
							"view" => BigTreeAutoModule::getView($action["view"])
						];
					} elseif ($action["form"]) {
						$actions[] = [
							"type" => "form",
							"form" => BigTreeAutoModule::getForm($action["form"])
						];
					}
				}
			}

			return $actions;
		}

		/*
			Function: getBasicTemplates
				Returns a list of non-routed templates ordered by position.

			Parameters:
				sort - Sort order, defaults to positioned

			Returns:
				An array of template entries.
		*/

		public function getBasicTemplates($sort = "position DESC, id ASC") {
			list($sort_column, $sort_direction) = explode(" ", $sort);
			$templates = BigTreeJSONDB::getAll("templates", $sort_column, $sort_direction ?: "ASC");
			$basic = [];

			foreach ($templates as $template) {
				if ($template["level"] <= $this->Level && !$template["routed"]) {
					$basic[] = $template;
				}
			}

			return $basic;
		}

		/*
			Function: getCachedAccessLevel
				Returns the permission level for a given module and cached view entry.

			Parameters:
				module - The module id or entry to check access for.
				item - (optional) The item of the module to check access for.
				table - (optional) The group based table.

			Returns:
				The permission level for the given item or module (if item was not passed).

			See Also:
				<getAccessLevel>
		*/

		// Since cached items don't use their normal columns...
		public function getCachedAccessLevel($module,$item = array(),$table = "") {
			if ($this->Level > 0) {
				return "p";
			}

			$id = is_array($module) ? $module["id"] : $module;

			$perm = $this->Permissions["module"][$id];

			// If group based permissions aren't on or we're a publisher of this module it's an easy solution… or if we're not even using the table.
			if (!$item || !$module["gbp"]["enabled"] || $perm == "p" || $table != $module["gbp"]["table"]) {
				return $perm;
			}

			if (is_array($this->Permissions["module_gbp"][$id])) {
				$current_gbp_value = $item["gbp_field"];
				$original_gbp_value = $item["published_gbp_field"];

				$access_level = $this->Permissions["module_gbp"][$id][$current_gbp_value];
				if ($access_level != "n") {
					$original_access_level = $this->Permissions["module_gbp"][$id][$original_gbp_value];
					if ($original_access_level != "p") {
						$access_level = $original_access_level;
					}
				}

				if ($access_level != "n") {
					return $access_level;
				}
			}

			return $perm;
		}

		/*
			Function: getCachedFieldTypes
				Caches available field types and returns them.

			Parameters:
				split - Whether to split the field types into separate default / custom arrays (defaults to false)

			Returns:
				Array of three arrays of field types (template, module, and callout).
		*/

		public static function getCachedFieldTypes($split = false) {
			$types["modules"] = $types["templates"] = $types["callouts"] = $types["settings"] = [
				"default" => [
					"text" => ["name" => "Text", "self_draw" => false],
					"textarea" => ["name" => "Text Area", "self_draw" => false],
					"html" => ["name" => "HTML Area", "self_draw" => false],
					"link" => ["name" => "Link", "self_draw" => false],
					"upload" => ["name" => "File Upload", "self_draw" => false],
					"image" => ["name" => "Image Upload", "self_draw" => false],
					"video" => ["name" => "YouTube or Vimeo Video", "self_draw" => false],
					"file-reference" => ["name" => "File Reference", "self_draw" => false],
					"image-reference" => ["name" => "Image Reference", "self_draw" => false],
					"video-reference" => ["name" => "Video Reference", "self_draw" => false],
					"list" => ["name" => "List", "self_draw" => false],
					"checkbox" => ["name" => "Checkbox", "self_draw" => false],
					"date" => ["name" => "Date Picker", "self_draw" => false],
					"time" => ["name" => "Time Picker", "self_draw" => false],
					"datetime" => ["name" => "Date &amp; Time Picker", "self_draw" => false],
					"media-gallery" => ["name" => "Media Gallery", "self_draw" => false],
					"callouts" => ["name" => "Callouts", "self_draw" => true],
					"matrix" => ["name" => "Matrix", "self_draw" => true],
					"one-to-many" => ["name" => "One to Many", "self_draw" => false]
				],
				"custom" => []
			];

			$types["modules"]["default"]["route"] = array("name" => "Generated Route","self_draw" => true);
			$field_types = BigTreeJSONDB::getAll("field-types", "name", "ASC");

			foreach ($field_types as $field_type) {
				foreach ($field_type["use_cases"] as $case => $val) {
					if ($val) {
						$types[$case]["custom"][$field_type["id"]] = ["name" => $field_type["name"], "self_draw" => $field_type["self_draw"]];
					}
				}
			}
			
			// Re-merge if we don't want them split
			if (!$split) {
				foreach ($types as $use_case => $list) {
					$types[$use_case] = array_merge($list["default"], $list["custom"]);
				}
			}

			return $types;
		}

		/*
			Function: getCallout
				Returns a callout entry.

			Parameters:
				id - The id of the callout.

			Returns:
				A callout entry from callouts database with resources decoded.
		*/

		public static function getCallout($id) {
			return BigTreeJSONDB::get("callouts", $id);
		}

		/*
			Function: getCalloutGroup
				Returns a callout group entry from the callout groups database.

			Parameters:
				id - The id of the callout group.

			Returns:
				A callout group entry.
		*/

		public static function getCalloutGroup($id) {
			return BigTreeJSONDB::get("callout-groups", $id);
		}

		/*
			Function: getCalloutGroups
				Returns a list of callout groups sorted by name.

			Returns:
				An array of callout group entries from callout groups database.
		*/

		public static function getCalloutGroups() {
			return BigTreeJSONDB::getAll("callout-groups", "name", "ASC");
		}

		/*
			Function: getCallouts
				Returns a list of callouts.

			Parameters:
				sort - The order to return the callouts. Defaults to positioned.

			Returns:
				An array of callout entries from callouts database.
		*/

		public static function getCallouts($sort = "position") {
			list($sort_column, $sort_direction) = explode(" ", $sort);

			return BigTreeJSONDB::getAll("callouts", $sort_column, $sort_direction ?: "ASC");
		}

		/*
			Function: getCalloutsAllowed
				Returns a list of callouts the logged-in user is allowed access to.

			Parameters:
				sort - The order to return the callouts. Defaults to positioned.

			Returns:
				An array of callout entries from callouts database.
		*/

		public function getCalloutsAllowed($sort = "position") {
			list($sort_column, $sort_direction) = explode(" ", $sort);
			$callouts = BigTreeJSONDB::getAll("callouts", $sort_column, $sort_direction ?: "ASC");

			foreach ($callouts as $index => $callout) {
				if ($callout["level"] > $this->Level) {
					unset($callouts[$index]);
				}
			}

			return $callouts;
		}

		/*
			Function: getCalloutsInGroups
				Returns a list of callouts in a given set of groups.

			Parameters:
				groups - An array of group IDs to retrieve callouts for.
				auth - If set to true, only returns callouts the logged in user has access to. Defaults to true.

			Returns:
				An array of entries from the callouts database.
		*/

		public function getCalloutsInGroups($groups, $auth = true) {
			$ids = [];
			$items = [];
			$names = [];

			foreach ($groups as $group_id) {
				$group = $this->getCalloutGroup($group_id);

				if (!$group) {
					continue;
				}

				foreach ($group["callouts"] as $callout_id) {
					if (!in_array($callout_id, $ids)) {
						$callout = $this->getCallout($callout_id);
						
						if (!$auth || $this->Level >= $callout["level"]) {
							$items[] = $callout;
							$ids[] = $callout_id;
							$names[] = $callout["name"];
						}
					}
				}
			}

			array_multisort($names,$items);

			return $items;
		}

		/*
			Function: getChange
				Get a pending change.

			Parameters:
				id - The id of the pending change.

			Returns:
				A pending change entry from the bigtree_pending_changes table.
		*/

		public static function getChange($id) {
			return sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE id = '$id'"));
		}

		/*
			Function: getChangeEditLink
				Returns a link to where the item involved in the pending change can be edited.

			Parameters:
				change - The ID of the change or the change array from the database.

			Returns:
				A string containing a link to the admin.
		*/

		public static function getChangeEditLink($change) {
			global $bigtree;

			if (!is_array($change)) {
				$change = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE id = '$change'"));
			}

			if ($change["table"] == "bigtree_pages" && $change["item_id"]) {
				return $bigtree["config"]["admin_root"]."pages/edit/".$change["item_id"]."/";
			}

			if ($change["table"] == "bigtree_pages") {
				return $bigtree["config"]["admin_root"]."pages/edit/p".$change["id"]."/";
			}

			$module = BigTreeJSONDB::get("modules", $change["module"]);

			foreach ($module["forms"] as $form) {
				if ($form["table"] == $change["table"]) {
					foreach ($module["actions"] as $module_action) {
						if ($module_action["form"] == $form["id"]) {
							$action = $module_action;
						}
					}
				}
			}

			if (!$change["item_id"]) {
				$change["item_id"] = "p".$change["id"];
			}

			if ($action) {
				return $bigtree["config"]["admin_root"].$module["route"]."/".$action["route"]."/".$change["item_id"]."/";
			} else {
				return $bigtree["config"]["admin_root"].$module["route"]."/edit/".$change["item_id"]."/";
			}
		}

		/*
			Function: getContentAlerts
				Gets a list of pages with content older than their Max Content Age that a user follows.

			Parameters:
				user - The user id to pull alerts for or a user entry (defaults to the logged in user)

			Returns:
				An array of arrays containing a page title, path, and id.
		*/

		public function getContentAlerts($user = false) {
			if (is_array($user)) {
				$user = static::getUser($user["id"]);
			} elseif ($user) {
				$user = static::getUser($user);
			} else {
				$user = static::getUser($this->ID);
			}

			if (!is_array($user["alerts"])) {
				return false;
			}

			$alerts = array();
			// We're going to generate a list of pages the user cares about first to get their paths.
			$where = array();
			foreach ($user["alerts"] as $alert => $status) {
				$where[] = "id = '".sqlescape($alert)."'";
			}
			if (!count($where)) {
				return false;
			}

			// If we care about the whole tree, skip the madness.
			if ($user["alerts"][0] == "on") {
				$q = sqlquery("SELECT nav_title,id,path,updated_at,DATEDIFF('".date("Y-m-d")."',updated_at) AS current_age FROM bigtree_pages WHERE max_age > 0 AND DATEDIFF('".date("Y-m-d")."',updated_at) > max_age ORDER BY current_age DESC");
				while ($f = sqlfetch($q)) {
					$alerts[] = $f;
				}
			} else {
				$paths = array();
				$q = sqlquery("SELECT path FROM bigtree_pages WHERE ".implode(" OR ",$where));

				while ($f = sqlfetch($q)) {
					$paths[] = "path = '".sqlescape($f["path"])."' OR path LIKE '".sqlescape($f["path"])."/%'";
				}

				if (count($paths)) {
					// Find all the pages that are old that contain our paths
					$q = sqlquery("SELECT nav_title,id,path,updated_at,DATEDIFF('".date("Y-m-d")."',updated_at) AS current_age FROM bigtree_pages WHERE max_age > 0 AND (".implode(" OR ",$paths).") AND DATEDIFF('".date("Y-m-d")."',updated_at) > max_age ORDER BY current_age DESC");
					while ($f = sqlfetch($q)) {
						$alerts[] = $f;
					}
				}
			}

			return $alerts;
		}

		/*
			Function: getExisting404
				Checks for the existance of a 404 at a given source URL.

			Parameters:
				url - Source URL
				get_vars - Source URL get vars
				site_key - Optional site key for a multi-site environment.

			Returns:
				An existing 404 or null if one is not found.
		*/

		static public function getExisting404($url, $get_vars, $site_key = null) {
			if (!empty($get_vars)) {
				if (!is_null($site_key)) {
					return SQL::fetch("SELECT * FROM bigtree_404s WHERE `broken_url` = ? AND get_vars = ? AND `site_key` = ?", $url, $get_vars, $site_key);
				} else {
					return SQL::fetch("SELECT * FROM bigtree_404s WHERE `broken_url` = ? AND get_vars = ?", $url, $get_vars);
				}
			} else {
				if (!is_null($site_key)) {
					return SQL::fetch("SELECT * FROM bigtree_404s WHERE `broken_url` = ? AND get_vars = '' AND `site_key` = ?", $url, $site_key);
				} else {
					return SQL::fetch("SELECT * FROM bigtree_404s WHERE `broken_url` = ? AND get_vars = ''", $url);
				}
			}
		}

		/*
			Function: getExtension
				Returns information about a package or extension.

			Parameters:
				id - The package/extension ID.

			Returns:
				A package/extension.
		*/

		public static function getExtension($id) {
			return BigTreeJSONDB::get("extensions", $id);
		}

		/*
			Function: getExtensions
				Returns a list of installed/created extensions.

			Parameters:
				sort - Column/direction to sort (defaults to name ASC)

			Returns:
				An array of extensions.
		*/

		public static function getExtensions($sort = "name ASC") {
			list($sort_column, $sort_direction) = explode(" ", $sort);

			return BigTreeJSONDB::getAll("extensions", $sort_column, $sort_direction ?: "ASC");
		}

		/*
			Function: getFeeds
				Returns a list of feeds.

			Parameters:
				sort - The sort direction, defaults to name.

			Returns:
				An array of feed elements from the feeds database sorted by name.
		*/

		public static function getFeeds($sort = "name ASC") {
			list($sort_column, $sort_direction) = explode(" ", $sort);

			return BigTreeJSONDB::getAll("feeds", $sort_column, $sort_direction ?: "ASC");
		}

		/*
			Function: getFieldType
				Returns a field type.

			Parameters:
				id - The id of the file type.

			Returns:
				A field type entry with the "files" column decoded.
		*/

		public static function getFieldType($id) {
			return BigTreeJSONDB::get("field-types", $id);
		}

		/*
			Function: getFieldTypes
				Returns a list of field types.

			Parameters:
				sort - The sort directon, defaults to name ASC.

			Returns:
				An array of entries from the field types database.
		*/

		public static function getFieldTypes($sort = "name ASC") {
			list($sort_column, $sort_direction) = explode(" ", $sort);

			return BigTreeJSONDB::getAll("field-types", $sort_column, $sort_direction ?: "ASC");
		}

		/*
			Function: getFullNavigationPath
				Calculates the full navigation path for a given page ID.

			Parameters:
				id - The page ID to calculate the navigation path for.

			Returns:
				The navigation path (normally found in the "path" column in bigtree_pages).
		*/

		public static function getFullNavigationPath($id, $path = array()) {
			$f = sqlfetch(sqlquery("SELECT route,id,parent FROM bigtree_pages WHERE id = '$id'"));
			$path[] = BigTreeCMS::urlify($f["route"]);
			if ($f["parent"] != 0) {
				return static::getFullNavigationPath($f["parent"],$path);
			}
			$path = implode("/",array_reverse($path));
			return $path;
		}

		/*
			Function: getHiddenNavigationByParent
				Returns an alphabetic list of navigation that is hidden under the given parent.

			Parameters:
				parent - The ID of the parent page

			Returns:
				An array of page entries.
		*/

		public static function getHiddenNavigationByParent($parent) {
			$nav = array();
			$q = sqlquery("SELECT id,nav_title as title,parent,external,new_window,template,publish_at,expire_at,path,ga_page_views FROM bigtree_pages WHERE parent = '$parent' AND in_nav = '' AND archived != 'on' ORDER BY nav_title asc");
			while ($nav_item = sqlfetch($q)) {
				$nav_item["external"] = BigTreeCMS::replaceRelativeRoots($nav_item["external"]);
				$nav[] = $nav_item;
			}
			return $nav;
		}

		/*
			Function: getMessage
				Returns a message from message center.
				Verifies that the user calling this method is either the sender or a recipient.

			Parameters:
				id - The id of the message.

			Returns:
				An entry from bigtree_messages.
		*/

		public function getMessage($id) {
			$message = sqlfetch(sqlquery("SELECT * FROM bigtree_messages WHERE id = '".sqlescape($id)."'"));
			if (!$message) {
				return false;
			}
			if ($message["sender"] != $this->ID && strpos($message["recipients"],"|".$this->ID."|") === false) {
				return false;
			}
			return $message;
		}

		/*
			Function: getMessageChain
				Gets a full chain of messages based on one ID in the chain

			Parameters:
				id - The ID of one message in the chain.

			Returns:
				An array of entries from bigtree_messages with the message entry that was requested having the "selected" column set.
		*/

		public function getMessageChain($id) {
			$message = $m = $this->getMessage($id);
			$message["selected"] = true;
			if (!$message) {
				return false;
			}
			$chain = array($message);

			// Find parents
			while ($m["response_to"]) {
				$m = $this->getMessage($m["response_to"]);
				// Prepend this message to the chain
				$chain = array_merge(array($m),$chain);
			}

			// Find children
			$m = $message;
			while ($f = sqlfetch(sqlquery("SELECT id FROM bigtree_messages WHERE response_to = '".$m["id"]."'"))) {
				$m = $this->getMessage($f["id"]);
				$chain[] = $m;
			}

			return $chain;
		}

		/*
			Function: getMessages
				Returns all a user's messages.

			Parameters:
				user - Optional user ID (defaults to logged in user)

			Returns:
				An array containing "sent", "read", and "unread" keys that contain an array of messages each.
		*/

		public function getMessages($user = false) {
			if ($user) {
				$user = sqlescape($user);
			} else {
				$user = $this->ID;
			}
			$sent = array();
			$read = array();
			$unread = array();
			$q = sqlquery("SELECT bigtree_messages.*, bigtree_users.name AS sender_name, bigtree_users.email AS sender_email FROM bigtree_messages JOIN bigtree_users ON bigtree_messages.sender = bigtree_users.id WHERE sender = '$user' OR recipients LIKE '%|$user|%' ORDER BY date DESC");

			while ($f = sqlfetch($q)) {
				// If we're the sender put it in the sent array.
				if ($f["sender"] == $user) {
					$sent[] = $f;
				} else {
					// If we've been marked read, put it in the read array.
					if ($f["read_by"] && strpos($f["read_by"],"|".$user."|") !== false) {
						$read[] = $f;
					} else {
						$unread[] = $f;
					}
				}
			}

			return array("sent" => $sent, "read" => $read, "unread" => $unread);
		}

		/*
			Function: getModule
				Returns an entry from the modules database.

			Parameters:
				id - The id of the module.

			Returns:
				A module entry with the "gbp" column decoded.
		*/

		public static function getModule($id) {
			$module = BigTreeJSONDB::get("modules", $id);

			if (!is_array($module["actions"])) {
				$module["actions"] = [];
			}

			if (!is_array($module["views"])) {
				$module["views"] = [];
			}

			if (!is_array($module["forms"])) {
				$module["forms"] = [];
			}

			if (!is_array($module["embeddable-forms"])) {
				$module["embeddable-forms"] = [];
			}

			if (!is_array($module["reports"])) {
				$module["reports"] = [];
			}

			return $module;
		}

		/*
			Function: getModuleAction
				Returns an action entry from the modules database.

			Parameters:
				id - The id of the action.

			Returns:
				A module action entry.
		*/

		public static function getModuleAction($id) {
			$modules = BigTreeJSONDB::getAll("modules");

			foreach ($modules as $module) {
				foreach ($module["actions"] as $action) {
					if ($action["id"] == $id) {
						$action["module"] = $module["id"];

						return $action;
					}
				}
			}
		}

		/*
			Function: getModuleActionByRoute
				Returns an action entry from the modules database for the given module and route.

			Parameters:
				module - The module to lookup an action for.
				route - An array of routes of the action and extra commands.

			Returns:
				A module action entry.
		*/

		public static function getModuleActionByRoute($module, $route) {
			// For landing routes.
			if (!count($route)) {
				$route = [""];
			}

			$module = BigTreeJSONDB::get("modules", $module);
			$commands = [];
			$action = false;

			while (count($route)) {
				$route_string = implode("/", $route);

				foreach ($module["actions"] as $action) {
					if ($action["route"] == $route_string) {
						$action["module"] = $module["id"];

						return ["action" => $action, "commands" => array_reverse($commands)];
					}
				}

				$commands[] = end($route);
				$route = array_slice($route, 0, -1);
			}

			return false;
		}

		/*
			Function: getModuleActionForForm
				Returns the related module action for an auto module form. Prioritizes edit action over add.

			Parameters:
				form - The id of a form or a form entry.

			Returns:
				A module action entry.
		*/

		public static function getModuleActionForForm($form) {
			if (is_array($form)) {
				$form = $form["id"];
			}

			$modules = BigTreeJSONDB::getAll("modules");

			foreach ($modules as $module) {
				foreach ($module["actions"] as $action) {
					if ($action["form"] == $form) {
						$action["module"] = $module["id"];

						return $action;
					}
				}
			}
		}

		/*
			Function: getModuleActionForReport
				Returns the related module action for an auto module report.

			Parameters:
				report - The id of a report or a report entry.

			Returns:
				A module action entry.
		*/

		public static function getModuleActionForReport($report) {
			if (is_array($report)) {
				$report = $report["id"];
			}

			$modules = BigTreeJSONDB::getAll("modules");

			foreach ($modules as $module) {
				foreach ($module["actions"] as $action) {
					if ($action["report"] == $report) {
						$action["module"] = $module["id"];

						return $action;
					}
				}
			}
		}

		/*
			Function: getModuleActionForView
				Returns the related module action for an auto module view.

			Parameters:
				view - The id of a view or a view entry.

			Returns:
				A module action entry.
		*/

		public static function getModuleActionForView($view) {
			if (is_array($view)) {
				$view = $view["id"];
			}

			$modules = BigTreeJSONDB::getAll("modules");

			foreach ($modules as $module) {
				foreach ($module["actions"] as $action) {
					if ($action["view"] == $view) {
						$action["module"] = $module["id"];

						return $action;
					}
				}
			}
		}

		/*
			Function: getModuleActions
				Returns a list of module actions in positioned order.

			Parameters:
				module - A module id or a module entry.

			Returns:
				An array of module action entries.
		*/

		public static function getModuleActions($module) {
			if (is_array($module)) {
				$module = $module["id"];
			}

			$context = BigTreeJSONDB::getSubset("modules", $module);
			$actions = $context->getAll("actions", "position");

			foreach ($actions as $index => $action) {
				$actions[$index]["module"] = $module;
			}

			return $actions;
		}

		/*
			Function: getModuleByClass
				Returns a module entry for the given class name.

			Parameters:
				class - A module class.

			Returns:
				A module entry with the "gbp" column decoded or false if a module was not found.
		*/

		public static function getModuleByClass($class) {
			return BigTreeJSONDB::get("modules", $class, "class");
		}

		/*
			Function: getModuleByRoute
				Returns a module entry for the given route.

			Parameters:
				route - A module route.

			Returns:
				A module entry with the "gbp" column decoded or false if a module was not found.
		*/

		public static function getModuleByRoute($route) {
			return BigTreeJSONDB::get("modules", $route, "route");
		}

		/*
			Function: getModuleEmbedForms
				Gets embeddable forms from the modules database.

			Parameters:
				sort - The field to sort by.
				module - Specific module to pull forms for (defaults to all modules).

			Returns:
				An array of embeddable form entries from modules database with "fields" decoded.
		*/

		public static function getModuleEmbedForms($sort = "title", $module = false) {
			list($sort_column, $sort_direction) = explode(" ", $sort);

			if ($module) {
				$context = BigTreeJSONDB::getSubset("modules", $module);

				return $context->getAll("embeddable-forms", $sort_column, $sort_direction);
			} else {
				$forms = [];
				$sort_field = [];
				$modules = BigTreeJSONDB::getAll("modules");

				foreach ($modules as $module) {
					$forms = array_merge($forms, array_filter((array) $module["embeddable-forms"]));
				}

				foreach ($forms as $form) {
					$sort_field[] = $form[$sort_column];
				}

				if ($sort_direction == "DESC") {
					array_multisort($sort_field, SORT_DESC, $forms);
				} else {
					array_multisort($sort_field, SORT_ASC, $forms);
				}

				return $forms;
			}
		}

		/*
			Function: getModuleForms
				Gets forms from the modules database with fields decoded.

			Parameters:
				sort - The field to sort by.
				module - Specific module to pull forms for (defaults to all modules).

			Returns:
				An array of form entries from modules database with "fields" decoded.
		*/

		public static function getModuleForms($sort = "title",$module = false) {
			list($sort_column, $sort_direction) = explode(" ", $sort);

			if ($module) {
				$context = BigTreeJSONDB::getSubset("modules", $module);

				return $context->getAll("forms", $sort_column, $sort_direction);
			} else {
				$forms = [];
				$sort_field = [];
				$modules = BigTreeJSONDB::getAll("modules");

				foreach ($modules as $module) {
					$forms = array_merge($forms, array_filter((array) $module["forms"]));
				}

				foreach ($forms as $form) {
					$sort_field[] = $form[$sort_column];
				}

				if ($sort_direction == "DESC") {
					array_multisort($sort_field, SORT_DESC, $forms);
				} else {
					array_multisort($sort_field, SORT_ASC, $forms);
				}

				return $forms;
			}
		}

		/*
			Function: getModuleGroup
				Returns a module group entry from the module groups database.

			Parameters:
				id - The id of the module group.

			Returns:
				A module group entry.

			See Also:
				<getModuleGroupByName>
				<getModuleGroupByRoute>
		*/

		public static function getModuleGroup($id) {
			return BigTreeJSONDB::get("module-groups", $id);
		}

		/*
			Function: getModuleGroupByName
				Returns a module group entry from the module groups database.

			Parameters:
				name - The name of the module group.

			Returns:
				A module group entry.

			See Also:
				<getModuleGroup>
				<getModuleGroupByRoute>
		*/

		public static function getModuleGroupByName($name) {
			$groups = BigTreeJSONDB::getAll("module-groups");
			$name = strtolower($name);

			foreach ($groups as $group) {
				if (strtolower($group["name"]) == $name) {
					return $group;
				}
			}
			
			return null;
		}

		/*
			Function: getModuleGroupByRoute
				Returns a module group entry from the module groups database.

			Parameters:
				route - The route of the module group.

			Returns:
				A module group entry.

			See Also:
				<getModuleGroup>
				<getModuleGroupByName>
		*/

		public static function getModuleGroupByRoute($route) {
			return BigTreeJSONDB::get("module-groups", $route, "route");
		}

		/*
			Function: getModuleGroups
				Returns a list of module groups.

			Parameters:
				sort - Sort by (defaults to positioned)

			Returns:
				An array of module group entries from the module groups database.
		*/

		public static function getModuleGroups($sort = "position DESC") {
			list($sort_column, $sort_direction) = explode(" ", $sort);

			return BigTreeJSONDB::getAll("module-groups", $sort_column, $sort_direction ?: "ASC");
		}

		/*
			Function: getModuleNavigation
				Returns a list of module actions that are in navigation.

			Parameters:
				module - A module id or a module entry.

			Returns:
				An array of module actions from the modules database.
		*/

		public static function getModuleNavigation($module) {
			if (is_array($module)) {
				$module = $module["id"];
			}

			$context = BigTreeJSONDB::getSubset("modules", $module);
			$actions = $context->getAll("actions", "position");
			$nav = [];

			foreach ($actions as $action) {
				if ($action["in_nav"]) {
					$nav[] = $action;
				}
			}

			return $nav;
		}

		/*
			Function: getModuleReports
				Gets reports from the modules database.

			Parameters:
				sort - The field to sort by.
				module - Specific module to pull reports for (defaults to all modules).

			Returns:
				An array of report entries from the modules database.
		*/

		public static function getModuleReports($sort = "title",$module = false) {
			list($sort_column, $sort_direction) = explode(" ", $sort);

			if ($module) {
				$context = BigTreeJSONDB::getSubset("modules", $module);

				return $context->getAll("reports", $sort_column, $sort_direction);
			} else {
				$reports = [];
				$sort_field = [];
				$modules = BigTreeJSONDB::getAll("modules");

				foreach ($modules as $module) {
					$reports = array_merge($reports, array_filter((array) $module["reports"]));
				}

				foreach ($reports as $report) {
					$sort_field[] = $report[$sort_column];
				}

				if ($sort_direction == "DESC") {
					array_multisort($sort_field, SORT_DESC, $reports);
				} else {
					array_multisort($sort_field, SORT_ASC, $reports);
				}

				return $reports;
			}
		}

		/*
			Function: getModules
				Returns a list of modules.

			Parameters:
				sort - The sort order (defaults to oldest first).
				auth - If set to true, only returns modules the logged in user has access to. Defaults to true.

			Returns:
				An array of entries from the modules database with an additional "group_name" column for the group the module is in.
		*/

		public function getModules($sort = "id ASC", $auth = true) {
			list($sort_column, $sort_direction) = explode(" ", $sort);

			$modules = BigTreeJSONDB::getAll("modules", $sort_column, $sort_direction ?: "ASC");

			foreach ($modules as $index => $module) {
				$module["group_name"] = "";

				if ($module["group"]) {
					$group = BigTreeJSONDB::get("module-groups", $module["group"]);

					if ($group) {
						$module["group_name"] = $group["name"];
					}
				}

				if ($auth && !$this->checkAccess($module["id"])) {
					unset($modules[$index]);
				} else {
					$modules[$index] = $module;
				}
			}

			return $modules;
		}

		/*
			Function: getModulesByGroup
				Returns a list of modules in a given group.

			Parameters:
				group - The group to return modules for.
				sort - The sort order (defaults to positioned)
				auth - If set to true, only returns modules the logged in user has access to. Defaults to true.

			Returns:
				An array of entries from the modules database.
		*/

		public function getModulesByGroup($group,$sort = "position DESC, id ASC",$auth = true) {
			if (is_array($group)) {
				$group = $group["id"];
			}

			list($sort_column, $sort_direction) = explode(" ", $sort);

			$modules = BigTreeJSONDB::getAll("modules", $sort_column, $sort_direction ?: "ASC");

			foreach ($modules as $index => $module) {
				if (
					($group && $group != $module["group"]) ||
					(!$group && $module["group"]) ||
					($auth && !$this->checkAccess($module["id"]))
				) {
					unset($modules[$index]);
				}
			}

			return $modules;
		}

		/*
			Function: getModuleViews
				Returns a list of all views in the modules database.

			Parameters:
				sort - The column to sort by.
				module - Specific module to pull views for (defaults to all modules).

			Returns:
				An array of view entries with "fields" decoded.
		*/

		public static function getModuleViews($sort = "title", $module = false) {
			list($sort_column, $sort_direction) = explode(" ", $sort);

			if ($module) {
				$context = BigTreeJSONDB::getSubset("modules", $module);

				return $context->getAll("views", $sort_column, $sort_direction);
			} else {
				$views = [];
				$sort_field = [];
				$modules = BigTreeJSONDB::getAll("modules");

				foreach ($modules as $module) {
					$views = array_merge($views, array_filter((array) $module["views"]));
				}

				foreach ($views as $view) {
					$sort_field[] = $view[$sort_column];
				}

				if ($sort_direction == "DESC") {
					array_multisort($sort_field, SORT_DESC, $views);
				} else {
					array_multisort($sort_field, SORT_ASC, $views);
				}

				return $views;
			}
		}

		/*
			Function: getNaturalNavigationByParent
				Returns a list of positioned navigation that is in navigation under the given parent.
				Does not return module navigation.

			Parameters:
				parent - The ID of the parent page

			Returns:
				An array of page entries.
		*/

		public static function getNaturalNavigationByParent($parent,$levels = 1) {
			$nav = array();
			$q = sqlquery("SELECT id,nav_title AS title,parent,external,new_window,template,publish_at,expire_at,path,ga_page_views FROM bigtree_pages WHERE parent = '$parent' AND in_nav = 'on' AND archived != 'on' ORDER BY position DESC, id ASC");
			while ($nav_item = sqlfetch($q)) {
				$nav_item["external"] = BigTreeCMS::replaceRelativeRoots($nav_item["external"]);
				if ($levels > 1) {
					$nav_item["children"] = static::getNaturalNavigationByParent($nav_item["id"],$levels - 1);
				}
				$nav[] = $nav_item;
			}
			return $nav;
		}

		/*
			Function: getPageAccessLevel
				Returns the access level for the logged in user to a given page.

			Parameters:
				page - The page id.

			Returns:
				"p" for publisher, "e" for editor, false for no access.

			See Also:
				<getPageAccessLevelForUser>
		*/

		public function getPageAccessLevel($page) {
			return $this->getPageAccessLevelByUser($page,$this->ID);
		}

		/*
			Function: getPageAccessLevelByUser
				Returns the access level for the given user to a given page.

			Parameters:
				page - The page id.
				user - The user id.

			Returns:
				"p" for publisher, "e" for editor, false for no access.

			See Also:
				<getPageAccessLevel>
		*/

		public function getPageAccessLevelByUser($page,$user) {
			// See if this is a pending change, if so, grab the change's parent page and check permission levels for that instead.
			if (!is_numeric($page) && $page[0] == "p") {
				$f = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE id = '".sqlescape(substr($page,1))."'"));
				$changes = json_decode($f["changes"],true);
				return $this->getPageAccessLevelByUser($changes["parent"],$user);
			}

			// If we're checking the logged in user, just use the info we already have
			if ($user == $this->ID) {
				$level = $this->Level;
				$permissions = $this->Permissions;
			// Not the logged in user? Look up the person.
			} else {
				$u = static::getUser($user);
				$level = $u["level"];
				$permissions = $u["permissions"];
			}

			// See if the user is an administrator, if so we can skip permissions.
			if ($level > 0) {
				return "p";
			}

			// See if this page has an explicit permission set and return it if so.
			$explicit_permission = $permissions["page"][$page];
			if ($explicit_permission == "n") {
				return false;
			} elseif ($explicit_permission && $explicit_permission != "i") {
				return $explicit_permission;
			}

			// We're now assuming that this page should inherit permissions from farther up the tree, so let's grab the first parent.
			$page_data = sqlfetch(sqlquery("SELECT parent FROM bigtree_pages WHERE id = '".sqlescape($page)."'"));

			// Grab the parent's permission. Keep going until we find a permission that isn't inherit or until we hit a parent of 0.
			$parent_permission = $permissions["page"][$page_data["parent"]];
			while ((!$parent_permission || $parent_permission == "i") && $page_data["parent"]) {
				$page_data = sqlfetch(sqlquery("SELECT parent FROM bigtree_pages WHERE id = '".$page_data["parent"]."'"));
				$parent_permission = $permissions["page"][$page_data["parent"]];
			}

			// If no permissions are set on the page (we hit page 0 and still nothing) or permission is "n", return not allowed.
			if (!$parent_permission || $parent_permission == "i" || $parent_permission == "n") {
				return false;
			}

			// Return whatever we found.
			return $parent_permission;
		}

		/*
			Function: getPageAdminLinks
				Gets a list of pages that link back to the admin.

			Returns:
				An array of pages that link to the admin.
		*/

		public static function getPageAdminLinks() {
			global $bigtree;
			$pages = array();
			$q = sqlquery("SELECT * FROM bigtree_pages WHERE REPLACE(resources,'{adminroot}js/embeddable-form.js','') LIKE '%{adminroot}%' OR resources LIKE '%".$bigtree["config"]["admin_root"]."%' OR resources LIKE '%".str_replace($bigtree["config"]["www_root"],"{wwwroot}",$bigtree["config"]["admin_root"])."%'");
			while ($f = sqlfetch($q)) {
				$pages[] = $f;
			}
			return $pages;
		}

		/*
			Function: getPageChanges
				Returns pending changes for a given page.

			Parameters:
				page - The page id.

			Returns:
				An entry from bigtree_pending_changes with changes decoded.
		*/

		public static function getPageChanges($page) {
			$page = sqlescape($page);
			$c = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = '$page'"));
			if (!$c) {
				return false;
			}
			$c["changes"] = json_decode($c["changes"],true);
			return $c;
		}

		/*
			Function: getPageChildren
				Returns all non-archived children of a given page.

			Parameters:
				page - The page id to pull children for.
				sort - The way to sort results. Defaults to nav_title ASC.

			Returns:
				An array of pages.
		*/

		public static function getPageChildren($page,$sort = "nav_title ASC") {
			$page = sqlescape($page);
			$items = array();
			$q = sqlquery("SELECT * FROM bigtree_pages WHERE parent = '$page' AND archived != 'on' ORDER BY $sort");
			while ($f = sqlfetch($q)) {
				$items[] = $f;
			}
			return $items;
		}

		/*
			Function: getPageLineage
				Returns all the ids of pages above this page.

			Parameters:
				page - Page ID

			Returns:
				Array of IDs
		*/

		public function getPageLineage($page) {
			$parents = array();
			$f = sqlfetch(sqlquery("SELECT parent FROM bigtree_pages WHERE id = '".sqlescape($page)."'"));
			$parents[] = $f["parent"];
			while ($f["parent"]) {
				$f = sqlfetch(sqlquery("SELECT parent FROM bigtree_pages WHERE id = '".sqlescape($f["parent"])."'"));
				if ($f["parent"]) {
					$parents[] = $f["parent"];
				}
			}
			return $parents;
		}

		/*
			Function: getPageIds
				Returns all the IDs in bigtree_pages for pages that aren't archived.

			Returns:
				An array of page ids.
		*/

		public static function getPageIds() {
			$ids = array();
			$q = sqlquery("SELECT id FROM bigtree_pages WHERE archived != 'on' ORDER BY id ASC");
			while ($f = sqlfetch($q)) {
				$ids[] = $f["id"];
			}
			return $ids;
		}

		/*
			Function: getPageIDForPath
				Provides the page ID for a given path array.
				This is equivalent to BigTreeCMS::getNavId.

			Parameters:
				path - An array of path elements from a URL
				previewing - Whether we are previewing or not.

			Returns:
				An array containing:
					- The page ID (or false)
					- An array of commands
					- The routed status of the page
					- GET variables
					- URL Hash
		*/

		public static function getPageIDForPath($path, $previewing = false) {
			$commands = array();

			// Get any GET variables and hashes and remove them
			$url_parse = parse_url(implode("/", array_values($path)));
			$query_vars = $url_parse["query"];
			$hash = $url_parse["fragment"];
			$path = explode("/", rtrim($url_parse["path"], "/"));

			if (!$previewing) {
				$publish_at = "AND (publish_at <= NOW() OR publish_at IS NULL) AND (expire_at >= NOW() OR expire_at IS NULL)";
			} else {
				$publish_at = "";
			}

			// See if we have a straight up perfect match to the path.
			$page = SQL::fetch("SELECT id, template FROM bigtree_pages WHERE path = ? AND archived = '' $publish_at", implode("/", $path));

			if ($page) {
				$template = BigTreeJSONDB::get("templates", $page["template"]);

				return array($page["id"], [], $template["routed"], $query_vars, $hash);
			}

			// Guess we don't, let's chop off commands until we find a page.
			$x = 0;

			while ($x < count($path)) {
				$x++;
				$commands[] = $path[count($path) - $x];

				// We have additional commands, so we're now making sure the template is also routed, otherwise it's a 404.
				$page = SQL::fetch("SELECT id, template FROM bigtree_pages WHERE path = ? AND archived = '' $publish_at", implode("/", array_slice($path, 0, -1 * $x)));

				if ($page) {
					$template = BigTreeJSONDB::get("templates", $page["template"]);

					if ($template["routed"]) {
						return array($page["id"], array_reverse($commands), "on", $query_vars, $hash);
					}
				}
			}

			return array(false, false, false, false, false);
		}

		/*
			Function: getPageOfSettings
				Returns a page of settings the logged in user has access to.

			Parameters:
				page - The page to return.
				query - Optional query string to search against.
				sort - Sort order. Defaults to name ASC.

			Returns:
				An array of entries from the settings database.
				If the setting is encrypted the value will be "[Encrypted Text]", otherwise it will be decoded.
				If the calling user is a developer, returns locked settings, otherwise they are left out.
		*/

		public function getPageOfSettings($page = 1, $query = "", $return_count = false) {
			$settings = $this->getSettings();

			// Get all the values
			foreach ($settings as $index => $setting) {
				if ($setting["locked"] && $this->Level < 2) {
					unset($settings[$index]);
				} else {
					$settings[$index]["value"] = BigTreeCMS::getSetting($setting["id"]);
				}
			}

			// If we're querying...
			if ($query) {
				foreach ($settings as $index => $setting) {
					if (stripos($setting["name"], $query) === false && stripos($setting["value"], $query) === false) {
						unset($settings[$index]);
					}
				}
			}

			if ($return_count) {
				return count($settings);
			}

			return BigTree::untranslateArray(array_slice($settings, ($page - 1) * static::$PerPage, static::$PerPage));
		}

		/*
			Function: getPageOfTags
				Returns a page of tags.

			Parameters:
				page - The page of tags to return.
				query - Optional query string to search against.
				sort_column - Column to sort by - defaults to tag.
				sort_direction - Direction to sort by - defaults to ascending.

			Returns:
				An array of entries from bigtree_users.
		*/

		public static function getPageOfTags($page = 1, $query = "", $sort_column = "tag", $sort_direction = "ASC") {
			if ($query) {
				$q = sqlquery("SELECT * FROM bigtree_tags WHERE tag LIKE '%".sqlescape($query)."%' ORDER BY $sort_column $sort_direction LIMIT ".(($page - 1) * static::$PerPage).",".static::$PerPage);
			} else {
				$q = sqlquery("SELECT * FROM bigtree_tags ORDER BY $sort_column $sort_direction LIMIT ".(($page - 1) * static::$PerPage).",".static::$PerPage);
			}

			$items = array();

			while ($f = sqlfetch($q)) {
				$items[] = $f;
			}

			return $items;
		}

		/*
			Function: getPageOfUsers
				Returns a page of users.

			Parameters:
				page - The page of users to return.
				query - Optional query string to search against.
				sort - Order to sort the results by. Defaults to name ASC.

			Returns:
				An array of entries from bigtree_users.
		*/

		public static function getPageOfUsers($page = 1,$query = "",$sort = "name ASC") {
			// If we're searching.
			if ($query) {
				$qparts = explode(" ",$query);
				$qp = array();
				foreach ($qparts as $part) {
					$part = sqlescape(strtolower($part));
					$qp[] = "(LOWER(name) LIKE '%$part%' OR LOWER(email) LIKE '%$part%' OR LOWER(company) LIKE '%$part%')";
				}
				$q = sqlquery("SELECT * FROM bigtree_users WHERE ".implode(" AND ",$qp)." ORDER BY $sort LIMIT ".(($page - 1) * static::$PerPage).",".static::$PerPage);
			// If we're grabbing anyone.
			} else {
				$q = sqlquery("SELECT * FROM bigtree_users ORDER BY $sort LIMIT ".(($page - 1) * static::$PerPage).",".static::$PerPage);
			}

			$items = array();
			while ($f = sqlfetch($q)) {
				$items[] = $f;
			}

			return $items;
		}

		/*
			Function: getPageRevision
				Returns a version of a page from the bigtree_page_revisions table.

			Parameters:
				id - The id of the page version.

			Returns:
				A page version entry from the table.
		*/

		public static function getPageRevision($id) {
			$id = sqlescape($id);
			$item = sqlfetch(sqlquery("SELECT * FROM bigtree_page_revisions WHERE id = '$id'"));
			return $item;
		}

		/*
			Function: getPageRevisions
				Get all revisions for a page.

			Parameters:
				page - The page id to get revisions for.

			Returns:
				An array of "saved" revisions and "unsaved" revisions.
		*/

		public static function getPageRevisions($page) {
			// Get all previous revisions, add them to the saved or unsaved list
			$unsaved = [];
			$saved = [];
			$revisions = SQL::fetchAll("SELECT bigtree_users.name, bigtree_users.email, bigtree_page_revisions.saved,  bigtree_page_revisions.has_deleted_resources,
											   bigtree_page_revisions.saved_description, bigtree_page_revisions.updated_at, bigtree_page_revisions.id 
										FROM bigtree_page_revisions JOIN bigtree_users ON bigtree_page_revisions.author = bigtree_users.id
										WHERE page = ?
										ORDER BY updated_at DESC", $page);
			
			foreach ($revisions as $revision) {
				if ($revision["saved"]) {
					$saved[] = $revision;
				} else {
					$unsaved[] = $revision;
				}
			}

			return array("saved" => $saved, "unsaved" => $unsaved);
		}

		/*
			Function: getPages
				Returns all pages from the database.

			Returns:
				Array of unmodified entries from bigtree_pages.
		*/

		public static function getPages() {
			$items = array();
			$q = sqlquery("SELECT * FROM bigtree_pages ORDER BY id ASC");
			while ($f = sqlfetch($q)) {
				$items[] = $f;
			}
			return $items;
		}

		/*
			Function: getPageSEORating
				Returns the SEO rating for a page.

			Parameters:
				page - A page array.
				content - An array of resources.

			Returns:
				An array of SEO data.
				"score" reflects a score from 0 to 100 points.
				"recommendations" is an array of recommendations to improve SEO score.
				"color" is a color reflecting the SEO score.

				Score Parameters
				- Having a title - 5 points
				- Having a unique title - 5 points
				- Title does not exceed 72 characters and has at least 4 words - 5 points
				- Having a meta description - 5 points
				- Meta description that is less than 165 characters - 5 points
				- Having an h1 - 10 points
				- Having page content - 5 points
				- Having at least 300 words in your content - 15 points
				- Having links in your content - 5 points
				- Having external links in your content - 5 points
				- Having one link for every 120 words of content - 5 points
				- Readability Score - up to 20 points
				- Fresh content - up to 10 points
		*/

		public static function getPageSEORating($page,$content) {
			$template = BigTreeCMS::getTemplate($page["template"]);
			$tsources = array();
			$h1_field = "";
			$body_fields = array();

			if (is_array($template["resources"])) {
				foreach ($template["resources"] as $item) {
					if (isset($item["seo_body"]) && $item["seo_body"]) {
						$body_fields[] = $item["id"];
					}
					if (isset($item["seo_h1"]) && $item["seo_h1"]) {
						$h1_field = $item["id"];
					}
					$tsources[$item["id"]] = $item;
				}
			}

			if (!$h1_field && $tsources["page_header"]) {
				$h1_field = "page_header";
			}
			if (!count($body_fields) && $tsources["page_content"]) {
				$body_fields[] = "page_content";
			}

			$textStats = new TextStatistics;
			$recommendations = array();

			$score = 0;

			// Check if they have a page title.
			if ($page["title"]) {
				$score += 5;
				// They have a title, let's see if it's unique
				$r = sqlrows(sqlquery("SELECT * FROM bigtree_pages WHERE title = '".sqlescape($page["title"])."' AND id != '".sqlescape($page["id"])."'"));
				if ($r == 0) {
					// They have a unique title
					$score += 5;
				} else {
					$recommendations[] = "Your page title should be unique. ".($r-1)." other page(s) have the same title.";
				}
				$words = $textStats->word_count($page["title"]);
				$length = mb_strlen($page["title"]);
				if ($words >= 4 && $length <= 72) {
					// Fits the bill!
					$score += 5;
				} else {
					$recommendations[] = "Your page title should be no more than 72 characters and should contain at least 4 words.";
				}
			} else {
				$recommendations[] = "You should enter a page title.";
			}

			// Check for meta description
			if ($page["meta_description"]) {
				$score += 5;
				// They have a meta description, let's see if it's no more than 165 characters.
				if (mb_strlen($page["meta_description"]) <= 165) {
					$score += 5;
				} else {
					$recommendations[] = "Your meta description should be no more than 165 characters. It is currently ".mb_strlen($page["meta_description"])." characters.";
				}
			} else {
				$recommendations[] = "You should enter a meta description.";
			}

			// Check for an H1
			if (!$h1_field || $content[$h1_field]) {
				$score += 10;
			} else {
				$recommendations[] = "You should enter a page header.";
			}
			// Check the content!
			if (!count($body_fields)) {
				// If this template doesn't for some reason have a seo body resource, give the benefit of the doubt.
				$score += 65;
			} else {
				$regular_text = "";
				$stripped_text = "";
				foreach ($body_fields as $field) {
					if (!is_array($content[$field])) {
						$regular_text .= $content[$field]." ";
						$stripped_text .= strip_tags($content[$field])." ";
					}
				}
				// Check to see if there is any content
				if ($stripped_text) {
					$score += 5;
					$words = $textStats->word_count($stripped_text);
					$readability = $textStats->flesch_kincaid_reading_ease($stripped_text);
					if ($readability < 0) {
						$readability = 0;
					}
					$number_of_links = substr_count($regular_text,"<a ");
					$number_of_external_links = substr_count($regular_text,'href="http://');

					// See if there are at least 300 words.
					if ($words >= 300) {
						$score += 15;
					} else {
						$recommendations[] = "You should enter at least 300 words of page content. You currently have ".$words." word(s).";
					}

					// See if we have any links
					if ($number_of_links) {
						$score += 5;
						// See if we have at least one link per 120 words.
						if (floor($words / 120) <= $number_of_links) {
							$score += 5;
						} else {
							$recommendations[] = "You should have at least one link for every 120 words of page content. You currently have $number_of_links link(s). You should have at least ".floor($words / 120).".";
						}
						// See if we have any external links.
						if ($number_of_external_links) {
							$score += 5;
						} else {
							$recommendations[] = "Having an external link helps build Page Rank.";
						}
					} else {
						$recommendations[] = "You should have at least one link in your content.";
					}

					// Check on our readability score.
					if ($readability >= 90) {
						$score += 20;
					} else {
						$read_score = round(($readability / 90),2);
						$recommendations[] = "Your readability score is ".($read_score*100)."%. Using shorter sentences and words with fewer syllables will make your site easier to read by search engines and users.";
						$score += ceil($read_score * 20);
					}
				} else {
					$recommendations[] = "You should enter page content.";
				}

				// Check page freshness
				$updated = strtotime($page["updated_at"]);
				$age = time()-$updated-(60*24*60*60);
				// See how much older it is than 2 months.
				if ($age > 0) {
					$age_score = 10 - floor(2 * ($age / (30*24*60*60)));
					if ($age_score < 0) {
						$age_score = 0;
					}
					$score += $age_score;
					$recommendations[] = "Your content is around ".ceil(2 + ($age / (30*24*60*60)))." months old. Updating your page more frequently will make it rank higher.";
				} else {
					$score += 10;
				}
			}

			$color = "#008000";
			if ($score <= 50) {
				$color = BigTree::colorMesh("#CCAC00","#FF0000",100-(100 * $score / 50));
			} elseif ($score <= 80) {
				$color = BigTree::colorMesh("#008000","#CCAC00",100-(100 * ($score-50) / 30));
			}

			return array("score" => $score, "recommendations" => $recommendations, "color" => $color);
		}

		/*
			Function: getPendingChange
				Returns a pending change from the bigtree_pending_changes table.

			Parameters:
				id - The id of the change.

			Returns:
				A entry from the table with the "changes" column decoded.
		*/

		public static function getPendingChange($id) {
			$id = sqlescape($id);
			$item = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE id = '$id'"));
			
			if (!$item) {
				return false;
			}

			$item["changes"] = json_decode($item["changes"], true);
			$item["mtm_changes"] = json_decode($item["mtm_changes"], true);
			$item["tags_changes"] = json_decode($item["tags_changes"], true);
			$item["open_graph_changes"] = json_decode($item["open_graph_changes"], true);

			return BigTree::untranslateArray($item);
		}

		/*
			Function: getPublishableChanges
				Returns a list of changes that the logged in user has access to publish.

			Parameters:
				user - The user id to retrieve changes for. Defaults to the logged in user.

			Returns:
				An array of changes sorted by most recent.
		*/

		public function getPublishableChanges($user = false) {
			if (!$user) {
				$user = static::getUser($this->ID);
			} else {
				$user = static::getUser($user);
			}

			$changes = array();
			// Setup the default search array to just be pages
			$search = array("`module` = ''");
			// Add each module the user has publisher permissions to
			if (is_array($user["permissions"]["module"])) {
				foreach ($user["permissions"]["module"] as $module => $permission) {
					if ($permission == "p") {
						$search[] = "`module` = '$module'";
					}
				}
			}

			// Add module group based permissions as well
			if (isset($user["permissions"]["module_gbp"]) && is_array($user["permissions"]["module_gbp"])) {
				foreach ($user["permissions"]["module_gbp"] as $module => $groups) {
					foreach ($groups as $group => $permission) {
						if ($permission == "p") {
							$search[] = "`module` = '$module'";
						}
					}
				}
			}

			$q = sqlquery("SELECT * FROM bigtree_pending_changes WHERE ".implode(" OR ",$search)." ORDER BY date DESC");

			while ($f = sqlfetch($q)) {
				$ok = false;

				if (!$f["item_id"]) {
					$id = "p".$f["id"];
				} else {
					$id = $f["item_id"];
				}

				// If they're an admin, they've got it.
				if ($user["level"] > 0) {
					$ok = true;
				// Check permissions on a page if it's a page.
				} elseif ($f["table"] == "bigtree_pages") {
					$r = $this->getPageAccessLevelByUser($id,$user["id"]);
					// If we're a publisher, this is ours!
					if ($r == "p") {
						$ok = true;
					}
				} else {
					// Check our list of modules.
					if ($user["permissions"]["module"][$f["module"]] == "p") {
						$ok = true;
					} else {
						// Check our group based permissions
						$item = BigTreeAutoModule::getPendingItem($f["table"],$id);
						$level = $this->getAccessLevel(static::getModule($f["module"]),$item["item"],$f["table"],$user);
						if ($level == "p") {
							$ok = true;
						}
					}
				}

				// We're a publisher, get the info about the change and put it in the change list.
				if ($ok) {
					$f["mod"] = static::getModule($f["module"]);
					$f["user"] = static::getUser($f["user"]);
					$changes[] = $f;
				}
			}

			return $changes;
		}

		/*
			Function: getPendingChanges
				Returns a list of changes for a given user.

			Parameters:
				user - The user id to retrieve changes for. Defaults to the logged in user.

			Returns:
				An array of changes sorted by most recent.
		*/

		public function getPendingChanges($user = false) {
			if (is_array($user)) {
				$user = $user["id"];
			} elseif (!$user) {
				$user = $this->ID;
			}

			$changes = array();
			$q = sqlquery("SELECT * FROM bigtree_pending_changes WHERE user = '".sqlescape($user)."' ORDER BY date DESC");
			while ($f = sqlfetch($q)) {
				$changes[] = $f;
			}

			return $changes;
		}

		/*
			Function: getPendingNavigationByParent
				Returns a list of pending pages under a given parent ordered by most recent.

			Parameters:
				parent - The ID of the parent page
				in_nav - true returns pages in navigation, false returns hidden pages

			Returns:
				An array of pending page titles/ids.
		*/

		public static function getPendingNavigationByParent($parent,$in_nav = true) {
			$nav = array();
			$titles = array();
			$q = sqlquery("SELECT * FROM bigtree_pending_changes WHERE pending_page_parent = '$parent' AND `table` = 'bigtree_pages' AND type = 'NEW' ORDER BY date DESC");
			while ($f = sqlfetch($q)) {
				$page = json_decode($f["changes"],true);
				if (($page["in_nav"] && $in_nav) || (!$page["in_nav"] && !$in_nav)) {
					$titles[] = $page["nav_title"];
					$page["bigtree_pending"] = true;
					$page["title"] = $page["nav_title"];
					$page["id"] = "p".$f["id"];
					$nav[] = $page;
				}
			}
			array_multisort($titles,$nav);
			return $nav;
		}

		/*
			Function: getContentsOfResourceFolder
				Returns a list of resources and subfolders in a folder.

			Parameters:
				folder - The id of a folder or a folder entry.
				sort - The column to sort the folder's files on (default: date DESC).

			Returns:
				An array of two arrays - folders and resources.
		*/

		public static function getContentsOfResourceFolder($folder, $sort = "date DESC") {
			if (is_array($folder)) {
				$folder = $folder["id"];
			}
			$folder = sqlescape($folder);

			$folders = array();
			$resources = array();

			$q = sqlquery("SELECT * FROM bigtree_resource_folders WHERE parent = '$folder' ORDER BY name");
			while ($f = sqlfetch($q)) {
				$folders[] = $f;
			}

			if ($folder) {
				$q = sqlquery("SELECT * FROM bigtree_resources WHERE folder = '$folder' ORDER BY $sort");
			} else {
				$q = sqlquery("SELECT * FROM bigtree_resources WHERE folder = 0 OR folder IS NULL ORDER BY $sort");
			}
			while ($f = sqlfetch($q)) {
				$resources[] = $f;
			}

			return array("folders" => $folders, "resources" => $resources);
		}

		/*
			Function: getResourceByFile
				Returns a resource with the given file name.

			Parameters:
				file - The file name.

			Returns:
				An entry from bigtree_resources with file and thumbs decoded.
		*/

		public static function getResourceByFile($file) {
			if (static::$IRLPrefixes === false) {
				static::$IRLPrefixes = array();
				$settings = BigTreeJSONDB::get("config", "media-settings");

				if (is_array($settings["presets"]["default"]["crops"])) {
					foreach ($settings["presets"]["default"]["crops"] as $crop) {
						if (!empty($crop["prefix"])) {
							static::$IRLPrefixes[] = $crop["prefix"];
						}
	
						if (!empty($crop["thumbs"]) && is_array($crop["thumbs"])) {
							foreach ($crop["thumbs"] as $thumb) {
								if (!empty($thumb["prefix"])) {
									static::$IRLPrefixes[] = $thumb["prefix"];
								}
							}
						} 
					}
				}

				if (is_array($settings["presets"]["default"]["thumbs"])) {
					foreach ($settings["presets"]["default"]["thumbs"] as $thumb) {
						if (!empty($thumb["prefix"])) {
							static::$IRLPrefixes[] = $thumb["prefix"];
						}
					}
				}


				if (is_array($settings["presets"]["default"]["center_crops"])) {
					foreach ($settings["presets"]["default"]["center_crops"] as $crop) {
						if (!empty($crop["prefix"])) {
							static::$IRLPrefixes[] = $crop["prefix"];
						}
					}
				}
			}

			$last_prefix = false;
			$tokenized_file = BigTreeCMS::replaceHardRoots($file);
			$single_domain_tokenized_file = static::stripMultipleRootTokens($tokenized_file);
			$item = sqlfetch(sqlquery("SELECT * FROM bigtree_resources WHERE file = '".sqlescape($file)."' OR file = '".sqlescape($tokenized_file)."' OR file = '".sqlescape($single_domain_tokenized_file)."'"));

			// Convert {wwwroot} to {staticroot} and see if that fixes it
			if (!$item) {
				$item = sqlfetch(sqlquery("SELECT * FROM bigtree_resources WHERE file = '".sqlescape($file)."' OR file = '".sqlescape(str_replace("{wwwroot}", "{staticroot}", $tokenized_file))."' OR file = '".sqlescape($single_domain_tokenized_file)."'"));
			}

			if (!$item) {
				foreach (static::$IRLPrefixes as $prefix) {
					if (!$item) {
						$sfile = str_replace("files/resources/$prefix", "files/resources/", $file);
						$tokenized_file = BigTreeCMS::replaceHardRoots($sfile);
						$single_domain_tokenized_file = static::stripMultipleRootTokens($tokenized_file);
						$item = sqlfetch(sqlquery("SELECT * FROM bigtree_resources WHERE file = '".sqlescape($sfile)."' OR file = '".sqlescape($tokenized_file)."' OR file = '".sqlescape($single_domain_tokenized_file)."'"));
						$last_prefix = $prefix;
					}
				}

				if (!$item) {
					return false;
				}
			}

			$item["prefix"] = $last_prefix;
			$item["crops"] = json_decode($item["crops"], true);
			$item["thumbs"] = json_decode($item["thumbs"], true);
			$item["metadata"] = json_decode($item["metadata"], true);
			$item["video_data"] = json_decode($item["video_data"], true);

			return BigTree::untranslateArray($item);
		}

		/*
			Function: getResource
				Returns a resource.

			Parameters:
				id - The id of the resource.

			Returns:
				A resource entry.
		*/

		public static function getResource($id) {
			$resource = SQL::fetch("SELECT * FROM bigtree_resources WHERE id = ?", $id);

			if (!$resource) {
				return false;
			}

			$resource["crops"] = json_decode($resource["crops"], true);
			$resource["thumbs"] = json_decode($resource["thumbs"], true);
			$resource["metadata"] = json_decode($resource["metadata"], true);
			$resource["video_data"] = json_decode($resource["video_data"], true);

			return BigTree::untranslateArray($resource);
		}

		/*
			Function: getResourceAllocation
				Returns the places a resource is used.

			Parameters:
				id - The id of the resource.

			Returns:
				An array of entries from the bigtree_resource_allocation table.
		*/

		public static function getResourceAllocation($id) {
			return SQL::fetchAll("SELECT * FROM bigtree_resource_allocation WHERE resource = ? ORDER BY updated_at DESC", $id);
		}

		/*
			Function: getResourceFolder
				Returns a resource folder.

			Parameters:
				id - The id of the folder.

			Returns:
				A resource folder entry.
		*/

		public static function getResourceFolder($id) {
			$id = sqlescape($id);
			return sqlfetch(sqlquery("SELECT * FROM bigtree_resource_folders WHERE id = '$id'"));
		}

		/*
			Function: getResourceFolderAllocationCounts
				Returns the number of items inside a folder and it's subfolders and the number of allocations of the contained resources.

			Parameters:
				folder - The id of the folder.

			Returns:
				A keyed array of "resources", "folders", and "allocations" for the number of resources, sub folders, and allocations.
		*/

		public static function getResourceFolderAllocationCounts($folder) {
			$allocations = $folders = $resources = 0;

			$items = static::getContentsOfResourceFolder($folder);
			foreach ($items["folders"] as $folder) {
				$folders++;
				$subs = static::getResourceFolderAllocationCounts($folder["id"]);
				$allocations += $subs["allocations"];
				$folders += $subs["folders"];
				$resources += $subs["resources"];
			}
			foreach ($items["resources"] as $resource) {
				$resources++;
				$allocations += count(static::getResourceAllocation($resource["id"]));
			}
			return array("allocations" => $allocations,"folders" => $folders,"resources" => $resources);
		}

		/*
			Function: getResourceFolderBreadcrumb
				Returns a breadcrumb of the given folder.

			Parameters:
				folder - The id of a folder or a folder entry.

			Returns:
				An array of arrays containing the name and id of folders above.
		*/

		public static function getResourceFolderBreadcrumb($folder,$crumb = array()) {
			if (!is_array($folder)) {
				$folder = sqlfetch(sqlquery("SELECT * FROM bigtree_resource_folders WHERE id = '".sqlescape($folder)."'"));
			}

			if ($folder) {
				$crumb[] = array("id" => $folder["id"], "name" => $folder["name"]);
			}

			if ($folder["parent"]) {
				return static::getResourceFolderBreadcrumb($folder["parent"],$crumb);
			} else {
				$crumb[] = array("id" => 0, "name" => "Home");
				return array_reverse($crumb);
			}
		}

		/*
			Function: getResourceFolderChildren
				Returns the child folders of a resource folder.

			Parameters:
				id - The id of the parent folder.

			Returns:
				An array of resource folder entries.
		*/

		public static function getResourceFolderChildren($id) {
			$items = array();
			$id = sqlescape($id);
			$q = sqlquery("SELECT * FROM bigtree_resource_folders WHERE parent = '$id' ORDER BY name ASC");
			while ($f = sqlfetch($q)) {
				$items[] = $f;
			}
			return $items;
		}

		/*
			Function: getResourceFolderPermission
				Returns the permission level of the current user for the folder.

			Parameters:
				folder - The id of a folder or a folder entry.

			Returns:
				"p" if a user can create folders and upload files, "e" if the user can see/use files, "n" if a user can't access this folder.
		*/

		public function getResourceFolderPermission($folder) {
			// User is an admin or developer
			if ($this->Level > 0) {
				return "p";
			}

			// We're going to save the folder entry in case we need its parent later.
			if (is_array($folder)) {
				$id = $folder["id"];
			} else {
				$id = $folder;
			}

			$p = $this->Permissions["resources"][$id];
			// If p is already no, creator, or consumer we can just return it.
			if ($p && $p != "i") {
				return $p;
			} else {
				// If folder is 0, we're already at home and can't check a higher folder for permissions.
				if (!$folder) {
					return "e";
				}

				// If a folder entry wasn't passed in, we need it to find its parent.
				if (!is_array($folder)) {
					$folder = sqlfetch(sqlquery("SELECT parent FROM bigtree_resource_folders WHERE id = '".sqlescape($id)."'"));
				}
				// If we couldn't find the folder anymore, just say they can consume.
				if (!$folder) {
					return "e";
				}

				// Return the parent's permissions
				return $this->getResourceFolderPermission($folder["parent"]);
			}
		}

		/*
			Function: getRoutedTemplates
				Returns a list of routed templates ordered by position that the logged in user has access to.

			Parameters:
				sort - Sort order, defaults to positioned

			Returns:
				An array of template entries.
		*/

		public function getRoutedTemplates($sort = "position DESC, id ASC") {
			list($sort_column, $sort_direction) = explode(" ", $sort);
			$templates = BigTreeJSONDB::getAll("templates", $sort_column, $sort_direction ?: "ASC");
			$basic = [];

			foreach ($templates as $template) {
				if ($template["level"] <= $this->Level && $template["routed"]) {
					$basic[] = $template;
				}
			}

			return $basic;
		}

		/*
			Function: getSetting
				Returns a setting.

			Parameters:
				id - The id of the setting to return.
				decode - Whether to decode the array or not. Large data sets may want to set this to false if there aren't internal page links.

			Returns:
				A setting entry with its value properly decoded and decrypted.
				Returns false if the setting could not be found.
		*/

		public static function getSetting($id, $decode = true) {
			global $bigtree;

			$id = BigTreeCMS::extensionSettingCheck($id);
			$setting = BigTreeJSONDB::get("settings", $id);

			if (!$setting) {
				return false;
			}

			if ($setting["encrypted"]) {
				$setting["value"] = SQL::fetchSingle("SELECT AES_DECRYPT(`value`, ?) FROM bigtree_settings WHERE id = ?", $bigtree["config"]["settings_key"], $id);
			} else {
				$setting["value"] = SQL::fetchSingle("SELECT value FROM bigtree_settings WHERE id = ?", $id);	
			}

			// Decode the JSON value
			if ($decode) {
				$setting["value"] = json_decode($setting["value"], true);

				if (is_array($setting["value"])) {
					$setting["value"] = BigTree::untranslateArray($setting["value"]);
				} else {
					$setting["value"] = BigTreeCMS::replaceInternalPageLinks($setting["value"]);
				}
			}

			return $setting;
		}

		/*
			Function: getSettings
				Returns a list of all settings that the logged in user has access to.

			Parameters:
				sort - Order to return the settings. Defaults to name ASC.

			Returns:
				An array of entries from the settings database.
				If the setting is encrypted the value will be "[Encrypted Text]", otherwise it will be decoded.
				If the calling user is a developer, returns locked settings, otherwise they are left out.
		*/

		public function getSettings($sort = "name ASC") {
			list($sort_column, $sort_direction) = explode(" ", $sort);
			$settings = BigTreeJSONDB::getAll("settings", $sort_column, $sort_direction);

			foreach ($settings as $index => $setting) {
				if ($setting["locked"] && $this->Level < 2) {
					unset($settings[$index]);
				} else {
					$setting["value"] = $setting["encrypted"] ? "[Encrypted Text]" : BigTreeCMS::getSetting($setting["id"]);
					$settings[$index] = BigTree::untranslateArray($setting);
				}
			}

			return $settings;
		}

		/*
			Function: getSettingsPageCount
				Returns the number of pages of settings that the logged in user has access to.

			Parameters:
				query - Optional string to query against.

			Returns:
				The number of pages of settings.
		*/

		public function getSettingsPageCount($query = "") {
			$count = $this->getPageOfSettings(1, $query, true);

			return ceil($count / static::$PerPage);
		}

		/*
			Function: getTag
				Returns a tag for the given id.

			Parameters:
				id - The id of the tag.

			Returns:
				A bigtree_tags entry.
		*/

		public static function getTag($id) {
			$id = sqlescape($id);
			return sqlfetch(sqlquery("SELECT * FROM bigtree_tags WHERE id = '$id'"));
		}

		/*
			Function: getTagsPageCount
				Returns the number of pages of tags.

			Parameters:
				query - Optional query to search against.

			Returns:
				An integer.
		*/

		public static function getTagsPageCount($query = "") {
			if ($query) {
				$f = sqlfetch(sqlquery("SELECT COUNT(*) AS `count` FROM bigtree_tags WHERE tag LIKE '%".sqlescape($query)."%'"));
			} else {
				$f = sqlfetch(sqlquery("SELECT COUNT(*) AS `count` FROM bigtree_tags"));
			}

			$pages = ceil($f["count"] / static::$PerPage);

			return $pages ?: 1;
		}

		/*
			Function: getTemplates
				Returns a list of templates.

			Parameters:
				sort - Sort order, defaults to positioned.

			Returns:
				An array of template entries.
		*/

		public static function getTemplates($sort = "position") {
			list($sort_column, $sort_direction) = explode(" ", $sort);

			return BigTreeJSONDB::getAll("templates", $sort_column, $sort_direction ?: "ASC");
		}

		/*
			Function: getUniqueModuleRoute
				Returns an available module route based on the desired route.

			Parameters:
				route - A route string

			Returns:
				A string
		*/

		public function getUniqueModuleRoute($route) {
			$route = BigTreeCMS::urlify($route);

			// Go through the hard coded modules
			$existing = array();
			$d = opendir(SERVER_ROOT."core/admin/modules/");
			
			while ($f = readdir($d)) {
				if ($f != "." && $f != "..") {
					$existing[] = $f;
				}
			}

			// Go through the directories (really ajax, css, images, js)
			$d = opendir(SERVER_ROOT."core/admin/");
			
			while ($f = readdir($d)) {
				if ($f != "." && $f != "..") {
					$existing[] = $f;
				}
			}

			// Go through the hard coded pages
			$d = opendir(SERVER_ROOT."core/admin/pages/");
			
			while ($f = readdir($d)) {
				if ($f != "." && $f != "..") {
					// Drop the .php
					$existing[] = substr($f, 0, -4);
				}
			}

			// Go through already created modules
			$modules = BigTreeJSONDB::getAll("modules");

			foreach ($modules as $module) {
				$existing[] = $module["route"];
			}

			// Get a unique route
			$x = 2;
			$oroute = $route;

			while (in_array($route, $existing)) {
				$route = $oroute."-".$x;
				$x++;
			}

			return $route;
		}

		/*
			Function: getUnreadMessageCount
				Returns the number of unread messages for the logged in user.

			Returns:
				The number of unread messages.
		*/

		public function getUnreadMessageCount() {
			return sqlrows(sqlquery("SELECT id FROM bigtree_messages WHERE recipients LIKE '%|".$this->ID."|%' AND read_by NOT LIKE '%|".$this->ID."|%'"));
		}

		/*
			Function: getUser
				Gets a user's decoded information.

			Parameters:
				id - The id of the user to return.

			Returns:
				A user entry from bigtree_users with permissions and alerts decoded.
		*/

		public static function getUser($id) {
			$user = SQL::fetch("SELECT * FROM bigtree_users WHERE id = ?", $id);
			
			if (!$user) {
				return false;
			}

			if ($user["level"] > 0) {
				$permissions = [];
				$modules = BigTreeJSONDB::getAll("modules");

				foreach ($modules as $module) {
					$permissions["module"][$module["id"]] = "p";
				}

				$user["permissions"] = $permissions;
			} else {
				$user["permissions"] = json_decode($user["permissions"], true);
			}

			$user["alerts"] = json_decode($user["alerts"], true);

			return $user;
		}

		/*
			Function: getUserByEmail
				Gets a user entry for a given email.

			Parameters:
				email - The email to find.

			Returns:
				A user entry from bigtree_users.
		*/

		public static function getUserByEmail($email) {
			$email = sqlescape($email);
			return sqlfetch(sqlquery("SELECT * FROM bigtree_users WHERE email = '$email'"));
		}

		/*
			Function: getUserByHash
				Gets a user entry for a change password hash.

			Parameters:
				hash - The hash to find.

			Returns:
				A user entry from bigtree_users.
		*/

		public static function getUserByHash($hash) {
			$hash = sqlescape($hash);
			return sqlfetch(sqlquery("SELECT * FROM bigtree_users WHERE change_password_hash = '$hash'"));
		}

		/*
			Function: getUsers
				Returns a list of all users.

			Parameters:
				sort - Order to sort the list. Defaults to name ASC.

			Returns:
				An array of entries from bigtree_users.
				The keys of the array are the ids of the user.
		*/

		public static function getUsers($sort = "name ASC") {
			$items = array();
			$q = sqlquery("SELECT * FROM bigtree_users ORDER BY $sort");
			while ($f = sqlfetch($q)) {
				$items[$f["id"]] = $f;
			}

			return $items;
		}

		/*
			Function: getUsersPageCount
				Returns the number of pages of users.

			Parameters:
				query - Optional query string to search against.

			Returns:
				The number of pages of results.
		*/

		public static function getUsersPageCount($query = "") {
			// If we're searching.
			if ($query) {
				$qparts = explode(" ",$query);
				$qp = array();
				foreach ($qparts as $part) {
					$part = sqlescape(strtolower($part));
					$qp[] = "(LOWER(name) LIKE '%$part%' OR LOWER(email) LIKE '%$part%' OR LOWER(company) LIKE '%$part%')";
				}
				$q = sqlquery("SELECT id FROM bigtree_users WHERE ".implode(" AND ",$qp));
			// If we're showing all.
			} else {
				$q = sqlquery("SELECT id FROM bigtree_users");
			}

			$r = sqlrows($q);
			$pages = ceil($r / static::$PerPage);
			if ($pages == 0) {
				$pages = 1;
			}

			return $pages;
		}

		/*
			Function: growl
				Sets up a growl session for the next page reload.

			Parameters:
				title - The section message for the growl.
				message - The description of what happened.
				type - The icon to draw.
		*/

		public static function growl($title,$message,$type = "success") {
			$_SESSION["bigtree_admin"]["growl"] = array("message" => $message, "title" => $title, "type" => $type);
		}

		/*
			Function: handleOpenGraph
				Handles open graph updates for a piece of data.

			Parameters:
				table - The table for the entry
				id - The ID of the entry
				data_source - Data Source (defaults to $_POST["_open_graph_"] if left empty)
				pending - Whether this is a pending entry, if true returns the array of pending data to store

			Returns:
				An array of data if pending flag is true, otherwise the ID of the open graph table entry
		*/

		public static function handleOpenGraph($table, $id, $data_source = null, $pending = false) {
			if (!$data_source) {
				$data_source = $_POST["_open_graph_"];
			}

			SQL::delete("bigtree_open_graph", ["table" => $table, "entry" => $id]);
			
			if (!empty($_FILES["_open_graph_"]["tmp_name"]["image"])) {
				$og_image = static::processImageUpload([
					"file_input" => [
						"name" => $_FILES["_open_graph_"]["name"]["image"],
						"tmp_name" => $_FILES["_open_graph_"]["tmp_name"]["image"],
						"error" => $_FILES["_open_graph_"]["error"]["image"]
					],
					"title" => "Open Graph Image",
					"settings" => [
						"directory" => "files/open-graph/",
						"min_width" => 1200,
						"min_height" => 630
					]
				]);
			}

			if (!$og_image) {
				$og_image = $data_source["image"];
			}

			if (strpos($og_image, "resource://") === 0) {
				$resource = static::getResourceByFile(substr($og_image, 11));

				if ($resource) {
					$og_image = "irl://".$resource["id"];
				} else {
					$og_image = "";
				}
			}

			$data = [
				"table" => $table,
				"entry" => $id,
				"title" => BigTree::safeEncode($data_source["title"]),
				"description" => BigTree::safeEncode($data_source["description"]),
				"type" => BigTree::safeEncode($data_source["type"]),
				"image" => BigTree::safeEncode($og_image)
			];

			if ($pending) {
				return $data;
			}

			return SQL::insert("bigtree_open_graph", $data);
		}

		/*
			Function: htmlClean
				Removes things that shouldn't be in the <body> of an HTML document from a string.

			Parameters:
				html - A string of HTML

			Returns:
				A clean string of HTML for echoing in <body>
		*/

		public static function htmlClean($html) {
			return str_replace("<br></br>","<br />",strip_tags($html,"<a><abbr><address><area><article><aside><audio><b><base><bdo><blockquote><body><br><button><canvas><caption><cite><code><col><colgroup><command><datalist><dd><del><details><dfn><div><dl><dt><em><emded><fieldset><figcaption><figure><footer><form><h1><h2><h3><h4><h5><h6><header><hgroup><hr><i><iframe><img><input><ins><keygen><kbd><label><legend><li><link><map><mark><menu><meter><nav><noscript><object><ol><optgroup><option><output><p><param><pre><progress><q><rp><rt><ruby><s><samp><script><section><select><small><source><span><strong><style><sub><summary><sup><table><tbody><td><textarea><tfoot><th><thead><time><title><tr><ul><var><video><wbr>"));
		}

		/*
			Function: ignore404
				Ignores a 404 error.
				Checks permissions.

			Parameters:
				id - The id of the reported 404.
		*/

		public function ignore404($id) {
			$this->requireLevel(1);
			$id = sqlescape($id);
			sqlquery("UPDATE bigtree_404s SET ignored = 'on' WHERE id = '$id'");
			$this->track("bigtree_404s",$id,"ignored");
		}

		/*
			Function: initSecurity
				Sets up security environment variables and runs white/blacklists for IP checks.
		*/

		public function initSecurity() {
			global $bigtree;

			$ip = ip2long(BigTree::remoteIP());
			$bigtree["security-policy"] = $p = BigTreeCMS::getSetting("bigtree-internal-security-policy");

			// Check banned IPs list for the user's IP
			if (!empty($p["banned_ips"])) {
				$banned = explode("\n",$p["banned_ips"]);

				foreach ($banned as $address) {
					if (ip2long(trim($address)) == $ip) {
						$bigtree["layout"] = "login";
						$this->stop(file_get_contents(BigTree::path("admin/pages/ip-restriction.php")));
					}
				}
			}

			// Check allowed IP ranges list for user's IP
			if (!empty($p["allowed_ips"])) {
				$allowed = false;
				// Go through the list and see if our IP address is allowed
				$list = explode("\n",$p["allowed_ips"]);

				foreach ($list as $item) {
					list($begin,$end) = explode(",",$item);
					$begin = ip2long(trim($begin));
					$end = ip2long(trim($end));

					if ($begin <= $ip && $end >= $ip) {
						$allowed = true;
					}
				}

				if (!$allowed) {
					$bigtree["layout"] = "login";
					$this->stop(file_get_contents(BigTree::path("admin/pages/ip-restriction.php")));
				}
			}
		}

		/*
			Function: installExtension
				Installs an extension.

			Parameters:
				manifest - Manifest array
				upgrade - Old manifest array (if doing an upgrade, otherwise leave false)

			Returns:
				Modified manifest array.
		*/

		public function installExtension($manifest,$upgrade = false) {
			$bigtree["group_match"] = $bigtree["module_match"] = $bigtree["class_name_match"] = $bigtree["form_id_match"] = $bigtree["view_id_match"] = $bigtree["report_id_match"] = array();
			$extension = sqlescape($manifest["id"]);

			// Turn off foreign key checks so we can reference the extension before creating it
			sqlquery("SET foreign_key_checks = 0");

			// Upgrades drop existing modules, templates, etc -- we don't drop settings because they have user data
			if (is_array($upgrade)) {
				$modules = BigTreeJSONDB::getAll("modules");

				foreach ($modules as $item) {
					if ($item["extension"] == $extension) {
						BigTreeJSONDB::delete("modules", $item["id"]);
					}
				}

				$module_groups = BigTreeJSONDB::getAll("module-groups");

				foreach ($module_groups as $group) {
					if ($group["extension"] == $extension) {
						BigTreeJSONDB::delete("module-groups", $group["id"]);
					}
				}

				$templates = BigTreeJSONDB::getAll("templates");

				foreach ($templates as $item) {
					if ($item["extension"] == $extension) {
						BigTreeJSONDB::delete("templates", $item["id"]);
					}
				}

				$callouts = BigTreeJSONDB::getAll("callouts");

				foreach ($callouts as $item) {
					if ($item["extension"] == $extension) {
						BigTreeJSONDB::delete("callouts", $item["id"]);
					}
				}

				$field_types = BigTreeJSONDB::getAll("field-types");

				foreach ($field_types as $item) {
					if ($item["extension"] == $extension) {
						BigTreeJSONDB::delete("field-types", $item["id"]);
					}
				}

				$feeds = BigTreeJSONDB::getAll("feeds");

				foreach ($feeds as $item) {
					if ($item["extension"] == $extension) {
						BigTreeJSONDB::delete("feeds", $item["id"]);
					}
				}
			// Import tables for new installs
			} else {
				foreach ($manifest["components"]["tables"] as $table_name => $sql_statement) {
					sqlquery("DROP TABLE IF EXISTS `$table_name`");
					sqlquery($sql_statement);
				}
			}

			// Import module groups
			foreach ($manifest["components"]["module_groups"] as &$group) {
				if ($group) {
					$bigtree["group_match"][$group["id"]] = $this->createModuleGroup($group["name"]);
					
					// Update the group ID since we're going to save this manifest locally for uninstalling
					$group["id"] = $bigtree["group_match"][$group["id"]];
					BigTreeJSONDB::update("module-groups", $group["id"], ["extension" => $extension]);
				}
			}

			// Import modules
			foreach ($manifest["components"]["modules"] as &$module) {
				if ($module) {
					$module_id = BigTreeJSONDB::insert("modules", [
						"name" => $module["name"],
						"route" => $module["route"],
						"class" => $module["class"],
						"icon" => $module["icon"],
						"group" => $module["group"] && isset($bigtree["group_match"][$module["group"]]) ? $bigtree["group_match"][$module["group"]] : null,
						"gbp" => $module["gbp"],
						"extension" => $extension
					]);
					
					$bigtree["module_match"][$module["id"]] = $module_id;

					// Update the module ID since we're going to save this manifest locally for uninstalling
					$module["id"] = $module_id;

					// Create the embed forms
					foreach ($module["embed_forms"] as $form) {
						$this->createModuleEmbedForm($module_id,$form["title"],$form["table"],(is_array($form["fields"]) ? $form["fields"] : json_decode($form["fields"],true)),$form["hooks"],$form["default_position"],$form["default_pending"],$form["css"],$form["redirect_url"],$form["thank_you_message"]);
					}

					// Create views
					foreach ($module["views"] as $view) {
						$settings = $view["settings"] ?: $view["options"];
						$bigtree["view_id_match"][$view["id"]] = $this->createModuleView($module_id,$view["title"],$view["description"],$view["table"],$view["type"],(is_array($settings) ? $settings : json_decode($settings,true)),(is_array($view["fields"]) ? $view["fields"] : json_decode($view["fields"],true)),(is_array($view["actions"]) ? $view["actions"] : json_decode($view["actions"],true)),$view["suffix"],$view["preview_url"]);
					}

					// Create regular forms
					foreach ($module["forms"] as $form) {
						$bigtree["form_id_match"][$form["id"]] = $this->createModuleForm($module_id,$form["title"],$form["table"],(is_array($form["fields"]) ? $form["fields"] : json_decode($form["fields"],true)),$form["hooks"],$form["default_position"],($form["return_view"] ? $bigtree["view_id_match"][$form["return_view"]] : false),$form["return_url"],$form["tagging"]);
					}

					// Create reports
					foreach ($module["reports"] as $report) {
						$bigtree["report_id_match"][$report["id"]] = $this->createModuleReport($module_id,$report["title"],$report["table"],$report["type"],(is_array($report["filters"]) ? $report["filters"] : json_decode($report["filters"],true)),(is_array($report["fields"]) ? $report["fields"] : json_decode($report["fields"],true)),$report["parser"],($report["view"] ? $bigtree["view_id_match"][$report["view"]] : false));
					}

					// Create actions
					foreach ($module["actions"] as $action) {
						$this->createModuleAction($module_id,$action["name"],$action["route"],$action["in_nav"],$action["class"],$bigtree["form_id_match"][$action["form"]],$bigtree["view_id_match"][$action["view"]],$bigtree["report_id_match"][$action["report"]],$action["level"],$action["position"]);
					}

					// Update related form state for views
					foreach ($module["views"] as $view) {
						if ($view["related_form"]) {
							$context = BigTreeJSONDB::getSubset("modules", $module_id);
							$context->update("views", $bigtree["view_id_match"][$view["id"]], [
								"related_form" => $bigtree["form_id_match"][$view["related_form"]]
							]);
						}
					}
				}
			}

			// Import templates
			foreach ($manifest["components"]["templates"] as $template) {
				if ($template) {
					BigTreeJSONDB::insert("templates", [
						"id" => $template["id"],
						"name" => $template["name"],
						"module" => $bigtree["module_match"][$template["module"]],
						"resources" => $template["resources"],
						"level" => $template["level"],
						"routed" => $template["routed"],
						"extension" => $extension
					]);
				}
			}

			// Import callouts
			foreach ($manifest["components"]["callouts"] as $callout) {
				if ($callout) {
					BigTreeJSONDB::insert("callouts", [
						"id" => $callout["id"],
						"name" => $callout["name"],
						"description" => $callout["description"],
						"display_default" => $callout["display_default"],
						"display_field" => $callout["display_field"],
						"resources" => $callout["resources"],
						"level" => $callout["level"],
						"position" => $callout["position"],
						"extension" => $extension
					]);
				}
			}

			// Import Settings
			foreach ($manifest["components"]["settings"] as $setting) {
				if ($setting) {
					$setting["extension"] = $extension;
					$this->createSetting($setting);
				}
			}

			// Import Feeds
			foreach ($manifest["components"]["feeds"] as &$feed) {
				if ($feed) {
					$settings = $feed["settings"] ?: $feed["options"];
					$feed["id"] = BigTreeJSONDB::insert("feeds", [
						"route" => $feed["route"],
						"name" => $feed["name"],
						"description" => $feed["description"],
						"type" => $feed["type"],
						"table" => $feed["table"],
						"fields" => $feed["fields"],
						"settings" => $settings,
						"extension" => $extension
					]);
				}
			}

			// Import Field Types
			foreach ($manifest["components"]["field_types"] as $type) {
				if ($type) {
					BigTreeJSONDB::insert("field-types", [
						"id" => $type["id"],
						"name" => $type["name"],
						"use_cases" => $type["use_cases"],
						"self_draw" => $type["self_draw"],
						"extension" => $extension
					]);
				}
			}

			// Upgrades don't drop tables, we run the SQL revisions instead
			if (is_array($upgrade)) {
				$old_revision = $upgrade["revision"];
				$sql_revisions = $manifest["sql_revisions"];

				// Go through all the SQL updates, we ksort first to ensure if the manifest somehow got out of order that we run the SQL update sequentially
				ksort($sql_revisions);

				foreach ($sql_revisions as $key => $statements) {
					if ($key > $old_revision) {
						foreach ($statements as $sql_statement) {
							sqlquery($sql_statement);
						}
					}
				}

				// Update the extension
				BigTreeJSONDB::update("extensions", $manifest["id"], [
					"name" => $manifest["title"],
					"version" => $manifest["version"],
					"last_updated" => date("Y-m-d H:i:s"),
					"manifest" => $manifest
				]);

			// Straight installs move files into place locally
			} else {
				// Make sure destination doesn't exist
				$destination_path = SERVER_ROOT."extensions/".$manifest["id"]."/";
				BigTree::deleteDirectory($destination_path);

				// Move the package to the extension directory
				rename(SERVER_ROOT."cache/package/",$destination_path);
				BigTree::setDirectoryPermissions($destination_path);

				// Create the extension
				BigTreeJSONDB::insert("extensions", [
					"id" => $manifest["id"],
					"name" => $manifest["title"],
					"version" => $manifest["version"],
					"last_updated" => date("Y-m-d H:i:s"),
					"manifest" => $manifest
				]);
			}

			// Re-enable foreign key checks
			SQL::query("SET foreign_key_checks = 1");

			// Empty view cache
			SQL::query("DELETE FROM bigtree_module_view_cache");

			// Move public files into the site directory
			$public_dir = SERVER_ROOT."extensions/".$manifest["id"]."/public/";
			$site_contents = file_exists($public_dir) ? BigTree::directoryContents($public_dir) : array();
			
			foreach ($site_contents as $file_path) {
				$destination_path = str_replace($public_dir,SITE_ROOT."extensions/".$manifest["id"]."/",$file_path);
				BigTree::copyFile($file_path,$destination_path);
			}

			// Clear module class cache and field type cache.
			@unlink(SERVER_ROOT."cache/bigtree-module-class-list.json");
			@unlink(SERVER_ROOT."cache/bigtree-form-field-types.json");

			$this->cacheHooks();

			return $manifest;
		}

		/*
			Function: iplExists
				Determines whether an internal page link still exists or not.

			Parameters:
				ipl - An internal page link

			Returns:
				True if it is still a valid link, otherwise false.
		*/

		public static function iplExists($ipl) {
			$ipl = explode("//",$ipl);

			// See if the page it references still exists.
			$nav_id = $ipl[1];

			if (!sqlrows(sqlquery("SELECT id FROM bigtree_pages WHERE id = '$nav_id'"))) {
				return false;
			}

			// Decode the commands attached to the page
			$commands = json_decode(base64_decode($ipl[2]),true);
			
			// If there are no commands, we're good.
			if (empty($commands[0])) {
				return true;
			}
			
			// If it's a hash tag link, we're also good.
			if (substr($commands[0],0,1) == "#") {
				return true;
			}
			
			// Get template for the navigation id to see if it's a routed template
			$template_id = SQL::fetchSingle("SELECT template FROM bigtree_pages WHERE id = ?", $nav_id);
			
			// If we're a routed template, we're good.
			if ($template_id) {
				$template = BigTreeJSONDB::get("templates", $template_id);

				if (!empty($template["routed"])) {
					return true;
				}
			}

			// We may have been on a page, but there's extra routes that don't go anywhere or do anything so it's a 404.
			return false;
		}

		/*
			Function: irlExists
				Determines whether an internal resource link still exists or not.

			Parameters:
				irl - An internal resource link

			Returns:
				True if it is still a valid link, otherwise false.
		*/

		public static function irlExists($irl) {
			$irl = explode("//",$irl);
			$resource = static::getResource($irl[1]);
			if ($resource) {
				return true;
			}
			return false;
		}

		/*
			Function: isIPBanned
				Checks to see if the requesting IP address is banned and should not be allowed to attempt login.

			Returns:
				true if the IP is banned
		*/

		public static function isIPBanned($ip) {
			global $bigtree;

			// Check to see if this IP is already banned from logging in.
			$ban = sqlfetch(sqlquery("SELECT * FROM bigtree_login_bans WHERE expires > NOW() AND ip = '$ip'"));

			if ($ban) {
				$bigtree["ban_expiration"] = date("F j, Y @ g:ia",strtotime($ban["expires"]));
				$bigtree["ban_is_user"] = false;

				return true;
			}

			return false;
		}

		/*
			Function: isUserBanned
				Checks to see if the logging in user is banned and should not be allowed to attempt login.

			Parameters:
				user - A user ID

			Returns:
				true if the user is banned
		*/

		public static function isUserBanned($user) {
			global $bigtree;

			// See if this user is banned due to failed login attempts
			$ban = sqlfetch(sqlquery("SELECT * FROM bigtree_login_bans WHERE expires > NOW() AND `user` = '".intval($user)."'"));

			if ($ban) {
				$bigtree["ban_expiration"] = date("F j, Y @ g:ia",strtotime($ban["expires"]));
				$bigtree["ban_is_user"] = true;

				return true;
			}

			return false;
		}

		/*
			Function: lockCheck
				Checks if a lock exists.
				If a lock exists and it's currently active, stops page execution and shows the lock page.
				If a lock is yours, refreshes the lock.
				If there is no lock, creates one for you.

			Parameters:
				table - The table to check.
				id - The id of the entry to check.
				include - The lock page to include (relative to /core/ or /custom/)
				force - Whether to force through the lock or not.
				in_admin - Whether to call stop()

			Returns:
				Your lock id.
		*/

		public function lockCheck($table,$id,$include,$force = false,$in_admin = true) {
			global $admin,$bigtree,$cms;
			$table = sqlescape($table);
			$id = sqlescape($id);

			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_locks WHERE `table` = '$table' AND item_id = '$id'"));
			if ($f && $f["user"] != $this->ID && strtotime($f["last_accessed"]) > (time()-300) && !$force) {
				$locked_by = static::getUser($f["user"]);
				$last_accessed = $f["last_accessed"];
				include BigTree::path($include);
				if ($in_admin) {
					$this->stop();
				}
				return false;
			}

			if ($f) {
				sqlquery("UPDATE bigtree_locks SET last_accessed = NOW(), user = '".$this->ID."' WHERE id = '".$f["id"]."'");
				return $f["id"];
			} else {
				sqlquery("INSERT INTO bigtree_locks (`table`,`item_id`,`user`,`title`) VALUES ('$table','$id','".$this->ID."','Page')");
				return sqlid();
			}
		}

		/*
			Function: login
				Attempts to login a user to the CMS.

			Parameters:
				email - The email address of the user.
				password - The password of the user.
				stay_logged_in - Whether to set a cookie to keep the user logged in.
				domain - A secondary domain to set login cookies for (used for multi-site).
				two_factor_token - A token for a login that is already in progress with 2FA.

			Returns:
				false if login failed, otherwise redirects back to the page the person requested.
		*/

		public static function login($email,$password,$stay_logged_in = false,$domain = null,$two_factor_token = null) {
			global $bigtree;

			$ip = ip2long(BigTree::remoteIP());

			if ($two_factor_token) {
				$user = SQL::fetch("SELECT * FROM bigtree_users WHERE 2fa_login_token = ?", $two_factor_token);

				if ($user) {
					$ok = true;
					SQL::update("bigtree_users", $user["id"], ["2fa_login_token" => ""]);
				} else {
					$ok = false;
				}
			} else {
				if (static::isIPBanned($ip)) {
					return false;
				}

				// Get rid of whitespace and make the email lowercase for consistency
				$email = trim(strtolower($email));
				$password = trim($password);
				$user = SQL::fetch("SELECT * FROM bigtree_users WHERE LOWER(email) = ?", $email);
				$ok = false;

				if ($user) {
					if (static::isUserBanned($user["id"])) {
						return false;
					}

					// BigTree 4.3+ switch to password_hash
					if ($user["new_hash"]) {
						$ok = password_verify($password, $user["password"]);

						// New algorithm
						if ($ok && password_needs_rehash($user["password"], PASSWORD_DEFAULT)) {
							SQL::update("bigtree_users", $user["id"], ["password" => password_hash($password, PASSWORD_DEFAULT)]);
						}
					} else {
						$phpass = new PasswordHash($bigtree["config"]["password_depth"], true);
						$ok = $phpass->CheckPassword($password, $user["password"]);

						// Switch to password_hash
						if ($ok) {
							SQL::update("bigtree_users", $user["id"], [
								"password" => password_hash($password, PASSWORD_DEFAULT),
								"new_hash" => "on"
							]);
						}
					}
				}
			}

			if ($ok) {
				// Generate a random CSRF token
				$csrf_token = base64_encode(openssl_random_pseudo_bytes(32));
				$csrf_token_field = "__csrf_token_".BigTree::randomString(32)."__";

				// Generate a random chain id
				$chain = uniqid("chain-",true);

				while (sqlrows(sqlquery("SELECT id FROM bigtree_user_sessions WHERE chain = '".sqlescape($chain)."'"))) {
					$chain = uniqid("chain-",true);
				}

				// Generate a random session id
				$session = uniqid("session-",true);

				while (sqlrows(sqlquery("SELECT id FROM bigtree_user_sessions WHERE id = '".sqlescape($session)."'"))) {
					$session = uniqid("session-",true);
				}

				// Create the new session chain
				sqlquery("INSERT INTO bigtree_user_sessions (`id`,`chain`,`email`,`csrf_token`,`csrf_token_field`) VALUES ('".sqlescape($session)."','".sqlescape($chain)."','".sqlescape($user["email"])."','$csrf_token','$csrf_token_field')");

				if (is_array($bigtree["config"]["sites"]) && count($bigtree["config"]["sites"])) {
					// Create another unique cache session for logins across domains
					$cache_data = array(
						"user_id" => $user["id"],
						"session" => $session,
						"chain" => $chain,
						"stay_logged_in" => $stay_logged_in,
						"login_redirect" => isset($_SESSION["bigtree_login_redirect"]) ? $_SESSION["bigtree_login_redirect"] : false,
						"remaining_sites" => array(),
						"csrf_token" => $csrf_token,
						"csrf_token_field" => $csrf_token_field
					);

					// If we have less than 4 other sites, browsers aren't going to freak out with the redirects
					$all_ssl = true;

					foreach ($bigtree["config"]["sites"] as $site_key => $site_configuration) {
						$cache_data["remaining_sites"][$site_key] = $site_configuration["www_root"];

						if (strpos($site_configuration["www_root"], "https://") !== 0) {
							$all_ssl = false;
						}
					}
			
					$cache_session_key = BigTreeCMS::cacheUnique("org.bigtreecms.login-session", $cache_data);

					// Start the login chain
					if (strpos(ADMIN_ROOT, "https://") === 0 && !$all_ssl) {
						BigTree::redirect(str_replace("https://", "http://", ADMIN_ROOT)."login/cors/?key=".$cache_session_key);
					} else {
						BigTree::redirect(ADMIN_ROOT."login/cors/?key=".$cache_session_key);
					}
				} else {
					$cookie_domain = str_replace(DOMAIN,"",WWW_ROOT);
					$cookie_value = json_encode(array($session, $chain));

					// We still set the email for BigTree bar usage even if they're not being "remembered"
					setcookie('bigtree_admin[email]', $user["email"], strtotime("+1 month"), $cookie_domain, "", false, true);

					if ($stay_logged_in) {
						setcookie('bigtree_admin[login]', $cookie_value, strtotime("+1 month"), $cookie_domain, "", false, true);
					}

					// Regenerate session ID on user state change
					$old_session_id = session_id();
					session_regenerate_id();
					
					if (!empty($bigtree["config"]["session_handler"]) && $bigtree["config"]["session_handler"] == "db") {
						SQL::update("bigtree_sessions", $old_session_id, [
							"id" => session_id(), 
							"is_login" => "on", 
							"logged_in_user" => $user["id"]
						]);
					}

					$_SESSION["bigtree_admin"]["id"] = $user["id"];
					$_SESSION["bigtree_admin"]["email"] = $user["email"];
					$_SESSION["bigtree_admin"]["level"] = $user["level"];
					$_SESSION["bigtree_admin"]["name"] = $user["name"];
					$_SESSION["bigtree_admin"]["permissions"] = json_decode($user["permissions"],true);
					$_SESSION["bigtree_admin"]["csrf_token"] = $csrf_token;
					$_SESSION["bigtree_admin"]["csrf_token_field"] = $csrf_token_field;

					if (isset($_SESSION["bigtree_login_redirect"])) {
						BigTree::redirect($_SESSION["bigtree_login_redirect"]);
					} else {
						BigTree::redirect(ADMIN_ROOT);
					}
				}
			} else {
				// Log it as a failed attempt for a user if the email address matched
				if ($user) {
					$user_id = "'".$user["id"]."'";
				} else {
					$user_id = "NULL";
				}

				sqlquery("INSERT INTO bigtree_login_attempts (`ip`,`user`) VALUES ('$ip', $user_id)");

				// See if this attempt earns the user a ban - first verify the policy is completely filled out (3 parts)
				if ($user["id"] && count(array_filter((array)$bigtree["security-policy"]["user_fails"])) == 3) {
					$p = $bigtree["security-policy"]["user_fails"];
					$r = sqlrows(sqlquery("SELECT * FROM bigtree_login_attempts WHERE `user` = $user_id AND `timestamp` >= DATE_SUB(NOW(),INTERVAL ".$p["time"]." MINUTE)"));

					// Earned a ban
					if ($r >= $p["count"]) {
						// See if they have an existing ban that hasn't expired, if so, extend it
						$existing = sqlfetch(sqlquery("SELECT * FROM bigtree_login_bans WHERE `user` = $user_id AND expires >= NOW()"));

						if ($existing) {
							sqlquery("UPDATE bigtree_login_bans SET expires = DATE_ADD(NOW(),INTERVAL ".$p["ban"]." MINUTE) WHERE id = '".$existing["id"]."'");
						} else {
							sqlquery("INSERT INTO bigtree_login_bans (`ip`,`user`,`expires`) VALUES ('$ip', $user_id, DATE_ADD(NOW(), INTERVAL ".$p["ban"]." MINUTE))");
						}

						$bigtree["ban_expiration"] = date("F j, Y @ g:ia",strtotime("+".$p["ban"]." minutes"));
						$bigtree["ban_is_user"] = true;
					}
				}

				// See if this attempt earns the IP as a whole a ban - first verify the policy is completely filled out (3 parts)
				if (count(array_filter((array) $bigtree["security-policy"]["ip_fails"])) == 3) {
					$p = $bigtree["security-policy"]["ip_fails"];
					$r = sqlrows(sqlquery("SELECT * FROM bigtree_login_attempts WHERE `ip` = '$ip' AND `timestamp` >= DATE_SUB(NOW(),INTERVAL ".$p["time"]." MINUTE)"));

					// Earned a ban
					if ($r >= $p["count"]) {
						$existing = sqlfetch(sqlquery("SELECT * FROM bigtree_login_bans WHERE `ip` = '$ip' AND expires >= NOW()"));

						if ($existing) {
							sqlquery("UPDATE bigtree_login_bans SET expires = DATE_ADD(NOW(),INTERVAL ".$p["ban"]." HOUR) WHERE id = '".$existing["id"]."'");
						} else {
							sqlquery("INSERT INTO bigtree_login_bans (`ip`,`expires`) VALUES ('$ip',DATE_ADD(NOW(),INTERVAL ".$p["ban"]." HOUR))");
						}

						$bigtree["ban_expiration"] = date("F j, Y @ g:ia", strtotime("+".$p["ban"]." hours"));
						$bigtree["ban_is_user"] = false;
					}
				}

				return false;
			}

			return true;
		}

		/*
			Function: login2FA
				Verifies an authorization token matches the user's secret and logs them in if successful.

			Parameters:
				token - A time based token
				bypass - For first time setup, bypass code check

			Returns:
				true if login fails, otherwise redirects
		*/

		public function login2FA($token, $bypass = false) {
			include BigTree::path("inc/lib/GoogleAuthenticator.php");

			// Grab the user's secret first
			$user = sqlfetch(sqlquery("SELECT 2fa_secret FROM bigtree_users WHERE id = '".$_SESSION["bigtree_admin"]["2fa_id"]."'"));
			$success = GoogleAuthenticator::verifyCode($user["2fa_secret"], $token);

			if ($success || $bypass) {
				$this->login(null, null, $_SESSION["bigtree_admin"]["2fa_stay_logged_in"], $_SESSION["bigtree_admin"]["2fa_domain"], $_SESSION["bigtree_admin"]["2fa_login_token"]);

				return false;
			}

			return true;
		}

		public static function loginSession($session_key) {
			global $bigtree;

			BigTreeSessionHandler::start();
			$cache_data = BigTreeCMS::cacheGet("org.bigtreecms.login-session", $session_key);
			
			if (empty($cache_data)) {
				die();
			}

			$admin_parts = parse_url(ADMIN_ROOT);

			if (isset($_GET["no_ssl"])) {
				$admin_parts["scheme"] = "http";
			}

			// Allow setting cookies and sessions
			header("Access-Control-Allow-Origin: ".$admin_parts["scheme"]."://".$admin_parts["host"]);
			header("Access-Control-Allow-Credentials: true");
			session_start(array("gc_maxlifetime" => 24 * 60 * 60));

			$user = sqlfetch(sqlquery("SELECT * FROM bigtree_users WHERE id = '".$cache_data["user_id"]."'"));

			foreach ($cache_data["remaining_sites"] as $site_key => $www_root) {
				if ($site_key == BIGTREE_SITE_KEY) {
					$cookie_domain = str_replace(DOMAIN, "", WWW_ROOT);
					$cookie_value = json_encode(array($cache_data["session"], $cache_data["chain"]));

					// We still set the email for BigTree bar usage even if they're not being "remembered"
					setcookie('bigtree_admin[email]', $user["email"], strtotime("+1 month"), $cookie_domain, "", false, true);

					if ($cache_data["stay_logged_in"]) {
						setcookie('bigtree_admin[login]', $cookie_value, strtotime("+1 month"), $cookie_domain, "", false, true);
					}

					// Regenerate session ID on user state change
					$old_session_id = session_id();
					session_regenerate_id();
					
					if (!empty($bigtree["config"]["session_handler"]) && $bigtree["config"]["session_handler"] == "db") {
						SQL::update("bigtree_sessions", $old_session_id, [
							"id" => session_id(), 
							"is_login" => "on", 
							"logged_in_user" => $user["id"]
						]);
					}

					$_SESSION["bigtree_admin"]["id"] = $user["id"];
					$_SESSION["bigtree_admin"]["email"] = $user["email"];
					$_SESSION["bigtree_admin"]["level"] = $user["level"];
					$_SESSION["bigtree_admin"]["name"] = $user["name"];
					$_SESSION["bigtree_admin"]["permissions"] = json_decode($user["permissions"], true);
					$_SESSION["bigtree_admin"]["csrf_token"] = $cache_data["csrf_token"];
					$_SESSION["bigtree_admin"]["csrf_token_field"] = $cache_data["csrf_token_field"];
				}
			}
		}

		/*
			Function: logout
				Logs out of the CMS.
				Destroys the user's session and unsets the login cookies, then sends the user back to the login page.
		*/

		public static function logout() {
			global $bigtree;

			// If the user asked to be remembered, drop their chain from the legit sessions and remove cookies
			if (!empty($_COOKIE["bigtree_admin"]["login"])) {
				list($session,$chain) = json_decode($_COOKIE["bigtree_admin"]["login"], true);

				// Make sure this session/chain is legit before removing everything with the given chain
				$chain = sqlescape($chain);
				$session = sqlescape($session);

				if (sqlrows(sqlquery("SELECT * FROM bigtree_user_sessions WHERE id = '$session' AND chain = '$chain'"))) {
					sqlquery("DELETE FROM bigtree_user_sessions WHERE chain = '$chain'");
				}

				setcookie("bigtree_admin[email]","",time()-3600,str_replace(DOMAIN,"",WWW_ROOT));
				setcookie("bigtree_admin[login]","",time()-3600,str_replace(DOMAIN,"",WWW_ROOT));
			}

			// Determine whether we should log out all instances of this user
			if (!empty($bigtree["config"]["session_handler"]) && $bigtree["config"]["session_handler"] == "db") {
				$security_policy = BigTreeCMS::getSetting("bigtree-internal-security-policy");

				if (!empty($security_policy["logout_all"])) {
					SQL::delete("bigtree_sessions", ["logged_in_user" => $_SESSION["bigtree_admin"]["id"]]);
					SQL::delete("bigtree_user_sessions", ["email" => $_SESSION["bigtree_admin"]["email"]]);
				}
			}

			unset($_COOKIE["bigtree_admin"]);
			unset($_SESSION["bigtree_admin"]);

			BigTree::redirect(ADMIN_ROOT);
		}

		/*
			Function: logoutAll
				Logs all users out of the CMS.
				Requires the "db" state for sessions.
		*/

		public function logoutAll() {
			$this->requireLevel(2);

			SQL::query("DELETE FROM bigtree_sessions");
			SQL::query("DELETE FROM bigtree_user_sessions");
		}

		/*
			Function: makeIPL
				Creates an internal page link out of a URL.

			Parameters:
				url - A URL

			Returns:
				An internal page link (if possible) or just the same URL (if it's not internal).
		*/

		public static function makeIPL($url) {
			global $bigtree;

			$path_components = explode("/", rtrim(str_replace(WWW_ROOT, "", $url), "/"));

			// See if this is a file
			$local_path = str_replace(WWW_ROOT,SITE_ROOT,$url);

			if (($path_components[0] != "files" || $path_components[1] != "resources") &&
				(substr($local_path,0,1) == "/" || substr($local_path,0,2) == "\\\\") &&
				file_exists($local_path)) {

				return BigTreeCMS::replaceHardRoots($url);
			}

			// If we have multiple sites, try each domain
			if (is_array($bigtree["config"]["sites"]) && count($bigtree["config"]["sites"]) > 1) {
				foreach ($bigtree["config"]["sites"] as $site_key => $configuration) {
					// This is the site we're pointing to
					if (strpos($url, $configuration["www_root"]) !== false) {
						$path_components = explode("/", rtrim(str_replace($configuration["www_root"], "", $url), "/"));

						// Check for resource link
						if ($path_components[0] == "files" && $path_components[1] == "resources") {
							$resource = static::getResourceByFile($url);

							if ($resource) {
								static::$IRLsCreated[] = $resource["id"];

								return "irl://".$resource["id"]."//".$resource["prefix"];
							}
						}

						// Get the root path of the site for calculating an IPL and add it to the path components
						$f = sqlfetch(sqlquery("SELECT path FROM bigtree_pages WHERE id = '".$configuration["trunk"]."'"));
						$path_components = array_filter(array_merge(explode("/", $f["path"]), $path_components));

						// Check for page link
						list($navid, $commands, $routed_state, $get_vars, $hash) = static::getPageIDForPath($path_components);

						if ($navid) {
							return "ipl://".$navid."//".base64_encode(json_encode($commands))."//".base64_encode($get_vars)."//".base64_encode($hash);
						} else {
							return BigTreeCMS::replaceHardRoots($url);
						}
					}
				}

				return BigTreeCMS::replaceHardRoots($url);
			} else {
				// Check for resource link
				if ($path_components[0] == "files" && $path_components[1] == "resources") {
					$resource = static::getResourceByFile($url);
					if ($resource) {
						static::$IRLsCreated[] = $resource["id"];

						return "irl://".$resource["id"]."//".$resource["prefix"];
					}
				}

				// Check for page link
				list($navid, $commands, $routed_state, $get_vars, $hash) = static::getPageIDForPath($path_components);
			}

			if (!$navid) {
				return BigTreeCMS::replaceHardRoots($url);
			}

			return "ipl://".$navid."//".base64_encode(json_encode($commands))."//".base64_encode($get_vars)."//".base64_encode($hash);
		}

		/*
			Function: markMessageRead
				Marks a message as read by the currently logged in user.

			Parameters:
				id - The message id.
		*/

		public function markMessageRead($id) {
			$message = $this->getMessage($id);
			if (!$message) {
				return false;
			}
			$read_by = str_replace("|".$this->ID."|","",$message["read_by"])."|".$this->ID."|";
			sqlquery("UPDATE bigtree_messages SET read_by = '".sqlescape($read_by)."' WHERE id = '".$message["id"]."'");
			return true;
		}

		/*
			Function: mergeTags
				Merges a set of tags into a single tag and updates references.

			Parameters:
				tag - A tag ID that will consume the other tags
				merge_tags - An array of tag IDs that will be consumed

			Returns:
				true if successful
		*/

		public function mergeTags($tag, $merge_tags) {
			$tag = intval($tag);
			$target_tag_data = sqlfetch(sqlquery("SELECT `tag` FROM bigtree_tags WHERE id = '$tag'"));

			if (!$target_tag_data) {
				return false;
			}

			foreach ($merge_tags as $tag_id) {
				$tag_id = intval($tag_id);
				$tag_data = sqlfetch(sqlquery("SELECT `tag` FROM bigtree_tags WHERE id = '$tag_id'"));

				sqlquery("UPDATE bigtree_tags_rel SET `tag` = '$tag' WHERE `tag` = '$tag_id'");
				sqlquery("DELETE FROM bigtree_tags WHERE `id` = '$tag_id'");

				$this->track("bigtree_tags", $tag_data["tag"], "merged");
			}

			// Clean up duplicate references
			sqlquery("DELETE tags_a FROM bigtree_tags_rel AS tags_a, bigtree_tags_rel AS tags_b
					  WHERE (tags_a.`table` = tags_b.`table`)
					    AND (tags_a.`entry` = tags_b.`entry`)
					    AND (tags_a.`tag` = tags_b.`tag`)
					    AND (tags_a.`id` < tags_b.`id`)");

			$this->updateTagReferenceCounts(array($tag));

			return true;
		}

		/*
			Function: pageChangeExists
				Returns whether pending changes exist for a given page.

			Parameters:
				page - The page id.

			Returns:
				true or false
		*/

		public static function pageChangeExists($page) {
			$page = sqlescape($page);
			$c = sqlfetch(sqlquery("SELECT id FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = '$page'"));
			if (!$c) {
				return false;
			}
			return true;
		}

		/*
			Function: parse404SourceURL
				Parses a user's 404 source URL based on site key.

			Parameters:
				source - Source URL or URL fragment
				site_key - Optional site key

			Returns:
				An array containing the sanitized source URL for input into bigtree_404s and GET variables from the URL.
		*/

		public static function parse404SourceURL($source, $site_key = null) {
			$source = trim($source);

			// If this is a multi-site environment and a full URL was pasted in we're going to auto-select the key no matter what they passed in
			if (!is_null($site_key)) {
				$from_domain = parse_url($source, PHP_URL_HOST);

				foreach ($bigtree["config"]["sites"] as $index => $site) {
					$domain = parse_url($site["domain"], PHP_URL_HOST);

					if ($domain == $from_domain) {
						$site_key = $index;
						$source = str_replace($site["www_root"], "", $source);
					}
				}
			}

			// Allow for from URLs with GET vars
			$source_parts = parse_url($source);
			$get_vars = "";

			if (!empty($source_parts["query"])) {
				$source = str_replace("?".$source_parts["query"], "", $source);
				$get_vars = sqlescape(htmlspecialchars($source_parts["query"]));
			}

			return [
				"url" => htmlspecialchars(strip_tags(trim(str_replace(WWW_ROOT, "", $source), "/"))),
				"get_vars" => $get_vars
			];
		}

		/*
			Function: pingSearchEngines
				Sends the latest sitemap.xml out to search engine ping services if enabled in settings.
		*/

		public static function pingSearchEngines() {
			$setting = static::getSetting("ping-search-engines");
			if ($setting["value"] == "on") {
				// Google
				file_get_contents("http://www.google.com/webmasters/tools/ping?sitemap=".urlencode(WWW_ROOT."sitemap.xml"));
				// Bing
				file_get_contents("http://www.bing.com/webmaster/ping.aspx?siteMap=".urlencode(WWW_ROOT."sitemap.xml"));
			}
		}

		/*
			Function: processCrop
				Processes a single crop of a crop set.

			Parameters:
				crop_key - A cache key pointing to the location of crop data.
				index - The crop to process
				x - Starting x point of the crop
				y - Starting y point of the crop
				width - Width of the crop
				height - Height of the crop
		*/

		public static function processCrop($crop_key, $index, $x, $y, $width, $height) {
			$storage = new BigTreeStorage;

			$crops = BigTreeCMS::cacheGet("org.bigtreecms.crops", $crop_key);
			$crop = $crops[$index];

			$image_src = $crop["image"];
			$target_width = $crop["width"];
			$target_height = $crop["height"];
			$thumbs = $crop["thumbs"];
			$center_crops = $crop["center_crops"];

			$image = new BigTreeImage($image_src);
			$temp_crop = $image->getTempFileName();
			$image->crop($temp_crop, $x, $y, $target_width, $target_height, $width, $height, $crop["retina"], $crop["grayscale"]);
			$temp_image = new BigTreeImage($temp_crop);

			// Make thumbnails for the crop
			if (is_array($thumbs)) {
				foreach ($thumbs as $thumb) {
					// We're going to figure out what size the thumbs will be so we can re-crop the original image so we don't lose image quality.
					$temp_thumb = $temp_image->getTempFileName();
					$size = $temp_image->getThumbnailSize($thumb["width"], $thumb["height"]);
					$image->crop($temp_thumb, $x, $y, $size["width"], $size["height"], $width, $height, $crop["retina"], $thumb["grayscale"]);
					$storage->replace($temp_thumb, $thumb["prefix"].$crop["name"], $crop["directory"]);
				}
			}

			// Make center crops of the crop
			if (is_array($center_crops)) {
				foreach ($center_crops as $center_crop) {
					$temp_center_crop = $image->getTempFileName();
					$temp_image->centerCrop($temp_center_crop, $center_crop["width"], $center_crop["height"], $crop["retina"], $center_crop["grayscale"]);
					$storage->replace($temp_center_crop, $center_crop["prefix"].$crop["name"], $crop["directory"]);
				}
			}

			// Move crop into its resting place
			$storage->replace($temp_crop, $crop["prefix"].$crop["name"], $crop["directory"]);
		}

		/*
			Function: processCrops
				Processes a list of cropped images.

			Parameters:
				crop_key - A cache key pointing to the location of crop data.
		*/

		public static function processCrops($crop_key) {
			$storage = new BigTreeStorage;

			// Get and remove the crop data
			$crops = BigTreeCMS::cacheGet("org.bigtreecms.crops",$crop_key);
			BigTreeCMS::cacheDelete("org.bigtreecms.crops",$crop_key);

			foreach ($crops as $key => $crop) {
				$image_src = $crop["image"];
				$target_width = $crop["width"];
				$target_height = $crop["height"];
				$x = $_POST["x"][$key];
				$y = $_POST["y"][$key];
				$width = $_POST["width"][$key];
				$height = $_POST["height"][$key];
				$thumbs = $crop["thumbs"];
				$center_crops = $crop["center_crops"];

				$image = new BigTreeImage($image_src);
				$temp_crop = $image->getTempFileName();
				$image->crop($temp_crop, $x, $y, $target_width, $target_height, $width, $height, $crop["retina"], $crop["grayscale"]);
				$temp_image = new BigTreeImage($temp_crop);

				// Make thumbnails for the crop
				if (is_array($thumbs)) {
					foreach ($thumbs as $thumb) {
						// We're going to figure out what size the thumbs will be so we can re-crop the original image so we don't lose image quality.
						$temp_thumb = $temp_image->getTempFileName();
						$size = $temp_image->getThumbnailSize($thumb["width"], $thumb["height"]);
						$image->crop($temp_thumb, $x, $y, $size["width"], $size["height"], $width, $height, $crop["retina"], $thumb["grayscale"]);
						$storage->replace($temp_thumb, $thumb["prefix"].$crop["name"], $crop["directory"]);
					}
				}

				// Make center crops of the crop
				if (is_array($center_crops)) {
					foreach ($center_crops as $center_crop) {
						$temp_center_crop = $image->getTempFileName();
						$temp_image->centerCrop($temp_center_crop, $center_crop["width"], $center_crop["height"], $crop["retina"], $center_crop["grayscale"]);
						$storage->replace($temp_center_crop, $center_crop["prefix"].$crop["name"], $crop["directory"]);
					}
				}

				// Move crop into its resting place
				$storage->replace($temp_crop,$crop["prefix"].$crop["name"],$crop["directory"]);
			}

			// Remove all the temporary images
			foreach ($crops as $crop) {
				@unlink($crop["image"]);
			}
		}

		/*
			Function: processField
				A helper function for field type processing.

			Parameters:
				field - Field information

			Returns:
				Field output.
		*/

		public static function processField($field) {
			global $admin,$bigtree,$cms;

			// Make sure options is an array to prevent warnings, load from options as a fallback for < 4.3
			if (!is_array($field["settings"])) {
				if (is_array($field["options"]) && array_filter($field["options"])) {
					$field["settings"] = $field["options"];
				} else {
					$field["settings"] = [];
				}
			}

			$field["options"] = &$field["settings"];

			// Save current context
			$bigtree["saved_extension_context"] = $bigtree["extension_context"];

			// Check if the field type is stored in an extension
			if (strpos($field["type"],"*") !== false) {
				list($extension,$field_type) = explode("*",$field["type"]);

				$bigtree["extension_context"] = $extension;
				$field_type_path = SERVER_ROOT."extensions/$extension/field-types/$field_type/process.php";
			} else {
				// < 4.3 location - we prefer it to allow old overrides to continue to work
				$field_type_path = BigTree::path("admin/form-field-types/process/".$field["type"].".php");

				if (!file_exists($field_type_path)) {
					$field_type_path = BigTree::path("admin/field-types/".$field["type"]."/process.php");
				}
			}

			// If we have a customized handler for this data type, run it.
			if (file_exists($field_type_path)) {
				include $field_type_path;

				// If it's explicitly ignored return null
				if ($field["ignore"]) {
					return null;
				} else {
					$output = $field["output"];
				}

			// Fall back to default handling
			} else {
				if (is_array($field["input"])) {
					$output = $field["input"];
				} else {
					$output = BigTree::safeEncode($field["input"]);
				}
			}

			// Check validation
			if (!BigTreeAutoModule::validate($output,$field["settings"]["validation"])) {
				$error = $field["settings"]["error_message"] ? $field["settings"]["error_message"] : BigTreeAutoModule::validationErrorMessage($output, $field["settings"]["validation"]);
				$bigtree["errors"][] = array(
					"field" => $field["title"],
					"error" => $error
				);
			}

			// Translation of internal links
			if (is_array($output)) {
				$output = BigTree::translateArray($output);
			} else {
				$output = $admin->autoIPL($output);
			}

			// Restore context
			$bigtree["extension_context"] = $bigtree["saved_extension_context"];

			return $output;
		}

		/*
			Function: processImageUpload
				Processes image upload data for form fields.
				If you're emulating field information, the following keys are of interest in the field array:
				"file_input" - a keyed array that needs at least "name" and "tmp_name" keys that contain the desired name of the file and the source file location, respectively.
				"settings" - a keyed array of options for the field, keys of interest for photo processing are:
					"min_height" - Minimum Height required for the image
					"min_width" - Minimum Width required for the image
					"retina" - Whether to try to create a 2x size image when thumbnailing / cropping (if the source file / crop is large enough)
					"thumbs" - An array of thumbnail arrays, each of which has "prefix", "width", "height", and "grayscale" keys (prefix is prepended to the file name when creating the thumbnail, grayscale will make the thumbnail grayscale)
					"crops" - An array of crop arrays, each of which has "prefix", "width", "height" and "grayscale" keys (prefix is prepended to the file name when creating the crop, grayscale will make the thumbnail grayscale)). Crops can also have their own "thumbs" key that creates thumbnails of each crop (format mirrors that of "thumbs")

			Parameters:
				field - Field information (normally set to $field when running a field type's process file)
				replace - If not looking for a unique filename (e.g. replacing an existing image) pass truthy value
				force_local_replace - If replacing a file, replace a local filepath regardless of default storage (defaults to false)
		*/

		public static function processImageUpload($field, $replace = false, $force_local_replace = false) {
			global $bigtree;

			$failed = false;
			$name = $field["file_input"]["name"];
			$temp_name = $field["file_input"]["tmp_name"];
			$error = $field["file_input"]["error"];

			// If a file upload error occurred, return the old image and set errors
			if ($error == 1 || $error == 2) {
				$bigtree["errors"][] = array("field" => $field["title"], "error" => "The file you uploaded ($name) was too large &mdash; <strong>Max file size: ".ini_get("upload_max_filesize")."</strong>");
				
				return false;
			} elseif ($error == 3) {
				$bigtree["errors"][] = array("field" => $field["title"], "error" => "The file upload failed ($name).");
				
				return false;
			}
			
			// Backwards compatibility with 4.2
			if (empty($field["settings"])) {
				$field["settings"] = $field["options"];
			}

			// See if we're using image presets
			if ($field["settings"]["preset"]) {
				$media_settings = BigTreeJSONDB::get("config", "media-settings");
				$preset = $media_settings["presets"][$field["settings"]["preset"]];
				
				// If the preset still exists, copy its properties over to our options
				if ($preset) {
					foreach ($preset as $key => $val) {
						$field["settings"][$key] = $val;
					}
				}
			}

			// This is a file manager upload, add a 100x100 center crop
			if ($field["settings"]["preset"] == "default") {
				if (!is_array($field["settings"]["center_crops"])) {
					$field["settings"]["center_crops"] = [];
				}

				$field["settings"]["center_crops"][] = [
					"prefix" => "list-preview/",
					"width" => 100,
					"height" => 100
				];
			}
			
			// Load up the image class for doing manipulation / calculation and fix any EXIF rotations
			$image = new BigTreeImage($temp_name, $field["settings"]);
						
			if ($image->Error) {
				$bigtree["errors"][] = ["field" => $field["title"], "error" => $image->Error];
				$image->destroy();
				
				return false;
			}
			
			// For crops that don't meet the required image size, see if a sub-crop will work.
			$image->filterGeneratableCrops();
			
			// Get largest crop and thumbnail to check if we have the memory available to make them
			$largest_thumb = $image->getLargestThumbnail();
			$largest_crop = $image->getLargestCrop();
			
			if (!$image->checkMemory($largest_thumb["width"], $largest_thumb["height"]) ||
				!$image->checkMemory($largest_crop["width"], $largest_crop["height"])
			) {
				$bigtree["errors"][] = ["field" => $field["title"], "error" => "The image uploaded is too large for the server to manipulate. Please upload a smaller version of this image"];
				$image->destroy();
				
				return false;
			}

			// Upload the original to the proper place.
			if ($replace) {
				$field["output"] = $image->replace($name, $force_local_replace);
			} else {
				$field["output"] = $image->store($name);
			}

			// If the upload service didn't return a value, we failed to upload it for one reason or another.
			if (!$field["output"]) {
				$bigtree["errors"][] = ["field" => $field["title"], $image->Error];
				$image->destroy();

				return false;
			}
			
			// Handle crops and thumbnails
			$crops = $image->processCrops();
			$image->processThumbnails();
			$image->processCenterCrops();
		 
			// If we don't have any crops, get rid of the temporary image we made.
			if (!count($crops)) {
				$image->destroy();
			} else {
				if (!is_array($bigtree["crops"])) {
					$bigtree["crops"] = [];
				}
				
				$bigtree["crops"] = array_merge($bigtree["crops"], $crops);
			}
			
			return $field["output"];
		}

		/*
			Function: rectifyResourceTypeChange
				Verifies that existing data for a resource set will fit with the new resource set.

			Parameters:
				data - Data, passed by reference and modified upon return
				new_resources - The resource fields for the data set to conform to
				old_resources - The resource fields the data set originated in

			Returns:
				An array of fields which should force re-crops
		*/

		public function rectifyResourceTypeChange(&$data, $new_resources, $old_resources) {
			$forced_recrops = [];
			$old_resources_keyed = [];

			if (is_array($old_resources)) {
				foreach ($old_resources as $resource) {
					$old_resources_keyed[$resource["id"]] = $resource;
				}
			}

			foreach ($new_resources as $new) {
				$id = $new["id"];

				if (empty($data[$id])) {
					continue;
				}

				if (isset($old_resources_keyed[$id])) {
					$old = $old_resources_keyed[$id];

					if ($old["type"] != $new["type"]) {
						// Not even the same resource type, wipe data
						unset($data[$id]);
					} elseif (($new["type"] == "callouts" || $new["type"] == "matrix" || $new["type"] == "list" || $new["type"] == "one-to-many" || $new["type"] == "photo-gallery") && $new["settings"] != $old["settings"]) {
						// These fields need to match exactly to allow data to move over
						unset($data[$id]);
					} elseif ($new["type"] == "image-reference") {
						// Image references just need to ensure that the existing data meets the new requirements
						$new_min_width = empty($new["settings"]["min_width"]) ? 0 : intval($new["settings"]["min_width"]);
						$new_min_height = empty($new["settings"]["min_height"]) ? 0 : intval($new["settings"]["min_height"]);
						$resource = $this->getResource($data[$id]);

						if (!$resource) {
							unset($data[$id]);
							continue;
						}

						if ($resource["width"] < $new_min_width || $resource["height"] < $new_min_height) {
							unset($data[$id]);
						}
					} elseif ($new["type"] == "text" && $new["settings"]["sub_type"] != $old["settings"]["sub_type"]) {
						// Sub-types changed, the data won't fit anymore
						unset($data[$id]);
					} elseif ($new["type"] == "html" && !empty($new["settings"]["simple"]) && empty($old["settings"]["simple"])) {
						// New HTML is simple
						unset($data[$id]);
					} elseif ($new["type"] == "upload" && !empty($new["settings"]["image"])) {
						if ($new["settings"] == $old["settings"]) {
							continue;
						}

						$new_min_width = empty($new["settings"]["min_width"]) ? 0 : intval($new["settings"]["min_width"]);
						$new_min_height = empty($new["settings"]["min_height"]) ? 0 : intval($new["settings"]["min_height"]);
						list($w, $h) = @getimagesize($data[$id]);

						// Existing base image won't work
						if (empty($w) || empty($h) || $w < $new_min_width || $h < $new_min_height) {
							unset($data[$id]);
							continue;
						}

						$forced_recrops[$id] = true;
					}
				}
			}

			return $forced_recrops;
		}

		/*
			Function: refreshLock
				Refreshes a lock.

			Parameters:
				table - The table for the lock.
				id - The id of the item.
		*/

		public function refreshLock($table,$id) {
			$id = sqlescape($id);
			$table = sqlescape($table);
			sqlquery("UPDATE bigtree_locks SET last_accessed = NOW() WHERE `table` = '$table' AND item_id = '$id' AND user = '".$this->ID."'");
		}

		/*
			Function: remove2FASecret
				Removes two factor authentication from a user.

			Parameters:
				user - A user ID
		*/

		public function remove2FASecret($user) {
			sqlquery("UPDATE bigtree_users SET 2fa_secret = '' WHERE id = '".sqlescape($user)."'");
		}

		/*
			Function: requireAccess
				Checks the logged in user's access to a given module.
				Throws a permission denied page and stops page execution if the user doesn't have access.

			Parameters:
				module - The id of the module to check access to.

			Returns:
				The permission level of the logged in user.
		*/

		public function requireAccess($module) {
			global $admin,$bigtree,$cms;
			if ($this->Level > 0) {
				return "p";
			}
			if (!isset($this->Permissions[$module]) || $this->Permissions[$module] == "") {
				define("BIGTREE_ACCESS_DENIED",true);
				$this->stop(file_get_contents(BigTree::path("admin/pages/_denied.php")));
			}
			return $this->Permissions[$module];
		}

		/*
			Function: requireLevel
				Requires the logged in user to have a certain access level to continue.
				Throws a permission denied page and stops page execution if the user doesn't have access.

			Parameters:
				level - An access level (0 being normal user, 1 being administrator, 2 being developer)
		*/

		public function requireLevel($level) {
			global $admin,$bigtree,$cms;
			if (!isset($this->Level) || $this->Level < $level) {
				define("BIGTREE_ACCESS_DENIED",true);
				$this->stop(file_get_contents(BigTree::path("admin/pages/_denied.php")));
			}
		}

		/*
			Function: requirePublisher
				Checks the logged in user's access to a given module to make sure they are a publisher.
				Throws a permission denied page and stops page execution if the user doesn't have access.

			Parameters:
				module - The id of the module to check access to.
		*/

		public function requirePublisher($module) {
			global $admin,$bigtree,$cms;
			if ($this->Level > 0) {
				return true;
			}
			if ($this->Permissions[$module] != "p") {
				ob_clean();
				include BigTree::path("admin/pages/_denied.php");
				$bigtree["content"] = ob_get_clean();
				include BigTree::path("admin/layouts/default.php");
				die();
			}
			return true;
		}

		/*
			Function: runHooks
				Runs extension hooks of a given type for a given context.

			Parameters:
				type - Hook type
				context - Hook context
				data - Data to modify (will be returned modified)
				data_context - Additional data context (will be global variables in the context of the hook, not returned)

			Returns:
				Data modified by hook script
		*/

		public function runHooks($type, $context = "", $data = "", $data_context = []) {
			if (!file_exists(SERVER_ROOT."cache/bigtree-hooks.json")) {
				$this->cacheHooks();
			}

			if (!isset($this->Hooks)) {
				$this->Hooks = json_decode(file_get_contents(SERVER_ROOT."cache/bigtree-hooks.json"), true);
			}

			// Anonymous function so that hooks can't pollute context
			$run_hook = function($hook, $data, $data_context = []) {
				foreach ($data_context as $key => $value) {
					$$key = $value;
				}

				include SERVER_ROOT.$hook;
				return $data;
			};

			if ($context) {
				if (!empty($this->Hooks[$type][$context]) && is_array($this->Hooks[$type][$context])) {
					foreach ($this->Hooks[$type][$context] as $hook) {
						$data = $run_hook($hook, $data, $data_context);
					}
				} 
			} else {
				if (!empty($this->Hooks[$type]) && is_array($this->Hooks[$type])) {
					foreach ($this->Hooks[$type] as $hook) {
						$data = $run_hook($hook, $data, $data_context);
					}
				} 
			}			

			return $data;
		}

		/*
			Function: saveCurrentPageRevision
				Saves the currently published page as a revision.

			Parameters:
				page - The page id.
				description - The revision description.

			Returns:
				The new revision id.
		*/

		public function saveCurrentPageRevision($page,$description) {
			$access = $this->getPageAccessLevel($page);

			if ($access != "p") {
				$this->stop("You must be a publisher to manage revisions.");
			}

			// Get the current page.
			$current = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $page);

			// Copy it to the saved versions
			$id = SQL::insert("bigtree_page_revisions", [
				"page" => $page,
				"title" => $current["title"],
				"meta_description" => $current["meta_description"],
				"template" => $current["template"],
				"external" => $current["external"],
				"new_window" => $current["new_window"],
				"resources" => $current["resources"],
				"author" => $current["last_edited_by"],
				"updated_at" => $current["updated_at"],
				"saved" => "on",
				"saved_description" => BigTree::safeEncode($description)
			]);

			$this->track("bigtree_page_revisions", $id, "created");

			return $id;
		}

		/*
			Function: search404s
				Searches 404s, returns results.

			Parameters:
				type - The type of results (301, 404, or ignored).
				query - The search query.
				page - The page to return.
				site_key - The site key to return 404s for (leave null for all 404s).

			Returns:
				An array of entries from bigtree_404s.
		*/

		public static function search404s($type, $query = "", $page = 1, $site_key = null) {
			$items = array();

			if ($site_key) {
				$site_key_query = "AND site_key = '".sqlescape($site_key)."'";
			} else {
				$site_key_query = "";
			}

			if ($query) {
				$s = sqlescape(strtolower($query));
				if ($type == "301") {
					$where = "ignored = '' AND (broken_url LIKE '%$s%' OR redirect_url LIKE '%$s%' OR get_vars LIKE '%$s%') AND redirect_url != ''";
				} elseif ($type == "ignored") {
					$where = "ignored != '' AND (broken_url LIKE '%$s%' OR redirect_url LIKE '%$s%' OR get_vars LIKE '%$s%')";
				} else {
					$where = "ignored = '' AND (broken_url LIKE '%$s%' OR get_vars LIKE '%$s%') AND redirect_url = ''";
				}
			} else {
				if ($type == "301") {
					$where = "ignored = '' AND redirect_url != ''";
				} elseif ($type == "ignored") {
					$where = "ignored != ''";
				} else {
					$where = "ignored = '' AND redirect_url = ''";
				}
			}

			// Get the page count
			$f = sqlfetch(sqlquery("SELECT COUNT(id) AS `count` FROM bigtree_404s WHERE $where $site_key_query"));
			$pages = ceil($f["count"] / 20);
			$pages = ($pages < 1) ? 1 : $pages;

			// Get the results
			$q = sqlquery("SELECT * FROM bigtree_404s WHERE $where $site_key_query ORDER BY requests DESC LIMIT ".(($page - 1) * 20).",20");

			while ($f = sqlfetch($q)) {
				$f["redirect_url"] = BigTreeCMS::replaceInternalPageLinks($f["redirect_url"]);
				$items[] = $f;
			}

			return array($pages,$items);
		}

		/*
			Function: searchAuditTrail
				Searches the audit trail for a set of data.

			Parameters:
				user - User to restrict results to (optional)
				table - Table to restrict results to (optional)
				entry - Entry to restrict results to (optional)
				start - Start date/time to restrict results to (optional)
				end - End date/time to restrict results to (optional)

			Returns:
				An array of adds/edits/deletions from the audit trail.
		*/

		public static function searchAuditTrail($user = false,$table = false,$entry = false,$start = false,$end = false) {
			global $admin;

			$users = $items = $where = array();
			$deleted_users = BigTreeCMS::getSetting("bigtree-internal-deleted-users");
			$query = "SELECT * FROM bigtree_audit_trail";

			if ($user) {
				$where[] = "user = '".sqlescape($user)."'";
			}

			if ($table) {
				$where[] = "`table` = '".sqlescape($table)."'";
			}

			if ($entry) {
				$where[] = "entry = '".sqlescape($entry)."'";
			}

			if ($start) {
				if ($admin instanceof BigTreeAdmin && $admin->Timezone) {
					$where[] = "`date` >= '".$admin->convertTimestampFromUser($start, "Y-m-d H:i:s")."'";
				} else {
					$where[] = "`date` >= '".date("Y-m-d H:i:s",strtotime($start))."'";
				}
			}

			if ($end) {
				if ($admin instanceof BigTreeAdmin && $admin->Timezone) {
					$where[] = "`date` >= '".$admin->convertTimestampFromUser($end, "Y-m-d H:i:s")."'";
				} else {
					$where[] = "`date` <= '".date("Y-m-d H:i:s",strtotime($end))."'";
				}
			}

			if (count($where)) {
				$query .= " WHERE ".implode(" AND ",$where);
			}

			$q = sqlquery($query." ORDER BY `date` DESC");

			while ($f = sqlfetch($q)) {
				if (isset($deleted_users[$f["user"]])) {
					$user = $deleted_users[$f["user"]];
					$user["deleted"] = true;
					$user["id"] = $f["user"];
					$f["user"] = $user;
				} else {
					if (!$users[$f["user"]]) {
						$u = static::getUser($f["user"]);
						$users[$f["user"]] = array("id" => $u["id"],"name" => $u["name"],"email" => $u["email"],"level" => $u["level"]);
					}

					$f["user"] = $users[$f["user"]];
				}

				$items[] = $f;
			}

			return $items;
		}

		/*
			Function: searchPages
				Searches for pages.

			Parameters:
				query - Query string to search against.
				fields - Fields to search.
				max - Maximum number of results to return.

			Returns:
				An array of pages.
		*/

		public static function searchPages($query,$fields = array("nav_title"),$max = 10) {
			// Since we're in JSON we have to do stupid things to the /s for URL searches.
			$query = str_replace('/','\\\/',$query);

			$results = array();
			$terms = explode(" ",$query);
			$qpart = array("archived != 'on'");

			foreach ($terms as $term) {
				$term = sqlescape(strtolower($term));
				$or_parts = array();
				foreach ($fields as $field) {
					$or_parts[] = "LOWER(`$field`) LIKE '%$term%'";
				}
				$qpart[] = "(".implode(" OR ",$or_parts).")";
			}

			$q = sqlquery("SELECT * FROM bigtree_pages WHERE ".implode(" AND ",$qpart)." ORDER BY nav_title LIMIT $max");
			while ($f = sqlfetch($q)) {
				$results[] = $f;
			}
			return $results;
		}

		/*
			Function: searchResources
				Returns a list of folders and files that match the given query string.

			Parameters:
				query - A string of text to search folders' and files' names to.
				sort - The column to sort the files on (default: date DESC).

			Returns:
				An array of two arrays - folders and files - with permission levels.
		*/

		public function searchResources($query, $sort = "date DESC") {
			$query = SQL::escape($query);
			$folders = array();
			$resources = array();
			$permission_cache = array();
			$existing = array();

			$q = SQL::query("SELECT * FROM bigtree_resource_folders WHERE name LIKE '%$query%' ORDER BY name");

			while ($folder = $q->fetch()) {
				$folder["permission"] = $permission_cache[$folder["id"]] = $this->getResourceFolderPermission($folder);
				$folders[] = $folder;
			}

			$q = SQL::query("SELECT * FROM bigtree_resources WHERE name LIKE '%$query%' OR metadata LIKE '%$query%' ORDER BY $sort");

			while ($resource = $q->fetch()) {
				$check = array($resource["name"], $resource["md5"]);

				if (!in_array($check, $existing)) {
					// If we've already got the permission cached, use it. Otherwise, fetch it and cache it.
					if ($permission_cache[$resource["folder"]]) {
						$resource["permission"] = $permission_cache[$resource["folder"]];
					} else {
						$resource["permission"] = $permission_cache[$resource["folder"]] = $this->getResourceFolderPermission($resource["folder"]);
					}

					$resources[] = $resource;
					$existing[] = $check;
				}
			}

			return array("folders" => $folders, "resources" => $resources);
		}

		/*
			Function: searchTags
				Finds existing tags that are similar.

			Parameters:
				tag - A tag to find similar tags for.
				full_row - Set to true to return a whole tag row rather than just the name (defaults to false)

			Returns:
				An array of up to 8 similar tags.
		*/

		public static function searchTags($tag, $full_row = false) {
			$tags = $dist = array();
			$meta = metaphone($tag);
			$q = sqlquery("SELECT * FROM bigtree_tags");
			
			while ($f = sqlfetch($q)) {
				$distance = levenshtein($f["metaphone"],$meta);
				
				if ($distance < 2) {
					if ($full_row) {
						$tags[] = $f;
					} else {
						$tags[] = $f["tag"];
					}

					$dist[] = $distance;
				}
			}

			array_multisort($dist,SORT_ASC,$tags);

			return array_slice($tags, 0, 8);
		}

		/*
			Function: set404Redirect
				Sets the redirect address for a 404.
				Checks permissions.

			Parameters:
				id - The id of the 404.
				url - The redirect URL.
		*/

		public function set404Redirect($id, $url) {
			$this->requireLevel(1);
			$id = sqlescape($id);
			$url = trim($url);

			// Try to convert the short URL into a full one
			if (strpos($url,"//") === false) {
				$url = WWW_ROOT.ltrim($url,"/");
			}

			$url = sqlescape(htmlspecialchars($this->autoIPL($url)));

			// Don't use static roots if they're the same as www just in case they are different when moving environments
			if (WWW_ROOT === STATIC_ROOT) {
				$url = str_replace("{staticroot}","{wwwroot}",$url);
			}

			sqlquery("UPDATE bigtree_404s SET redirect_url = '$url' WHERE id = '$id'");
			$this->track("bigtree_404s",$id,"updated");
		}

		/*
			Function: setCalloutPosition
				Sets the position of a callout.

			Parameters:
				id - The id of the callout.
				position - The position to set.
		*/

		public static function setCalloutPosition($id, $position) {
			BigTreeJSONDB::update("callouts", $id, ["position" => $position]);
		}

		/*
			Function: setModuleActionPosition
				Sets the position of a module action.

			Parameters:
				id - The id of the module action.
				position - The position to set.
		*/

		public static function setModuleActionPosition($id, $position) {
			$modules = BigTreeJSONDB::getAll("modules");

			foreach ($modules as $module) {
				foreach ($module["actions"] as $action) {
					if ($action["id"] == $id) {
						$context = BigTreeJSONDB::getSubset("modules", $module["id"]);
						$context->update("actions", $action["id"], ["position" => $position]);
					}
				}
			}
		}

		/*
			Function: setModuleGroupPosition
				Sets the position of a module group.

			Parameters:
				id - The id of the module group.
				position - The position to set.
		*/

		public static function setModuleGroupPosition($id,$position) {
			BigTreeJSONDB::update("module-groups", $id, ["position" => $position]);
		}

		/*
			Function: setModulePosition
				Sets the position of a module.

			Parameters:
				id - The id of the module.
				position - The position to set.
		*/

		public static function setModulePosition($id,$position) {
			BigTreeJSONDB::update("modules", $id, ["position" => $position]);
		}

		/*
			Function: setPagePosition
				Sets the position of a page.

			Parameters:
				id - The id of the page.
				position - The position to set.
		*/

		public static function setPagePosition($id,$position) {
			SQL::update("bigtree_pages", $id, ["position" => $position]);
		}

		/*
			Function: setPasswordHashForUser
				Creates a change password hash for a user.

			Parameters:
				user - A user entry.

			Returns:
				A change password hash.
		*/

		public static function setPasswordHashForUser($user) {
			$hash = md5(microtime().$user["password"]);
			sqlquery("UPDATE bigtree_users SET change_password_hash = '$hash' WHERE id = '".$user["id"]."'");
		}

		/*
			Function: setTemplatePosition
				Sets the position of a template.

			Parameters:
				id - The id of the template.
				position - The position to set.
		*/

		public static function setTemplatePosition($id, $position) {
			BigTreeJSONDB::update("templates", $id, ["position" => $position]);
		}

		/*
			Function: settingExists
				Determines whether a setting exists for a given id.

			Parameters:
				id - The setting id to check for.

			Returns:
				1 if the setting exists, otherwise 0.
		*/

		public static function settingExists($id) {
			$id = BigTreeCMS::extensionSettingCheck($id);

			return BigTreeJSONDB::exists("settings", $id);
		}

		/*
			Function: stop
				Stops processing of the Admin area and shows a message in the default layout.

			Parameters:
				message - Content to show (error, permission denied, etc)
		*/

		public function stop($message = "") {
			global $admin,$bigtree,$cms;
			echo $message;
			$bigtree["content"] = ob_get_clean();
			include BigTree::path("admin/layouts/".$bigtree["layout"].".php");
			die();
		}

		/*
			Function: stripMultipleRootTokens
				Strips the multi-domain root tokens from a string and replaces them with standard {wwwroot} and {staticroot}

			Parameters:
				string - A string

			Returns:
				A modified string.
		*/

		public static function stripMultipleRootTokens($string) {
			global $bigtree;

			if (empty($bigtree["config"]["sites"]) || !array_filter((array) $bigtree["config"]["sites"])) {
				return $string;
			}

			foreach ($bigtree["config"]["sites"] as $key => $data) {
				$string = str_replace(
					array("{wwwroot:$key}", "{staticroot:$key}"),
					array("{wwwroot}", "{staticroot}"),
					$string
				);
			}

			return $string;
		}

		/*
			Function: submitPageChange
				Adds a pending change to the bigtree_pending_changes table for the page.
				Determines what has changed and only stores the changed fields.
				Does not check permissions.

			Parameters:
				page - The page id or pending page id (prefixed with a "p")
				changes - An array of changes
		*/

		public function submitPageChange($page, $changes) {
			$page = sqlescape($page);

			if ($page[0] == "p") {
				// It's still pending...
				$type = "NEW";
				$pending = true;
				$existing_page = array();
				$existing_pending_change = array("id" => substr($page,1));
			} else {
				// It's an existing page
				$type = "EDIT";
				$pending = false;
				$existing_page = BigTreeCMS::getPage($page);
				$existing_pending_change = sqlfetch(sqlquery("SELECT id FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = '$page'"));
			}

			// Save tags separately
			$tags = BigTree::json(array_unique($changes["_tags"]) ?: [], true);
			unset($changes["_tags"]);

			// Encode fields
			$changes["title"] = htmlspecialchars($changes["title"]);
			$changes["nav_title"] = htmlspecialchars($changes["nav_title"]);
			$changes["meta_description"] = htmlspecialchars($changes["meta_description"]);
			$changes["seo_invisible"] = $changes["seo_invisible"]["seo_invisible"] ? "on" : "";
			$changes["external"] = htmlspecialchars($changes["external"]);

			// Convert times from user's timezone
			if ($changes["publish_at"] && $changes["publish_at"] != "NULL") {
				$changes["publish_at"] = $this->convertTimestampFromUser($changes["publish_at"]);
			}

			if ($changes["expire_at"] && $changes["expire_at"] != "NULL") {
				$changes["expire_at"] = $this->convertTimestampFromUser($changes["expire_at"]);
			}

			// Handle open graph
			$open_graph = BigTree::json($this->handleOpenGraph("bigtree_pages", null, $changes["_open_graph_"], true));
			unset($changes["_open_graph_"]);

			// Convert to an IPL
			if (!empty($changes["external"])) {
				$changes["external"] = $this->makeIPL($changes["external"]);
			}

			// Unset the trunk flag if the user isn't a developer
			if ($this->Level < 2) {
				unset($changes["trunk"]);
			// Make sure the value is changed -- since it's a check box it may not have come through
			} else {
				$changes["trunk"] = !empty($changes["trunk"]) ? "on" : "";
			}

			// Set the in_nav flag, since it's not in the post if the checkbox became unclicked
			$changes["in_nav"] = !empty($changes["in_nav"]) ? "on" : "";

			// If there's already a change in the queue, update it with this latest info.
			if ($existing_pending_change) {
				// If this is a pending page, just replace all the changes
				if ($pending) {
					$changes = BigTree::json($changes,true);
				// Otherwise, we need to check what's changed.
				} else {

					// We don't want to indiscriminately put post data in as changes, so we ensure it matches a column in the bigtree_pages table
					$diff = array();
					foreach ($changes as $key => $val) {
						if (array_key_exists($key,$existing_page) && $existing_page[$key] != $val) {
							$diff[$key] = $val;
						}
					}
					$changes = BigTree::json($diff,true);
				}

				// Update existing draft and track
				sqlquery("UPDATE bigtree_pending_changes SET changes = '$changes', tags_changes = '$tags', open_graph_changes = '$open_graph', date = NOW(), user = '".$this->ID."', type = '$type' WHERE id = '".$existing_pending_change["id"]."'");
				$this->track("bigtree_pages",$page,"updated-draft");

				return $existing_pending_change["id"];

			// We're submitting a change to a presently published page with no pending changes.
			} else {
				$diff = array();
				foreach ($changes as $key => $val) {
					if (array_key_exists($key,$existing_page) && $val != $existing_page[$key]) {
						$diff[$key] = $val;
					}
				}
				$changes = BigTree::json($diff,true);

				// Create draft and track
				sqlquery("INSERT INTO bigtree_pending_changes (`user`,`date`,`table`,`item_id`,`changes`,`tags_changes`,`open_graph_changes`,`type`,`title`) VALUES ('".$this->ID."',NOW(),'bigtree_pages','$page','$changes','$tags','$open_graph','EDIT','Page Change Pending')");
				$this->track("bigtree_pages",$page,"saved-draft");
				
				return sqlid();
			}
		}

		/*
			Function: track
				Logs a user's actions to the audit trail table.

			Parameters:
				table - The table affected by the user.
				entry - The primary key of the entry affected by the user.
				type - The action taken by the user (delete, edit, create, etc.)
		*/

		public function track($table, $entry, $type, $user = null) {
			// If this is running fron cron or something, nobody is logged in so don't track.
			if (isset($this->ID) || !is_null($user)) {
				SQL::insert("bigtree_audit_trail", [
					"table" => BigTree::safeEncode($table),
					"user" => !is_null($user) ? $user : $this->ID,
					"entry" => BigTree::safeEncode($entry),
					"date" => "NOW()",
					"type" => BigTree::safeEncode($type)
				]);
			}
		}

		/*
			Function: unarchivePage
				Unarchives a page and all its children that inherited archived status.
				Checks permissions.

			Parameters:
				page - The page id or page entry.

			Returns:
				true if successful. false if permission was denied.
		*/

		public function unarchivePage($page) {
			if (is_array($page)) {
				$page = sqlescape($page["id"]);
			} else {
				$page = sqlescape($page);
			}
			$access = $this->getPageAccessLevel($page);
			if ($access == "p" && $this->canModifyChildren(BigTreeCMS::getPage($page))) {
				sqlquery("UPDATE bigtree_pages SET archived = '' WHERE id = '$page'");
				$this->track("bigtree_pages",$page,"unarchived");
				$this->unarchivePageChildren($page);
				return true;
			}
			return false;
		}

		/*
			Function: unarchivePageChildren
				Unarchives a page's children that have the archived_inherited status.
				Does not checks permissions.

			Parameters:
				id - The parent page id.
		*/

		public function unarchivePageChildren($id) {
			$q = sqlquery("SELECT * FROM bigtree_pages WHERE parent = '$id' AND archived_inherited = 'on'");
			while ($f = sqlfetch($q)) {
				$this->track("bigtree_pages",$f["id"],"unarchived-inherited");
				$this->unarchivePageChildren($f["id"]);
			}
			sqlquery("UPDATE bigtree_pages SET archived = '', archived_inherited = '' WHERE parent = '$id' AND archived_inherited = 'on'");
		}

		/*
			Function: ungrowl
				Destroys the growl session.
		*/

		public static function ungrowl() {
			unset($_SESSION["bigtree_admin"]["growl"]);
		}

		/*
			Function: urlExists
				Attempts to connect to a URL using cURL.
				This is now an alias for BigTree::urlExists

			Parameters:
				url - The URL to connect to.

			Returns:
				true if it can connect, false if connection failed.

			See Also:
				BigTree::urlExists
		*/

		public static function urlExists($url) {
			return BigTree::urlExists($url);
		}

		/*
			Function: unCache
				Removes the cached copy of a given page.

			Parameters:
				page - Either a page id or a page entry.
		*/

		public static function unCache($page) {
			$url = "";

			// Already have the path
			if (is_array($page)) {
				$url = $page["path"]."/";
			} else {
				if ($page != 0) {
					$url = str_replace(WWW_ROOT,"",BigTreeCMS::getLink($page));
				}
			}

			@unlink(md5(json_encode(array("bigtree_htaccess_url" => $url))).".page");
			@unlink(md5(json_encode(array("bigtree_htaccess_url" => rtrim($url,"/")))).".page");
		}

		/*
			Function: unignore404
				Unignores a 404.
				Checks permissions.

			Parameters:
				id - The id of the 404.
		*/

		public function unignore404($id) {
			$this->requireLevel(1);
			$id = sqlescape($id);
			sqlquery("UPDATE bigtree_404s SET ignored = '' WHERE id = '$id'");
			$this->track("bigtree_404s",$id,"unignored");
		}

		/*
			Function: uniqueModuleActionRoute
				Returns a unique module action route.

			Parameters:
				module - The module to create a route for.
				route - The desired route.
				action - The ID of the action you're trying to set a new route for (optional)

			Returns:
				A unique action route.
		*/

		public static function uniqueModuleActionRoute($module,$route,$action = false) {
			$module = BigTreeJSONDB::get("modules", $module);
			$oroute = $route;
			$x = 2;
			
			do {
				$exists = false;

				foreach ($module["actions"] as $module_action) {
					if ($module_action["id"] != $action && $module_action["route"] == $route) {
						$exists = true;
						$route = $oroute."-".$x;
						$x++;
					}
				}
			} while ($exists);

			return $route;
		}

		/*
			Function: unlock
				Removes a lock from a table entry.

			Parameters:
				table - The table the entry is in.
				id - The id of the entry.
		*/

		public static function unlock($table,$id) {
			sqlquery("DELETE FROM bigtree_locks WHERE `table` = '".sqlescape($table)."' AND item_id = '".sqlescape($id)."'");
		}

		/*
			Function: updateCallout
				Updates a callout.

			Parameters:
				id - The id of the callout to update.
				name - The name.
				description - The description.
				level - The access level (0 for all users, 1 for administrators, 2 for developers)
				resources - An array of resources.
				display_field - The field to use as the display field describing a user's callout
				display_default - The text string to use in the event the display_field is blank or non-existent
		*/

		public function updateCallout($id,$name,$description,$level,$resources,$display_field,$display_default) {
			$clean_resources = [];

			foreach ($resources as $resource) {
				// "type" is still a reserved keyword due to the way we save callout data when editing.
				if ($resource["id"] && $resource["id"] != "type") {
					$settings = json_decode($resource["settings"] ?: $resource["options"], true);
					$settings = BigTree::arrayFilterRecursive($settings);
					
					$clean_resources[] = array(
						"id" => BigTree::safeEncode($resource["id"]),
						"type" => BigTree::safeEncode($resource["type"]),
						"title" => BigTree::safeEncode($resource["title"]),
						"subtitle" => BigTree::safeEncode($resource["subtitle"]),
						"settings" => $settings
					);
				}
			}

			BigTreeJSONDB::update("callouts", $id, [
				"name" => BigTree::safeEncode($name),
				"description" => BigTree::safeEncode($description),
				"level" => intval($level),
				"resources" => $clean_resources,
				"display_default" => BigTree::safeEncode($display_default),
				"display_field" => BigTree::safeEncode($display_field)
			]);

			$this->track("jsondb -> callouts", $id, "updated");
		}

		/*
			Function: updateCalloutGroup
				Updates a callout group's name and callout list.

			Parameters:
				id - The id of the callout group to update.
				name - The name.
				callouts - An array of callout IDs to assign to the group.
		*/

		public function updateCalloutGroup($id, $name, $callouts) {
			sort($callouts);
			
			BigTreeJSONDB::update("callout-groups", $id, [
				"name" => BigTree::safeEncode($name),
				"callouts" => $callouts
			]);
			
			$this->track("jsondb -> callout-groups",$id,"updated");
		}

		/*
			Function: updateChildPagePaths
				Updates the paths for pages who are descendants of a given page to reflect the page's new route.
				Also sets route history if the page has changed paths.

			Parameters:
				page - The page id.
		*/

		public static function updateChildPagePaths($page) {
			$page = sqlescape($page);
			$q = sqlquery("SELECT id,path FROM bigtree_pages WHERE parent = '$page'");
			while ($f = sqlfetch($q)) {
				$oldpath = $f["path"];
				$path = static::getFullNavigationPath($f["id"]);
				if ($oldpath != $path) {
					sqlquery("DELETE FROM bigtree_route_history WHERE old_route = '$path' OR old_route = '$oldpath'");
					sqlquery("INSERT INTO bigtree_route_history (`old_route`,`new_route`) VALUES ('$oldpath','$path')");
					sqlquery("UPDATE bigtree_pages SET path = '$path' WHERE id = '".$f["id"]."'");
					static::updateChildPagePaths($f["id"]);
				}
			}
		}

		/*
			Function: updateFeed
				Updates a feed.

			Parameters:
				id - The id of the feed to update.
				name - The name.
				description - The description.
				table - The data table.
				type - The feed type.
				settings - The feed type settings.
				fields - The fields.
		*/

		public function updateFeed($id,$name,$description,$table,$type,$settings,$fields) {
			if (!is_array($settings)) {
				$settings = array_filter((array) json_decode($settings, true));
			}

			BigTreeJSONDB::update("feeds", $id, [
				"name" => BigTree::safeEncode($name),
				"description" => BigTree::safeEncode($description),
				"table" => $table,
				"type" => $type,
				"settings" => $settings,
				"fields" => $fields
			]);

			$this->track("jsondb -> feeds", $id, "updated");
		}

		/*
			Function: updateFieldType
				Updates a field type.

			Parameters:
				id - The id of the field type.
				name - The name.
				use_cases - Associate array of sections in which the field type can be used (i.e. array("pages" => "on", "modules" => "","callouts" => "","settings" => ""))
				self_draw - Whether this field type will draw its <fieldset> and <label> ("on" or a falsey value)
		*/

		public function updateFieldType($id,$name,$use_cases,$self_draw) {
			BigTreeJSONDB::update("field-types", $id, [
				"name" => BigTree::safeEncode($name),
				"use_cases" => $use_cases,
				"self_draw" => $self_draw ? "on" : null
			]);
			$this->track("jsondb -> field-types", $id, "updated");
		}

		/*
			Function: updateInternalSettingValue
				Updates the value of an internal BigTree setting.

			Parameters:
				id - The id of the setting to update.
				value - A value to set (can be a string or array).
				encrypted - Whether the value should be encrypted (defaults to false).
		*/

		public static function updateInternalSettingValue($id, $value, $encrypted = false) {
			global $bigtree;

			if (is_array($value)) {
				$value = BigTree::translateArray($value);
			} else {
				$value = static::autoIPL($value);
			}

			$value = BigTree::json($value);
			
			if (!SQL::exists("bigtree_settings", $id)) {
				SQL::insert("bigtree_settings", [
					"id" => $id,
					"encrypted" => $encrypted ? "on" : ""
				]);
			}

			if ($encrypted) {
				SQL::query("UPDATE bigtree_settings SET `value` = AES_ENCRYPT(?, ?) WHERE id = ?", $value, $bigtree["config"]["settings_key"], $id);
			} else {
				SQL::update("bigtree_settings", $id, ["value" => $value]);
			}
		}

		/*
			Function: updateModule
				Updates a module.

			Parameters:
				id - The id of the module to update.
				name - The name of the module.
				group - The group for the module.
				class - The module class to create.
				permissions - The group-based permissions.
				icon - The icon to use.
		*/

		public function updateModule($id,$name,$group,$class,$permissions,$icon) {
			// If this has a permissions table, wipe that table's view cache
			if ($permissions["table"]) {
				BigTreeAutoModule::clearCache($permissions["table"]);
			}

			BigTreeJSONDB::update("modules", $id, [
				"name" => BigTree::safeEncode($name),
				"group" => $group ?: null,
				"class" => $class,
				"gbp" => $permissions,
				"icon" => $icon
			]);

			$this->track("jsondb -> modules",$id,"updated");

			// Remove cached class list.
			unlink(SERVER_ROOT."cache/bigtree-module-class-list.json");
		}

		/*
			Function: updateModuleAction
				Updates a module action.

			Parameters:
				id - The id of the module action to update.
				name - The name of the action.
				route - The action route.
				in_nav - Whether the action is in the navigation.
				icon - The icon class for the action.
				form - The associated form.
				view - The associated view.
				report - The associated report.
				level - The required access level.
				position - The position in navigation.
		*/

		public function updateModuleAction($id,$name,$route,$in_nav,$icon,$form,$view,$report,$level,$position) {
			$modules = BigTreeJSONDB::getAll("modules");

			foreach ($modules as $module) {
				foreach ($module["actions"] as $action) {
					if ($action["id"] == $id) {
						$context = BigTreeJSONDB::getSubset("modules", $module["id"]);
						$context->update("actions", $action["id"], [
							"route" => $this->uniqueModuleActionRoute($module["id"], $route, $action["id"]),
							"in_nav" => $in_nav ? "on" : "",
							"class" => $icon,
							"name" => BigTree::safeEncode($name),
							"level" => intval($level),
							"form" => $form ?: null,
							"view" => $view ?: null,
							"report" => $report ?: null,
							"position" => intval($position)
						]);
					}
				}
			}

			$this->track("jsondb -> module-actions", $id, "updated");
		}

		/*
			Function: updateModuleEmbedForm
				Updates an embeddable form.

			Parameters:
				id - The ID of the form.
				title - The title of the form.
				table - The table for the form data.
				fields - The form fields.
				hooks - An array of "pre", "post", and "publish" keys that can be function names to call
				default_position - Default position for entries to the form (if the view is positioned).
				default_pending - Whether the submissions to default to pending or not ("on" or "").
				css - URL of a CSS file to include.
				redirect_url - The URL to redirect to upon completion of submission.
				thank_you_message - The message to display upon completeion of submission.
		*/

		public function updateModuleEmbedForm($id,$title,$table,$fields,$hooks = array(),$default_position = "",$default_pending = "",$css = "",$redirect_url = "",$thank_you_message = "") {
			$modules = BigTreeJSONDB::getAll("modules");
			$clean_fields = [];

			foreach ($fields as $key => $field) {
				$field["settings"] = json_decode($field["settings"], true);
				$field["settings"] = BigTree::arrayFilterRecursive($field["settings"]);
				$field["column"] = $key;

				$clean_fields[] = $field;
			}
			
			foreach ($modules as $module) {
				foreach ($module["embeddable-forms"] as $form) {
					if ($form["id"] == $id) {
						$context = BigTreeJSONDB::getSubset("modules", $module["id"]);
						$context->update("embeddable-forms", $form["id"], [
							"title" => BigTree::safeEncode($title),
							"table" => $table,
							"fields" => $clean_fields,
							"hooks" => $hooks,
							"default_position" => $default_position,
							"default_pending" => $default_pending ? "on" : "",
							"css" => BigTree::safeEncode($css),
							"redirect_url" => BigTree::safeEncode($redirect_url),
							"thank_you_message" => $thank_you_message
						]);
					}
				}
			}
			
			$this->track("jsondb -> module-embeddable-forms",$id,"updated");
		}

		/*
			Function: updateModuleForm
				Updates a module form.

			Parameters:
				id - The id of the form.
				title - The title of the form.
				table - The table for the form data.
				fields - The form fields.
				hooks - An array of "pre", "post", and "publish" keys that can be function names to call
				default_position - Default position for entries to the form (if the view is positioned).
				return_view - The view to return to when the form is completed.
				return_url - The alternative URL to return to when the form is completed.
				tagging - Whether or not to enable tagging.
				open_graph - Whether or not to enable open graph attributes.
		*/

		public function updateModuleForm($id,$title,$table,$fields,$hooks = array(),$default_position = "",$return_view = false,$return_url = "",$tagging = "",$open_graph = "") {
			$clean_fields = [];
			$modules = BigTreeJSONDB::getAll("modules");

			if (is_string($hooks)) {
				$hooks = json_decode($hooks, true);
			}
			
			foreach ($fields as $key => $field) {
				if (!empty($field["settings"])) {
					$field["settings"] = json_decode($field["settings"], true);
				} else {
					$field["settings"] = json_decode($field["options"], true);
				}

				$field["settings"] = BigTree::arrayFilterRecursive($field["settings"]);
				$field["column"] = $key;
				$field["title"] = BigTree::safeEncode($field["title"]);
				$field["subtitle"] = BigTree::safeEncode($field["subtitle"]);
				
				$clean_fields[] = $field;
			}

			foreach ($modules as $module) {
				foreach ($module["forms"] as $form) {
					if ($form["id"] == $id) {
						$context = BigTreeJSONDB::getSubset("modules", $module["id"]);
						$context->update("forms", $form["id"], [
							"title" => BigTree::safeEncode($title),
							"table" => $table,
							"fields" => $clean_fields,
							"hooks" => $hooks,
							"default_position" => $default_position,
							"return_view" => $return_view ?: null,
							"return_url" => BigTree::safeEncode($return_url),
							"tagging" => $tagging ? "on" : "",
							"open_graph" => $open_graph ? "on" : ""
						]);
					}
				}

				foreach ($module["actions"] as $action) {
					if ($action["form"] == $id) {
						if (substr($action["route"], 0, 3) == "add") {
							$context = BigTreeJSONDB::getSubset("modules", $module["id"]);
							$context->update("actions", $action["id"], ["name" => "Add $title"]);
						} elseif (substr($action["route"], 0, 4) == "edit") {
							$context = BigTreeJSONDB::getSubset("modules", $module["id"]);
							$context->update("actions", $action["id"], ["name" => "Edit $title"]);							
						}
					}
				}
			}

			// Get related views for this table and update numeric status
			static::updateModuleViewColumnNumericStatusForTable($table);
			
			$this->track("jsondb -> module-forms",$id,"updated");
		}

		/*
			Function: updateModuleGroup
				Updates a module group's name.

			Parameters:
				id - The id of the module group to update.
				name - The name of the module group.
		*/

		public function updateModuleGroup($id, $name) {
			// Get a unique route
			$x = 2;
			$route = BigTreeCMS::urlify($name);
			$oroute = $route;
			$existing = BigTreeJSONDB::get("module-groups", $route, "route");

			while ($existing && $existing["id"] != $id) {
				$route = $oroute."-".$x;
				$x++;
			}

			BigTreeJSONDB::update("module-groups", $id, [
				"name" => BigTree::safeEncode($name),
				"route" => $route
			]);

			$this->track("jsondb -> module-groups", $id, "updated");
		}

		/*
			Function: updateModuleReport
				Updates a module report.

			Parameters:
				id - The ID of the report to update.
				title - The title of the report.
				table - The table for the report data.
				type - The type of report (csv or view).
				filters - The filters a user can use to create the report.
				fields - The fields to show in the CSV export (if type = csv).
				parser - An optional parser function to run on the CSV export data (if type = csv).
				view - A module view ID to use (if type = view).
		*/

		public function updateModuleReport($id,$title,$table,$type,$filters,$fields = "",$parser = "",$view = "") {
			$modules = BigTreeJSONDB::getAll("modules");
			
			foreach ($modules as $module) {
				foreach ($module["reports"] as $report) {
					if ($report["id"] == $id) {
						$context = BigTreeJSONDB::getSubset("modules", $module["id"]);
						$context->update("reports", $report["id"], [
							"title" => BigTree::safeEncode($title),
							"table" => $table,
							"type" => $type,
							"filters" => $filters,
							"fields" => $fields,
							"parser" => $parser,
							"view" => $view ?: null
						]);
					}
				}

				foreach ($module["actions"] as $action) {
					if ($action["report"] == $id) {
						$context = BigTreeJSONDB::getSubset("modules", $module["id"]);
						$context->update("actions", $action["id"], ["name" => BigTree::safeEncode($title)]);
					}
				}
			}

			$this->track("jsondb -> module-reports",$id,"updated");
		}

		/*
			Function: updateModuleView
				Updates a module view.

			Parameters:
				id - The view id.
				title - View title.
				description - Description.
				table - Data table.
				type - View type.
				settings - View settings array.
				fields - Field array.
				actions - Actions array.
				related_form - Form ID to handle edits.
				preview_url - Optional preview URL.

			Returns:
				The id for view.
		*/

		public function updateModuleView($id,$title,$description,$table,$type,$settings,$fields,$actions,$related_form,$preview_url = "") {
			$modules = BigTreeJSONDB::getAll("modules");
			
			foreach ($modules as $module) {
				foreach ($module["views"] as $view) {
					if ($view["id"] == $id) {
						$context = BigTreeJSONDB::getSubset("modules", $module["id"]);
						$context->update("views", $view["id"], [
							"title" => BigTree::safeEncode($title),
							"description" => BigTree::safeEncode($description),
							"table" => $table,
							"type" => $type,
							"settings" => $settings,
							"fields" => $fields,
							"actions" => $actions,
							"preview_url" => BigTree::safeEncode($preview_url),
							"related_form" => $related_form ?: null
						]);
					}
				}

				foreach ($module["actions"] as $action) {
					if ($action["view"] == $id) {
						$context = BigTreeJSONDB::getSubset("modules", $module["id"]);
						$context->update("actions", $action["id"], ["name" => "View ".BigTree::safeEncode($title)]);
					}
				}
			}

			static::updateModuleViewColumnNumericStatusForTable($table);
			$this->track("jsondb -> module-views", $id, "updated");
		}

		/*
			Function: updateModuleViewColumnNumericStatusForTable
				Updates module view columns to designate whether they are numeric or not based on parsers, column type, and related forms.

			Parameters:
				table - A table to update view columns for
		*/

		public static function updateModuleViewColumnNumericStatusForTable($table_name) {
			$modules = BigTreeJSONDB::getAll("modules");

			foreach ($modules as $module) {
				foreach ($module["views"] as $view) {
					if ($view["table"] == $table_name) {
						if (is_array($view["fields"])) {
							$form = BigTreeAutoModule::getRelatedFormForView($view);
							$table = BigTree::describeTable($view["table"]);
			
							foreach ($view["fields"] as $key => $field) {
								$numeric = false;
								$type = $table["columns"][$key]["type"];
								
								if (in_array($type, ["int", "float", "double", "double precision", "tinyint", "smallint", "mediumint", "bigint", "real", "decimal", "dec", "fixed", "numeric"])) {
									$numeric = true;
								}

								if ($field["parser"] || ($form["fields"][$key]["type"] == "list" && $form["fields"][$key]["list_type"] == "db")) {
									$numeric = false;
								}
			
								$view["fields"][$key]["numeric"] = $numeric;
							}

							$context = BigTreeJSONDB::getSubset("modules", $module["id"]);
							$context->update("views", $view["id"], $view);
						}
					}
				}
			}
		}

		/*
			Function: updateModuleViewFields
				Updates the fields for a module view.

			Parameters:
				view - The view id.
				fields - A fields array.
		*/

		public function updateModuleViewFields($view_id, $fields) {
			$modules = BigTreeJSONDB::getAll("modules");

			foreach ($modules as $module) {
				foreach ($module["views"] as $view) {
					if ($view["id"] == $view_id) {
						$context = BigTreeJSONDB::getSubset("modules", $module["id"]);
						$context->update("views", $view["id"], ["fields" => $fields]);
					}
				}
			}

			$this->track("jsondb -> module-views", $view, "updated");
		}

		/*
			Function: updatePage
				Updates a page.
				Checks some (but not all) permissions.

			Parameters:
				page - The page id to update.
				data - The page data to update with.
				publisher - If set to true, the owner of the current pending change will be marked as the author of this page. (defaults to false)
		*/

		public function updatePage($page, $data) {
			$page = sqlescape($page);

			// Save the existing copy as a draft, remove drafts for this page that are one month old or older.
			$current = sqlfetch(sqlquery("SELECT * FROM bigtree_pages WHERE id = '$page'"));
			
			// Figure out if we currently have a template that the user isn't allowed to use. If they do, we're not letting them change it.
			$template_data = BigTreeCMS::getTemplate($current["template"]);
			
			if (is_array($template_data) && $template_data["level"] > $this->Level) {
				$data["template"] = $current["template"];
			}
			
			// Copy it to the saved versions
			SQL::insert("bigtree_page_revisions", [
				"page" => $page,
				"title" => $current["title"],
				"meta_description" => $current["meta_description"],
				"template" => $current["template"],
				"external" => $current["external"],
				"new_window" => $current["new_window"],
				"resources" => $current["resources"],
				"author" => $current["last_edited_by"],
				"updated_at" => $current["updated_at"],
				"resource_allocation" => SQL::fetchAllSingle("SELECT resource FROM bigtree_resource_allocation WHERE `table` = 'bigtree_pages' AND `entry` = ?", $page)
			]);
			
			// Count the page revisions
			$revision_count = SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_page_revisions WHERE page = ? AND saved = ''", $page);
			
			// If we have more than 10, delete any that are more than a month old
			if ($revision_count > 10) {
				$one_month_ago = date("Y-m-d",strtotime("-1 month"));
				SQL::query("DELETE FROM bigtree_page_revisions
						    WHERE page = ?
							  AND updated_at < '$one_month_ago'
							  AND saved = ''
						    ORDER BY updated_at ASC LIMIT ".($revision_count - 10), $page);
			}

			// Remove this page from the cache
			static::unCache($page);

			// Set the trunk flag back to the current value if the user isn't a developer
			if ($this->Level < 2) {
				$trunk = $current["trunk"];
			} else {
				$trunk = $data["trunk"] ? "on" : "";
			}

			// If this is top level nav and the user isn't a developer, use what the current state is.
			if (!$current["parent"] && $this->Level < 2) {
				$in_nav = $current["in_nav"];
			} else {
				$in_nav = $data["in_nav"];
			}

			// Make an ipl:// or {wwwroot}'d version of the URL
			if ($data["external"]) {
				$data["external"] = static::makeIPL($data["external"]);
			}

			// If somehow we didn't provide a parent page (like, say, the user didn't have the right to change it) then pull the one from before. Actually, this might be exploitable… look into it later.
			if (!isset($data["parent"])) {
				$data["parent"] = $current["parent"];
			}

			if ($page == 0) {
				// Home page doesn't get a route - fixes sitemap bug
				$data["route"] = "";
			} else {
				// Create a route if we don't have one, otherwise, make sure the one they provided doesn't suck.
				$route = $data["route"];
				
				if (!$route) {
					$route = BigTreeCMS::urlify($data["nav_title"]);
				} else {
					$route = BigTreeCMS::urlify($route);
				}

				// Get a unique route
				$oroute = $route;
				$x = 2;
				
				// Reserved paths.
				if ($data["parent"] == 0) {
					while (file_exists(SERVER_ROOT."site/".$route."/")) {
						$route = $oroute."-".$x;
						$x++;
					}
					
					while (in_array($route,static::$ReservedTLRoutes)) {
						$route = $oroute."-".$x;
						$x++;
					}
				}

				// Existing pages.
				$exists = SQL::exists("bigtree_pages", ["route" => $route, "parent" => $data["parent"]], $page);

				while ($exists) {
					$route = $oroute."-".$x;
					$exists = SQL::exists("bigtree_pages", ["route" => $route, "parent" => $data["parent"]], $page);
					$x++;
				}

				// Make sure route isn't longer than 255
				$data["route"] = substr($route, 0, 255);
			}

			// We have no idea how this affects the nav, just wipe it all.
			if ($current["nav_title"] != $data["nav_title"] || $current["route"] != $data["route"] ||
				$current["in_nav"] != $in_nav || $current["parent"] != $data["parent"]) {
				static::clearCache();
			}

			// Make sure we set the publish date to NULL if it wasn't provided or we'll have a page that got published at 0000-00-00
			if ($data["publish_at"] && $data["publish_at"] != "NULL") {
				$publish_at = $this->convertTimestampFromUser($data["publish_at"]);
			} else {
				$publish_at = null;
			}

			// If we set an expiration date, make it the proper MySQL format.
			if ($data["expire_at"] && $data["expire_at"] != "NULL") {
				$expire_at = $this->convertTimestampFromUser($data["expire_at"]);
			} else {
				$expire_at = null;
			}

			// Set the full path, saves DB access time on the front end.
			if ($data["parent"] > 0) {
				$path = static::getFullNavigationPath($data["parent"])."/".$data["route"];
			} else {
				$path = $data["route"];
			}

			$update = [
				"trunk" => $data["trunk"],
				"parent" => $data["parent"],
				"nav_title" => BigTree::safeEncode($data["nav_title"]),
				"title" => BigTree::safeEncode($data["title"]),
				"route" => $data["route"],
				"path" => $path,
				"in_nav" => $in_nav ? "on" : "",
				"template" => $data["template"],
				"external" => BigTree::safeEncode($data["external"]),
				"new_window" => $data["new_window"] ? "Yes" : "",
				"resources" => $data["resources"],
				"meta_description" => BigTree::safeEncode($data["meta_description"]),
				"seo_invisible" => $data["seo_invisible"] ? "on" : "",
				"publish_at" => $publish_at,
				"expire_at" => $expire_at,
				"max_age" => $data["max_age"],
				"last_edited_by" => $this->ID,
				"updated_at" => "NOW()"
			];

			// Check if this data is exactly the same as the pending copy -- if it is, attribute it to the change author, not the publisher
			$pending = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = ?", $page);

			if ($pending && $pending["user"] != $this->ID) {
				$exact = true;
				$changes = BigTree::untranslateArray(json_decode($pending["changes"], true));

				foreach ($changes as $key => $value) {
					if ($update[$key] != $value) {
						$exact = false;
					}
				}

				if ($exact) {
					$update["last_edited_by"] = $pending["user"];
					$this->track("bigtree_pages", $page, "updated via publisher", $pending["user"]);
					$this->track("bigtree_pages", $page, "published");
				} else {
					$this->track("bigtree_pages", $page, "updated");
				}
			} else {
				$this->track("bigtree_pages", $page, "updated");
			}
			
			SQL::update("bigtree_pages", $page, $update);

			// Remove any pending drafts
			SQL::delete("bigtree_pending_changes", ["table" => "bigtree_pages", "item_id" => $page]);

			// Remove old paths from the redirect list
			SQL::query("DELETE FROM bigtree_route_history WHERE old_route = ? OR old_route = ?", $path, $current["path"]);

			// Create an automatic redirect from the old path to the new one.
			if ($current["path"] != $path) {
				SQL::insert("bigtree_route_history", [
					"old_route" => $current["path"],
					"new_route" => $path
				]);
				
				// Update all child page routes, ping those engines, clean those caches
				static::updateChildPagePaths($page);
				static::pingSearchEngines();
				static::clearCache();
			}

			// Handle tags
			$existing_tags = SQL::fetchAllSingle("SELECT `tag` FROM bigtree_tags_rel WHERE `table` = 'bigtree_pages' AND `entry` = ?", $page);
			SQL::delete("bigtree_tags_rel", ["table" => "bigtree_pages", "entry" => $page]);

			if (is_array($data["_tags"])) {
				$data["_tags"] = array_unique($data["_tags"]);

				foreach ($data["_tags"] as $tag) {
					SQL::insert("bigtree_tags_rel", [
						"table" => "bigtree_pages",
						"entry" => $page,
						"tag" => $tag
					]);
				}
			} else {
				$data["_tags"] = [];
			}

			$update_tags = array_merge($data["_tags"], $existing_tags);

			if (count($update_tags)) {
				$this->updateTagReferenceCounts($update_tags);
			}
			
			// Handle Open Graph
			$this->handleOpenGraph("bigtree_pages", $page, $data["_open_graph_"]);

			// See if this template has a publish hook
			$template = BigTreeCMS::getTemplate($data["template"]);

			if (!empty($template["hooks"]["publish"])) {
				call_user_func($template["hooks"]["publish"], "bigtree_pages", $page, $update, [], $data["_tags"], $data["_open_graph_"]);
			}
			
			// If this page is a trunk in a multi-site setup, wipe the cache
			foreach (BigTreeCMS::$SiteRoots as $site_path => $site_data) {
				if ($site_data["trunk"] == $page) {
					unlink(SERVER_ROOT."cache/bigtree-multi-site-cache.json");
				}
			}

			return $page;
		}

		/*
			Function: updatePageParent
				Changes a page's parent.
				Checks permissions.

			Parameters:
				page - The page to update.
				parent - The parent to switch to.
		*/

		public function updatePageParent($page,$parent) {
			$page = sqlescape($page);
			$parent = sqlescape($parent);

			if ($this->Level < 1) {
				$this->stop("You are not allowed to move pages.");
			}

			// Get the existing path so we can create a route history
			$current = sqlfetch(sqlquery("SELECT in_nav,path FROM bigtree_pages WHERE id = '$page'"));
			$old_path = sqlescape($current["path"]);

			// If the current user isn't a developer and is moving the page to top level, set it to not be visible
			$in_nav = $current["in_nav"] ? "on" : "";
			if ($this->Level < 2 && $parent == 0) {
				$in_nav = "";
			}

			sqlquery("UPDATE bigtree_pages SET in_nav = '$in_nav', parent = '$parent' WHERE id = '$page'");
			$path = sqlescape($this->getFullNavigationPath($page));

			// Set the route history
			sqlquery("DELETE FROM bigtree_route_history WHERE old_route = '$path' OR old_route = '$old_path'");
			sqlquery("INSERT INTO bigtree_route_history (`old_route`,`new_route`) VALUES ('$old_path','$path')");

			// Update the page with its new path.
			sqlquery("UPDATE bigtree_pages SET path = '$path' WHERE id = '$page'");

			// Update the paths of any child pages.
			$this->updateChildPagePaths($page);
			$this->track("bigtree_pages",$page,"moved");
		}

		/*
			Function: updatePageRevision
				Updates a page revision to save it as a favorite.
				Checks permissions.

			Parameters:
				id - The page revision id.
				description - Saved description.
		*/

		public function updatePageRevision($id,$description) {
			// Get the version, check if the user has access to the page the version refers to.
			$revision = $this->getPageRevision($id);
			$access = $this->getPageAccessLevel($revision["page"]);
			if ($access != "p") {
				$this->stop("You must be a publisher to manage revisions.");
			}

			// Save the version's description and saved status
			$description = sqlescape(htmlspecialchars($description));
			sqlquery("UPDATE bigtree_page_revisions SET saved = 'on', saved_description = '$description' WHERE id = '".$revision["id"]."'");
			$this->track("bigtree_page_revisions",$id,"updated");
		}

		/*
			Function: updatePendingChange
				Updates a pending change.

			Parameters:
				id - The id of the pending change.
				changes - The changes to the fields in the entry.
				mtm_changes - Many to Many changes.
				tags_changes - Tags changes.
		*/

		public function updatePendingChange($id,$changes,$mtm_changes = array(),$tags_changes = array()) {
			$id = sqlescape($id);
			$changes = BigTree::json($changes,true);
			$mtm_changes = BigTree::json($mtm_changes,true);
			$tags_changes = BigTree::json($tags_changes,true);

			sqlquery("UPDATE bigtree_pending_changes SET changes = '$changes', mtm_changes = '$mtm_changes', tags_changes = '$tags_changes', date = NOW(), user = '".$this->ID."' WHERE id = '$id'");
			$this->track("bigtree_pending_changes",$id,"updated");
		}

		/*
			Function: updateTagReferenceCounts
				Updates the reference counts for tags to accurately match active database entries.

			Parameters:
				tags - An array of tag IDs to update reference counts for (defaults to all tags)
		*/

		public static function updateTagReferenceCounts($tags = array()) {
			if (!count($tags)) {
				$tags = array();
				$query = sqlquery("SELECT id FROM bigtree_tags");

				while ($tag = sqlfetch($query)) {
					$tags[] = $tag["id"];
				}
			}

			foreach ($tags as $tag_id) {
				$tag_id = intval($tag_id);
				$query = sqlquery("SELECT * FROM bigtree_tags_rel WHERE `tag` = '$tag_id'");
				$reference_count = 0;

				while ($reference = sqlfetch($query)) {
					// See if the related entry still exists
					$row = sqlrows(sqlquery("SELECT id FROM `".$reference["table"]."` WHERE id = '".sqlescape($reference["entry"])."'"));

					if ($row) {
						$reference_count++;
					} else {
						sqlquery("DELETE FROM bigtree_tags_rel WHERE `id` = '".$reference["id"]."'");
					}
				}

				sqlquery("UPDATE bigtree_tags SET usage_count = '$reference_count' WHERE id = '$tag_id'");
			}
		}

		/*
			Function: updateProfile
				Updates a user's name, company, digest setting, and (optionally) password.

			Parameters:
				data - Array containing name / company / daily_digest / password.
		*/

		public function updateProfile($data) {
			global $bigtree;

			$update = [
				"name" => BigTree::safeEncode($data["name"]),
				"company" => BigTree::safeEncode($data["company"]),
				"daily_digest" => !empty($data["daily_digest"]) ? "on" : "",
				"timezone" => $data["timezone"]
			];

			if ($data["password"]) {
				$update["password"] = password_hash($data["password"], PASSWORD_DEFAULT);
				$update["new_hash"] = "on";

				// Clean existing sessions
				$email = SQL::fetchSingle("SELECT email FROM bigtree_users WHERE id = ?", $this->ID);
				SQL::delete("bigtree_sessions", ["logged_in_user" => $this->ID]);
				SQL::delete("bigtree_user_sessions", ["email" => $email]);
			}

			SQL::update("bigtree_users", $this->ID, $update);
		}

		/*
			Function: updateResource
				Updates a resource.

			Parameters:
				id - The id of the resource.
				data - A key/value array of fields to update.
		*/

		public function updateResource($id, $data) {
			SQL::update("bigtree_resources", $id, $data);
			$this->track("bigtree_resources",$id,"updated");
		}

		/*
			Function: updateResourceAllocation
				Updates resource allocation to move pending changes to the live entry.

			Parameters:
				table - Table the entry is in
				entry - Entry ID
				pending_id - The pending entry ID
		*/

		public function updateResourceAllocation($table, $entry, $pending_id) {
			SQL::delete("bigtree_resource_allocation", ["table" => $table, "entry" => $entry]);
			SQL::update("bigtree_resource_allocation", ["table" => $table, "entry" => "p".$pending_id], ["entry" => $entry]);
		}

		/*
			Function: updateResourceFolder
				Updates a resource folder.

			Parameters:
				id - The id of the resource folder.
				name - The new name for the resource folder.
				parent - The new parent for the resource folder.
		*/

		public function updateResourceFolder($id, $name, $parent = null) {
			if ($parent !== null && $this->Level < 1) {
				$this->stop("Only administrators can move a resource folder.");
			}

			$permission = $this->getResourceFolderPermission($id);

			if ($permission != "p") {
				$this->stop("You do not have permission to edit this folder.");
			}

			$data = [
				"name" => BigTree::safeEncode($name)
			];

			if ($parent !== null) {
				$data["parent"] = intval($parent);
			}

			SQL::update("bigtree_resource_folders", $id, $data);

			$this->track("bigtree_resource_folders", $id, "updated");
		}

		/*
			Function: updateSetting
				Updates a setting.

			Parameters:
				old_id - The current id of the setting to update.
				data - The new data for the setting ("id", "type", "name", "description", "locked", "system", "encrypted")

			Returns:
				true if successful, false if a setting exists for the new id already.
		*/

		public function updateSetting($old_id, $data) {
			global $bigtree;

			// See if we have an id collision with the new id.
			if ($old_id != $data["id"] && static::settingExists($data["id"])) {
				return false;
			}

			// Get the existing setting information.
			$existing = static::getSetting($old_id);

			// Stored as JSON encoded already
			if (is_array($data["settings"])) {
				$settings = $data["settings"];
			} else {
				$settings = json_decode($data["settings"] ?: $data["options"], true);
			}

			foreach ($settings as $key => $value) {
				if (($key == "options" || $key == "settings") && is_string($value)) {
					$settings[$key] = json_decode($value, true);
				}
			}

			$settings = BigTree::arrayFilterRecursive($settings);

			BigTreeJSONDB::update("settings", $old_id, [
				"id" => $data["id"],
				"type" => $data["type"],
				"name" => BigTree::safeEncode($data["name"]),
				"description" => $data["description"],
				"locked" => !empty($data["locked"]) ? "on" : "",
				"system" => !empty($data["system"]) ? "on" : "",
				"encrypted" => !empty($data["encrypted"]) ? "on" : "",
				"settings" => $settings
			]);

			if ($old_id != $data["id"]) {
				SQL::update("jsondb -> settings", $old_id, ["id" => $data["id"]]);
			}

			// If encryption status has changed, update the value
			if ($existing["encrypted"] && !$data["encrypted"]) {
				SQL::query("UPDATE bigtree_settings SET value = AES_DECRYPT(value, ?) WHERE id = ?", $bigtree["config"]["settings_key"], $data["id"]);
			}

			if (!$existing["encrypted"] && $data["encrypted"]) {
				SQL::query("UPDATE bigtree_settings SET value = AES_ENCRYPT(value, ?) WHERE id = ?", $bigtree["config"]["settings_key"], $data["id"]);
			}

			// Audit trail.
			if ($old_id != $data["id"]) {
				$this->track("jsondb -> settings", $old_id, "id -> ".$data["id"]);
			}

			$this->track("jsondb -> settings", $data["id"], "updated");

			return true;
		}

		/*
			Function: updateSettingValue
				Updates the value of a setting.

			Parameters:
				id - The id of the setting to update.
				value - A value to set (can be a string or array).
		*/

		public static function updateSettingValue($id, $value) {
			global $bigtree, $admin;

			$item = static::getSetting($id, false);
			$id = BigTreeCMS::extensionSettingCheck($id);

			if (is_array($value)) {
				$value = BigTree::translateArray($value);
			} else {
				$value = static::autoIPL($value);
			}

			$value = BigTree::json($value);
			
			if (SQL::exists("bigtree_settings", $id)) {
				if ($item["encrypted"]) {
					SQL::query("UPDATE bigtree_settings SET `value` = AES_ENCRYPT(?, ?) WHERE id = ?", $value, $bigtree["config"]["settings_key"], $id);
				} else {
					SQL::update("bigtree_settings", $id, ["value" => $value]);
				}
			} else {
				if ($item["encrypted"]) {
					SQL::query("INSERT INTO bigtree_settings (`id`, `value`, `encrypted`) VALUES (?, AES_ENCRYPT(?, ?), 'on')", $id, $value, $bigtree["config"]["settings_key"]);
				} else {
					SQL::insert("bigtree_settings", ["id" => $id, "value" => $value]);
				}
			}

			if (!$item["system"] && is_object($admin) && method_exists($admin, "track")) {
				// Audit trail.
				$admin->track("bigtree_settings",$id,"updated");
			}
		}

		/*
			Function: updateTemplate
				Updates a template.

			Parameters:
				id - The id of the template to update.
				name - Name
				level - Access level (0 for everyone, 1 for administrators, 2 for developers)
				module - Related module id
				resources - An array of resources
				hooks - An array of hooks
		*/

		public function updateTemplate($id,$name,$level,$module,$resources,$hooks = []) {
			$clean_resources = array();

			foreach ($resources as $resource) {
				if ($resource["id"]) {
					$settings = json_decode($resource["settings"] ?: $resource["options"], true);
					$settings = BigTree::arrayFilterRecursive($settings);

					$clean_resources[] = array(
						"id" => BigTree::safeEncode($resource["id"]),
						"title" => BigTree::safeEncode($resource["title"]),
						"subtitle" => BigTree::safeEncode($resource["subtitle"]),
						"type" => BigTree::safeEncode($resource["type"]),
						"settings" => $settings
					);
				}
			}

			BigTreeJSONDB::update("templates", $id, [
				"resources" => $clean_resources,
				"name" => BigTree::safeEncode($name),
				"module" => $module,
				"level" => $level,
				"hooks" => is_array($hooks) ? $hooks : array_filter((array) json_decode($hooks, true))
			]);

			$this->track("jsondb -> templates", $id, "updated");
		}

		/*
			Function: updateUser
				Updates a user.

			Parameters:
				id - The user's "id"
				data - A key/value array containing email, name, company, level, permissions, alerts, daily_digest, and (optionally) password.

			Returns:
				True if successful. False if the logged in user doesn't have permission to change the user or there was an email collision.
		*/

		public function updateUser($id, $data) {
			$level = intval($data["level"]);

			// See if there's an email collission
			$r = SQL::query("SELECT * FROM bigtree_users WHERE email = ? AND id != ?", $data["email"], $id)->rows();

			if ($r) {
				return false;
			}

			// If this person has higher access levels than the person trying to update them, fail.
			$current = static::getUser($id);

			if ($current["level"] > $this->Level) {
				return false;
			}

			// If the user is editing themselves, they can't change the level.
			if ($this->ID == $current["id"]) {
				$level = $current["level"];
			}

			// Don't allow the level to be set higher than the logged in user's level
			if ($level > $this->Level) {
				$level = $this->Level;
			}

			$update = [
				"level" => $level,
				"email" => BigTree::safeEncode($data["email"]),
				"name" => BigTree::safeEncode($data["name"]),
				"company" => BigTree::safeEncode($data["company"]),
				"daily_digest" => !empty($data["daily_digest"]) ? "on" : "",
				"permissions" => is_array($data["permissions"]) ? $data["permissions"] : [],
				"alerts" => is_array($data["alerts"]) ? $data["alerts"] : [],
				"timezone" => $data["timezone"] ?: ""
			];

			if ($data["password"]) {
				$update["password"] = password_hash(trim($data["password"]), PASSWORD_DEFAULT);
				$update["new_hash"] = "on";

				// Clean existing sessions on password change
				SQL::delete("bigtree_sessions", ["logged_in_user" => $id]);
				SQL::delete("bigtree_user_sessions", ["email" => $current["email"]]);
			}

			SQL::update("bigtree_users", $id, $update);
			$this->track("bigtree_users", $id, "updated");

			return true;
		}

		/*
			Function: updateUserPassword
				Updates a user's password.

			Parameters:
				id - The user's id.
				password - The new password.
		*/

		public static function updateUserPassword($id,$password) {
			SQL::update("bigtree_users", $id, [
				"password" => password_hash(trim($password), PASSWORD_DEFAULT),
				"new_hash" => "on"
			]);

			// Clean existing sessions
			$email = SQL::fetchSingle("SELECT email FROM bigtree_users WHERE id = ?", $id);
			SQL::delete("bigtree_sessions", ["logged_in_user" => $id]);
			SQL::delete("bigtree_user_sessions", ["email" => $email]);
		}

		/*
			Function: validatePassword
				Validates a password against the security policy.

			Parameters:
				password - Password to validate.

			Returns:
				true if it passes all password criteria.
		*/

		public static function validatePassword($password) {
			global $bigtree;

			$policy = $bigtree["security-policy"]["password"];
			$failed = false;

			// Check length policy
			if ($policy["length"] && strlen($password) < $policy["length"]) {
				$failed = true;
			}
			// Check case policy
			if ($policy["multicase"] && strtolower($password) === $password) {
				$failed = true;
			}
			// Check numeric policy
			if ($policy["numbers"] && !preg_match("/[0-9]/",$password)) {
				$failed = true;
			}
			// Check non-alphanumeric policy
			if ($policy["nonalphanumeric"] && ctype_alnum($password)) {
				$failed = true;
			}
			return !$failed;
		}

		/*
			Function: verifyCSRFToken
				Verifies the referring host and session token and stops processing if they fail.
		*/

		public function verifyCSRFToken() {
			$clean_referer = str_replace(array("http://","https://"),"//",$_SERVER["HTTP_REFERER"]);
			$clean_domain = str_replace(array("http://","https://"),"//",DOMAIN);
			$token = isset($_POST[$this->CSRFTokenField]) ? $_POST[$this->CSRFTokenField] : $_GET[$this->CSRFTokenField];

			if (strpos($clean_referer, $clean_domain) !== 0 || $token != $this->CSRFToken) {
				// See if this is a timeout and an existing token exists in the database for another session
				$q = sqlquery("SELECT * FROM bigtree_user_sessions WHERE email = '".sqlescape($this->User)."'");

				while ($old_session = sqlfetch($q)) {
					$token = isset($_POST[$old_session["csrf_token_field"]]) ? $_POST[$old_session["csrf_token_field"]] : $_GET[$old_session["csrf_token_field"]];

					if ($token && $token == $old_session["csrf_token"]) {
						return;
					}
				}

				$this->stop("An error has occurred. Please try your submission again.");
			}
		}

		/*
			Function: verifyLogin2FA
				Verifies a username and password and returns the two factor auth secret.

			Parameters:
				email - Email address
				password - Password

			Returns:
				The two factor auth secret for the user or null if login failed.
		*/

		public static function verifyLogin2FA($email, $password) {
			global $bigtree;

			$ip = ip2long(BigTree::remoteIP());

			if (static::isIPBanned($ip)) {
				return null;
			}

			// Get rid of whitespace and make the email lowercase for consistency
			$email = trim(strtolower($email));
			$password = trim($password);
			$user = SQL::fetch("SELECT * FROM bigtree_users WHERE LOWER(email) = ?", $email);

			if ($user) {
				if (static::isUserBanned($user["id"])) {
					return null;
				}

				// BigTree 4.3+ uses password_hash instead of PHPass
				if ($user["new_hash"]) {
					$validated = password_verify($password, $user["password"]);

					// If the latest algorithm is available and needs a rehash, update it now
					if ($validated && password_needs_rehash($user["password"], PASSWORD_DEFAULT)) {
						SQL::update("bigtree_users", $user["id"], ["password" => password_hash($password, PASSWORD_DEFAULT)]);
					}
				} else {
					$phpass = new PasswordHash($bigtree["config"]["password_depth"], true);
					$validated = $phpass->CheckPassword($password, $user["password"]);

					// Switch to password_hash
					if ($validated) {
						SQL::update("bigtree_users", $user["id"], [
							"password" => password_hash($password, PASSWORD_DEFAULT),
							"new_hash" => "on"
						]);
					}
				}

				if ($validated) {
					$token = $phpass->HashPassword(BigTree::randomString(64).trim($password).BigTree::randomString(64));
					$_SESSION["bigtree_admin"]["2fa_id"] = intval($user["id"]);
					$_SESSION["bigtree_admin"]["2fa_login_token"] = $token;

					SQL::update("bigtree_users", $user["id"], ["2fa_login_token" => $token]);

					return $user["2fa_secret"];
				}
			}

			return null;
		}

		/*
			Function: versionToDecimal
				Returns a decimal number of a BigTree version for numeric comparisons.

			Parameters:
				version - BigTree version number (i.e. 4.2.0)

			Returns:
				A number
		*/

		public static function versionToDecimal($version) {
			$pieces = explode(".",$version);
			$number = $pieces[0] * 10000;
			if (isset($pieces[1])) {
				$number += $pieces[1] * 100;
			}
			if (isset($pieces[2])) {
				$number += $pieces[2];
			}
			return $number;
		}
	}
