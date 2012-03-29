<?
	/*
		Class: BigTreeAdmin
			The main class used by the admin for manipulating and retrieving data.
	*/

	class BigTreeAdmin {

		var $PerPage = 15;

		// !View Types
		var $ViewTypes = array(
			"searchable" => "Searchable List",
			"draggable" => "Draggable List",
			"images" => "Image List",
			"grouped" => "Grouped List",
			"images-grouped" => "Grouped Image List"
		);

		// !Reserved Column Names
		var $ReservedColumns = array(
			"id",
			"position",
			"archived",
			"approved"
		);

		// !View Actions
		var $ViewActions = array(
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
		
		/*
			Constructor:
				Initializes the user's permissions.
		*/
		
		function __construct() {
			if (isset($_SESSION["bigtree"]["email"])) {
				$this->ID = $_SESSION["bigtree"]["id"];
				$this->User = $_SESSION["bigtree"]["email"];
				$this->Level = $_SESSION["bigtree"]["level"];
				$this->Name = $_SESSION["bigtree"]["name"];
				$this->Permissions = $_SESSION["bigtree"]["permissions"];
			} elseif (isset($_COOKIE["bigtree"]["email"])) {
				$user = mysql_escape_string($_COOKIE["bigtree"]["email"]);
				$pass = mysql_escape_string($_COOKIE["bigtree"]["password"]);
				$f = sqlfetch(sqlquery("SELECT * FROM bigtree_users WHERE email = '$user' AND password = '$pass'"));
				if ($f) {
					$this->ID = $f["id"];
					$this->User = $user;
					$this->Level = $f["level"];
					$this->Name = $f["name"];
					$this->Permissions = json_decode($f["permissions"],true);
					$_SESSION["bigtree"]["id"] = $f["id"];
					$_SESSION["bigtree"]["email"] = $f["email"];
					$_SESSION["bigtree"]["level"] = $f["level"];
					$_SESSION["bigtree"]["name"] = $f["name"];
					$_SESSION["bigtree"]["permissions"] = $this->Permissions;
				}
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

		function archivePage($page) {
			global $cms;
			
			if (is_array($page)) {
				$page = mysql_real_escape_string($page["id"]);
			} else {
				$page = mysql_real_escape_string($page);
			}

			$access = $this->getPageAccessLevel($page);
			if ($access == "p" && $this->canModifyChildren($cms->getPage($page))) {
				sqlquery("UPDATE bigtree_pages SET archived = 'on' WHERE id = '$page'");
				$this->archivePageChildren($page);
				$this->growl("Pages","Archived Page");
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

		function archivePageChildren($page) {
			$page = mysql_real_escape_string($page);
			$q = sqlquery("SELECT * FROM bigtree_pages WHERE parent = '$page'");
			while ($f = sqlfetch($q)) {
				if (!$f["archived"]) {
					sqlquery("UPDATE bigtree_pages SET archived = 'on', archived_inherited = 'on' WHERE id = '".$f["id"]."'");
					$this->track("bigtree_pages",$f["id"],"archived");
					$this->archivePageChildren($f["id"]);
				}
			}
		}

		/*
			Function: autoIPL
				Automatically converts links to internal page links.
			
			Parameters:
				html - A string of contents that may contain URLs
			
			Returns:
				A string with hard links converted into internal page links.
		*/

		function autoIPL($html) {
			// If this string is actually just a URL, IPL it.
			if (substr($html,0,7) == "http://" || substr($html,0,8) == "https://") {
				$html = $this->makeIPL($html);
			// Otherwise, switch all the image srcs and javascripts srcs and whatnot to {wwwroot}.
			} else {
				$html = preg_replace_callback('/href="([^"]*)"/',create_function('$matches','
					global $cms;
					$href = str_replace("{wwwroot}",$GLOBALS["www_root"],$matches[1]);
					if (strpos($href,$GLOBALS["www_root"]) !== false) {
						$command = explode("/",rtrim(str_replace($GLOBALS["www_root"],"",$href),"/"));
						list($navid,$commands) = $cms->getNavId($command);
						$page = $cms->getPage($navid,false);
						if ($navid && (!$commands[0] || substr($page["template"],0,6) == "module" || substr($commands[0],0,1) == "#")) {
							$href = "ipl://".$navid."//".base64_encode(json_encode($commands));
						}
					}
					$href = str_replace($GLOBALS["www_root"],"{wwwroot}",$href);
					return \'href="\'.$href.\'"\';'
				),$html);
				$html = str_replace($GLOBALS["www_root"],"{wwwroot}",$html);
			}
			return $html;
		}
		
		/*
			Function: canAccessGroup
				Returns whether or not the logged in user can access a module group.
				Utility for form field types / views -- we already know module group permissions are enabled so we skip some overhead
		
			Parameters:
				module - A module entry.
				group - A group id.
			
			Returns:
				true if the user can access this group, otherwise false.
		*/
			
		function canAccessGroup($module,$group) {
			if ($this->Level > 0) {
				return true;
			}

			$id = $module["id"];

			if ($this->Permissions["module"][$id] && $this->Permissions["module"][$id] != "n") {
				return true;
			}

			if (is_array($this->Permissions["module_gbp"][$id])) {
				$gp = $this->Permissions["module_gbp"][$id][$group];
				if ($gp && $gp != "n") {
					return true;
				}
			}

			return false;
		}
		
		/*
			Function: canModifyChildren
				Checks whether the logged in user can modify all child pages or a page.
				Assumes we already know that we're a publisher of the parent.
			
			Parameters:
				page - The page entry to check children for.
			
			Returns:
				true if the user can modify all the page children, otherwise false.
		*/
		
		function canModifyChildren($page) {
			if ($this->Level > 0) {
				return true;
			}
			
			$q = sqlquery("SELECT id FROM bigtree_pages WHERE path LIKE '".mysql_real_escape_string($page["path"])."%'");
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

			Paramters:
				hash - The unique hash generated by <forgotPassword>.
				password - The user's new password.

			See Also:
				<forgotPassword>

		*/

		function changePassword($hash,$password) {
			global $config;

			$hash = mysql_real_escape_string($hash);
			$user = sqlfetch(sqlquery("SELECT * FROM bigtree_users WHERE change_password_hash = '$hash'"));

			$phpass = new PasswordHash($config["password_depth"], TRUE);
			$password = mysql_real_escape_string($phpass->HashPassword($password));

			sqlquery("UPDATE bigtree_users SET password = '$password', change_password_hash = '' WHERE id = '".$user["id"]."'");
			header("Location: ".$GLOBALS["admin_root"]."login/reset-success/");
			die();
		}
		
		/*
			Function: checkAccess
				Determines whether the logged in user has access to a module or not.
			
			Parameters:
				module - Either a module id or module entry.
			
			Returns:
				true if the user can access the module, otherwise false.
		*/
		
		function checkAccess($module) {
			if (is_array($module)) {
				$module = $module["id"];
			}

			if ($this->Level > 0) {
				return true;
			}

			if ($this->Permissions["module"][$module] && $this->Permissions["module"][$module] != "n") {
				return true;
			}

			if (is_array($this->Permissions["module_gbp"][$module])) {
				foreach ($this->Permissions["module_gbp"][$module] as $p) {
					if ($p != "n") {
						return true;
					}
				}
			}

			return false;
		}
		
		/*
			Function: checkHTML
				Checks a block of HTML for broken links/images
			
			Parameters:
				relative_path - The starting path of the page containing the HTML (so that relative links, i.e. "good/" know where to begin)
				html - A string of HTML
				external - Whether to check external links (slow) or not
		
			Returns:
				An array of errors.
		*/

		function checkHTML($relative_path,$html,$external = false) {
			if (!$html) {
				return array();
			}
			$errors = array();
			$doc = new DOMDocument();
			$doc->loadHTML($html);
			// Check A tags.
			$links = $doc->getElementsByTagName("a");
			foreach ($links as $link) {
				$href = $link->getAttribute("href");
				$href = str_replace(array("{wwwroot}","%7Bwwwroot%7D"),$GLOBALS["www_root"],$href);
				if (substr($href,0,4) == "http" && strpos($href,$GLOBALS["www_root"]) === false) {
					// External link, not much we can do but alert that it's dead
					if ($external) {
						if (strpos($href,"#") !== false)
							$href = substr($href,0,strpos($href,"#")-1);
						if (!$this->urlExists($href)) {
							$errors["a"][] = $href;
						}
					}
				} elseif (substr($href,0,6) == "ipl://") {
					if (!$this->iplExists($href)) {
						$errors["a"][] = $href;
					}
				} elseif (substr($href,0,7) == "mailto:" || substr($href,0,1) == "#" || substr($href,0,5) == "data:") {
					// Don't do anything, it's a page mark, data URI, or email address
				} elseif (substr($href,0,4) == "http") {
					// It's a local hard link
					if (!$this->urlExists($href)) {
						$errors["a"][] = $href;
					}
				} else {
					// Local file.
					$local = $relative_path.$href;
					if (!$this->urlExists($local)) {
						$errors["a"][] = $local;
					}
				}
			}
			// Check IMG tags.
			$images = $doc->getElementsByTagName("img");
			foreach ($images as $image) {
				$href = $image->getAttribute("src");
				$href = str_replace(array("{wwwroot}","%7Bwwwroot%7D"),$GLOBALS["www_root"],$href);
				if (substr($href,0,4) == "http" && strpos($href,$GLOBALS["www_root"]) === false) {
					// External link, not much we can do but alert that it's dead
					if ($external) {
						if (!$this->urlExists($href)) {
							$errors["img"][] = $href;
						}
					}
				} elseif (substr($href,0,6) == "ipl://") {
					if (!$this->iplExists($href)) {
						$errors["a"][] = $href;
					}
				} elseif (substr($href,0,5) == "data:") {
					// Do nothing, it's a data URI
				} elseif (substr($href,0,4) == "http") {
					// It's a local hard link
					if (!$this->urlExists($href)) {
						$errors["img"][] = $href;
					}
				} else {
					// Local file.
					$local = $relative_path.$href;
					if (!$this->urlExists($local)) {
						$errors["img"][] = $local;
					}
				}
			}
			return array($errors);
		}
		
		/*
			Function: clearCache
				Removes all files in the cache directory.
		*/

		function clearCache() {
			$d = opendir($GLOBALS["server_root"]."cache/");
			while ($f = readdir($d)) {
				if ($f != "." && $f != ".." && !is_dir($GLOBALS["server_root"]."cache/".$f)) {
					unlink($GLOBALS["server_root"]."cache/".$f);
				}
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
		*/
		
		function createCallout($id,$name,$description,$level,$resources) {
			// If we're creating a new file, let's populate it with some convenience things to show what resources are available.
			$file_contents = '<?
	/*
		Resources Available:
';			
			
			$cached_types = $this->getCachedFieldTypes();
			$types = $cached_types["callout"];
			
			$clean_resources = array();
			foreach ($resources as $resource) {
				if ($resource["id"] && $resource["id"] != "type") {
					$options = json_decode($resource["options"],true);
					foreach ($options as $key => $val) {
						if ($key != "name" && $key != "id" && $key != "type") {
							$resource[$key] = $val;
						}
					}
					
					$file_contents .= '		$'.$resource["id"].' = '.$resource["name"].' - '.$types[$resource["type"]]."\n";
					
					$resource["id"] = htmlspecialchars($resource["id"]);
					$resource["name"] = htmlspecialchars($resource["name"]);
					$resource["subtitle"] = htmlspecialchars($resource["subtitle"]);
					unset($resource["options"]);
					$clean_resources[] = $resource;
				}
			}
			
			$file_contents .= '	*/
?>';		
			
			// Clean up the post variables
			$id = mysql_real_escape_string(htmlspecialchars($id));
			$name = mysql_real_escape_string(htmlspecialchars($name));
			$description = mysql_real_escape_string(htmlspecialchars($description));
			$level = mysql_real_escape_string($level);
			$resources = mysql_real_escape_string(json_encode($clean_resources));
			
			if (!file_exists($GLOBALS["server_root"]."templates/callouts/".$id.".php")) {
				file_put_contents($GLOBALS["server_root"]."templates/callouts/".$id.".php",$file_contents);
				chmod($GLOBALS["server_root"]."templates/callouts/".$id.".php",0777);
			}
			
			sqlquery("INSERT INTO bigtree_callouts (`id`,`name`,`description`,`resources`,`level`) VALUES ('$id','$name','$description','$resources','$level')");
		}
		
		/*
			Function: createFeed
				Creates a feed.
			
			Parameters:
				name - The name.
				description - The description.
				table - The data table.
				type - The feed type.
				options - The feed type options.
				fields - The fields.
			
			Returns:
				The route to the new feed.
		*/
		
		function createFeed($name,$description,$table,$type,$options,$fields) {
			global $cms;
			
			// Options were encoded before submitting the form, so let's get them back.
			$options = json_decode($options,true);
			if (is_array($options)) {
				foreach ($options as &$option) {
					$option = str_replace($www_root,"{wwwroot}",$option);
				}
			}
			
			// Get a unique route!
			$route = $cms->urlify($name);
			$x = 2;
			$oroute = $route;
			$f = $cms->getFeedByRoute($route);
			while ($f) {
				$route = $oroute."-".$x;
				$f = $cms->getFeedByRoute($route);
				$x++;
			}
			
			// Fix stuff up for the db.
			$name = mysql_real_escape_string(htmlspecialchars($name));
			$description = mysql_real_escape_string(htmlspecialchars($description));
			$table = mysql_real_escape_string($table);
			$type = mysql_real_escape_string($type);
			$options = mysql_real_escape_string(json_encode($options));
			$fields = mysql_real_escape_string(json_encode($fields));
			$route = mysql_real_escape_string($route);
			
			sqlquery("INSERT INTO bigtree_feeds (`route`,`name`,`description`,`type`,`table`,`fields`,`options`) VALUES ('$route','$name','$description','$type','$table','$fields','$options')");
			
			return $route;
		}
		
		/*
			Function: createFieldType
				Creates a field type and its files.
			
			Parameters:
				id - The id of the field type.
				name - The name.
				pages - Whether it can be used as a page resource or not ("on" is yes)
				modules - Whether it can be used as a module resource or not ("on" is yes)
				callouts - Whether it can be used as a callout resource or not ("on" is yes)
		*/
		
		function createFieldType($id,$name,$pages,$modules,$callouts) {
			$id = mysql_real_escape_string($id);
			$name = mysql_real_escape_string(htmlspecialchars($name));
			$author = mysql_real_escape_string($this->Name);
			$pages = mysql_real_escape_string($pages);
			$modules = mysql_real_escape_string($modules);
			$callouts = mysql_real_escape_string($callouts);
			
			$file = "$id.php";
			
			sqlquery("INSERT INTO bigtree_field_types (`id`,`name`,`pages`,`modules`,`callouts`) VALUES ('$id','$name','$pages','$modules','$callouts')");
			
			// Make the files for draw and process and options if they don't exist.
			if (!file_exists($GLOBALS["server_root"]."custom/admin/form-field-types/draw/$file")) {
				BigTree::touchFile($GLOBALS["server_root"]."custom/admin/form-field-types/draw/$file");
				file_put_contents($GLOBALS["server_root"]."custom/admin/form-field-types/draw/$file",'<? include BigTree::path("admin/form-field-types/draw/text.php"); ?>');
				chmod($GLOBALS["server_root"]."custom/admin/form-field-types/draw/$file",0777);
			}
			if (!file_exists($GLOBALS["server_root"]."custom/admin/form-field-types/process/$file")) {
				BigTree::touchFile($GLOBALS["server_root"]."custom/admin/form-field-types/process/$file");
				file_put_contents($GLOBALS["server_root"]."custom/admin/form-field-types/process/$file",'<? $value = $data[$key]; ?>');
				chmod($GLOBALS["server_root"]."custom/admin/form-field-types/process/$file",0777);
			}
			if (!file_exists($GLOBALS["server_root"]."custom/admin/ajax/developer/field-options/$file")) {
				BigTree::touchFile($GLOBALS["server_root"]."custom/admin/ajax/developer/field-options/$file");
				chmod($GLOBALS["server_root"]."custom/admin/ajax/developer/field-options/$file",0777);
			}
				
			unlink($GLOBALS["server_root"]."cache/form-field-types.btc");
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
		
		function createMessage($subject,$message,$recipients,$in_response_to = 0) {
			// Clear tags out of the subject, sanitize the message body of XSS attacks.
			$subject = mysql_real_escape_string(htmlspecialchars(strip_tags($subject)));
			$message = mysql_real_escape_string(strip_tags($message,"<p><b><strong><em><i><a>"));
			$in_response_to = mysql_real_escape_string($in_response_to);
			
			// We build the send_to field this way so that we don't have to create a second table of recipients.
			// Is it faster database wise using a LIKE over a JOIN? I don't know, but it makes for one less table.
			$send_to = "|";
			foreach ($recipients as $r) {
				// Make sure they actually put in a number and didn't try to screw with the $_POST
				$send_to .= intval($r)."|";
			}
			
			$send_to = mysql_real_escape_string($send_to);
			
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
			
			Returns:
				The new module id.
		*/
		
		function createModule($name,$group,$class,$table,$permissions) {
			global $cms;
			
			// Find an available module route.
			$route = $cms->urlify($name);
			
			// Go through the hard coded modules
			$existing = array();
			$d = opendir($GLOBALS["server_root"]."core/admin/modules/");
			while ($f = readdir($d)) {
				if ($f != "." && $f != "..") {
					$existing[] = $f;
				}
			}
			// Go through the directories (really ajax, css, images, js)
			$d = opendir($GLOBALS["server_root"]."core/admin/");
			while ($f = readdir($d)) {
				if ($f != "." && $f != "..") {
					$existing[] = $f;
				}
			}
			// Go through the hard coded pages
			$d = opendir($GLOBALS["server_root"]."core/admin/pages/");
			while ($f = readdir($d)) {
				if ($f != "." && $f != "..") {
					// Drop the .php
					$existing[] = substr($f,0,-4);
				}
			}
			// Go through already created modules
			$q = sqlquery("SELECT route FROM bigtree_modules");
			while ($f = sqlfetch($q)) {
				$existing[] = $f["route"];
			}
			
			// Get a unique route
			$x = 2;
			$oroute = $route;
			while (in_array($route,$existing)) {
				$route = $oroute."-".$x;
				$x++;
			}
			
			$name = mysql_real_escape_string(htmlspecialchars($name));
			$route = mysql_real_escape_string($route);
			$class = mysql_real_escape_string($class);
			$group = mysql_real_escape_string($group);
			$gbp = mysql_real_escape_string(json_encode($permissions));
			
			sqlquery("INSERT INTO bigtree_modules (`name`,`route`,`class`,`group`,`gbp`) VALUES ('$name','$route','$class','$group','$gbp')");
			$id = sqlid();
			
			if ($class) {
				// Create class module.
				$f = fopen($GLOBALS["server_root"]."custom/inc/modules/$route.php","w");
				fwrite($f,"<?\n");
				fwrite($f,"	class $class extends BigTreeModule {\n");
				fwrite($f,"\n");
				fwrite($f,'		var $Table = "'.$table.'";'."\n");
				fwrite($f,'		var $Module = "'.$id.'";'."\n");
				fwrite($f,"	}\n");
				fwrite($f,"?>\n");
				fclose($f);
				chmod($GLOBALS["server_root"]."custom/inc/modules/$route.php",0777);
				
				// Remove cached class list.
				unlink($GLOBALS["server_root"]."cache/module-class-list.btc");
			}
			
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
				form - Optional auto module form id.
				view - Optional auto module view id.
		*/
		
		function createModuleAction($module,$name,$route,$in_nav,$icon,$form = 0,$view = 0) {
			$module = mysql_real_escape_string($module);
			$route = mysql_real_escape_string(htmlspecialchars($route));
			$in_nav = mysql_real_escape_string($in_nav);
			$icon = mysql_real_escape_string($icon);
			$name = mysql_real_escape_string(htmlspecialchars($name));
			$form = mysql_real_escape_string($form);
			$view = mysql_real_escape_string($view);
		
			$oroute = $route;
			$x = 2;
			while ($f = sqlfetch(sqlquery("SELECT * FROM bigtree_module_actions WHERE module = '$module' AND route = '$route'"))) {
				$route = $oroute."-".$x;
				$x++;
			}
			
			sqlquery("INSERT INTO bigtree_module_actions (`module`,`name`,`route`,`in_nav`,`class`,`form`,`view`) VALUES ('$module','$name','$route','$in_nav','$icon','$form','$view')");
		}
		
		/*
			Function: createModuleForm
				Creates a module form.
			
			Parameters:
				title - The title of the form.
				table - The table for the form data.
				fields - The form fields.
				javascript - Optional Javascript file to include in the form.
				css - Optional CSS file to include in the form.
				callback - Optional callback function to run after the form processes.
				default_position - Default position for entries to the form (if the view is positioned).
				
			Returns:
				The new form id.
		*/
		
		function createModuleForm($title,$table,$fields,$javascript = "",$css = "",$callback = "",$default_position = "") {
			$title = mysql_real_escape_string(htmlspecialchars($title));
			$table = mysql_real_escape_string($table);
			$fields = mysql_real_escape_string(json_encode($fields));
			$javascript - mysql_real_escape_string(htmlspecialchars($javascript));
			$css - mysql_real_escape_string(htmlspecialchars($css));
			$callback - mysql_real_escape_string($callback);
			$default_position - mysql_real_escape_string($default_position);
			
			sqlquery("INSERT INTO bigtree_module_forms (`title`,`table`,`fields`,`javascript`,`css`,`callback`,`default_position`) VALUES ('$title','$table','$fields','$javascript','$css','$callback','$default_position')");
			return sqlid();
		}
		
		/*
			Function: createModuleGroup
				Creates a module group.
			
			Parameters:
				name - The name of the group.
				package - The (optional) package id the group originated from.
			
			Returns:
				The id of the newly created group.
		*/
		
		function createModuleGroup($name,$in_nav,$package = 0) {
			global $cms;
			
			$name = mysql_real_escape_string($name);
			$packge = mysql_real_escape_string($package);
			
			// Get a unique route
			$x = 2;
			$route = $cms->urlify($name);
			$oroute = $route;
			while ($this->getModuleGroupByRoute($route)) {
				$route = $oroute."-".$x;
				$x++;			
			}
			
			// Just to be safe
			$route = mysql_real_escape_string($route);
			
			sqlquery("INSERT INTO bigtree_module_groups (`name`,`route`,`in_nav`,`package`) VALUES ('$name','$route','$in_nav','$package')");
			return sqlid();
		}
		
		/*
			Function: createModuleView
				Creates a module view.
			
			Parameters:
				title - View title.
				description - Description.
				table - Data table.
				type - View type.
				options - View options array.
				fields - Field array.
				actions - Actions array.
				suffix - Add/Edit suffix.
				uncached - Don't cache the view.
				preview_url - Optional preview URL.
				
			Returns:
				The id for view.
		*/
		
		function createModuleView($title,$description,$table,$type,$options,$fields,$actions,$suffix,$uncached = "",$preview_url = "") {
			$title = mysql_real_escape_string(htmlspecialchars($title));
			$description = mysql_real_escape_string(htmlspecialchars($description));
			$table = mysql_real_escape_string($table);
			$type = mysql_real_escape_string($type);
			$options = mysql_real_escape_string(json_encode($options));
			$fields = mysql_real_escape_string(json_encode($fields));
			$actions = mysql_real_escape_string(json_encode($actions));
			$suffix = mysql_real_escape_string($suffix);
			$uncached = mysql_real_escape_string($uncached);
			$preview_url = mysql_real_escape_string(htmlspecialchars($preview_url));
			
			sqlquery("INSERT INTO bigtree_module_views (`title`,`description`,`type`,`fields`,`actions`,`table`,`options`,`suffix`,`uncached`,`preview_url`) VALUES ('$title','$description','$type','$fields','$actions','$table','$options','$suffix','$uncached','$preview_url')");
			
			return sqlid();
		}
		
		/*
			Function: createPage
				Creates a page.
				Does not check permissions.
			
			Parameters:
				data - An array of page information.
			
			Returns:
				The id of the newly created page.
		*/

		function createPage($data) {
			global $cms;
			
			// Loop through the posted data, make sure no session hijacking is done.
			foreach ($data as $key => $val) {
				if (substr($key,0,1) != "_") {
					if (is_array($val)) {
						$$key = mysql_real_escape_string(json_encode($val));
					} else {
						$$key = mysql_real_escape_string($val);
					}
				}
			}
			
			// If there's an external link, make sure it's a relative URL
			if ($external) {
				$external = $this->makeIPL($external);
			}
			
			
			// Who knows what they may have put in for a route, so we're not going to use the mysql_real_escape_string version.
			$route = $data["route"];
			if (!$route) {
				// If they didn't specify a route use the navigation title
				$route = $cms->urlify($data["nav_title"]);
			} else {
				// Otherwise sanitize the one they did provide.
				$route = $cms->urlify($route);
			}
			
			// We need to figure out a unique route for the page.  Make sure it doesn't match a directory in /site/
			$original_route = $route;
			$x = 2;
			// Reserved paths.
			if ($parent == 0) {
				while (file_exists($GLOBALS["server_root"]."site/".$route."/")) {
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
			
			// If we have a parent, get the full navigation path, otherwise, just use this route as the path since it's top level.
			if ($parent) {
				$path = $this->getFullNavigationPath($parent)."/".$route;
			} else {
				$path = $route;
			}
			
			// If we set a publish at date, make it the proper MySQL format.
			if ($publish_at) {
				$publish_at = "'".date("Y-m-d",strtotime($publish_at))."'";
			} else {
				$publish_at = "NULL";
			}

			// If we set an expiration date, make it the proper MySQL format.
			if ($expire_at) {
				$expire_at = "'".date("Y-m-d",strtotime($expire_at))."'";
			} else {
				$expire_at = "NULL";
			}
			
			// Make the title, navigation title, description, keywords, and external link htmlspecialchar'd -- these are all things we'll be echoing in the HTML so we might as well make them valid now instead of at display time.
			
			$title = htmlspecialchars($title);
			$nav_title = htmlspecialchars($nav_title);
			$meta_description = htmlspecialchars($meta_description);
			$meta_keywords = htmlspecialchars($meta_keywords);
			$external = htmlspecialchars($external);

			// Make the page!
			sqlquery("INSERT INTO bigtree_pages (`parent`,`nav_title`,`route`,`path`,`in_nav`,`title`,`template`,`external`,`new_window`,`resources`,`callouts`,`meta_keywords`,`meta_description`,`last_edited_by`,`created_at`,`updated_at`,`publish_at`,`expire_at`,`max_age`) VALUES ('$parent','$nav_title','$route','$path','$in_nav','$title','$template','$external','$new_window','$resources','$callouts','$meta_keywords','$meta_description','".$this->ID."',NOW(),NOW(),$publish_at,$expire_at,'$max_age')");

			$id = sqlid();

			// Handle tags
			if (is_array($data["_tags"])) {
				foreach ($data["_tags"] as $tag) {
					sqlquery("INSERT INTO bigtree_tags_rel (`module`,`entry`,`tag`) VALUES ('0','$id','$tag')");
				}
			}

			// If there was an old page that had previously used this path, dump its history so we can take over the path.
			sqlquery("DELETE FROM bigtree_route_history WHERE old_route = '$path'");
			
			// Dump the cache, we don't really know how many pages may be showing this now in their nav.
			$this->clearCache();
			// Let search engines know this page now exists.
			$this->pingSearchEngines();
			// Audit trail.
			$this->track("bigtree_pages",$id,"created");

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
		
		function createPendingChange($table,$item_id,$changes,$mtm_changes = array(),$tags_changes = array(),$module = 0) {
			$table = mysql_real_escape_string($table);
			$item_id = mysql_real_escape_string($item_id);
			$changes = mysql_real_escape_string(json_encode($changes));
			$mtm_changes = mysql_real_escape_string(json_encode($mtm_changes));
			$tags_changes = mysql_real_escape_string(json_encode($tags_changes));
			$module = mysql_real_escape_string($module);
			
			sqlquery("INSERT INTO bigtree_pending_changes (`user`,`date`,`table`,`item_id`,`changes`,`mtm_changes`,`tags_changes`,`module`) VALUES ('".$this->ID."',NOW(),'$table','$item_id','$changes','$mtm_changes','$tags_changes','$module')");
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

		function createPendingPage($data) {
			global $cms;
			
			// Make a relative URL for external links.
			if ($data["external"]) {
				$data["external"] = $this->makeIPL($data["external"]);
			}
			
			// Save the tags, then dump them from the saved changes array.
			$tags = mysql_real_escape_string(json_encode($data["_tags"]));
			unset($data["_tags"]);
			
			// Make the nav title, title, external link, keywords, and description htmlspecialchar'd for displaying on the front end / the form again.
			$data["nav_title"] = htmlspecialchars($data["nav_title"]);
			$data["title"] = htmlspecialchars($data["title"]);
			$data["external"] = htmlspecialchars($data["external"]);
			$data["meta_keywords"] = htmlspecialchars($data["meta_keywords"]);
			$data["meta_description"] = htmlspecialchars($data["meta_description"]);
			
			$parent = mysql_real_escape_string($data["parent"]);

			// JSON encode the changes and stick them in the database.
			unset($data["MAX_FILE_SIZE"]);
			unset($data["ptype"]);
			$data = mysql_real_escape_string(json_encode($data));
			
			sqlquery("INSERT INTO bigtree_pending_changes (`user`,`date`,`title`,`table`,`changes`,`tags_changes`,`type`,`module`,`pending_page_parent`) VALUES ('".$this->ID."',NOW(),'New Page Created','bigtree_pages','$data','$tags','NEW','','$parent')");
			$id = sqlid();
			
			// Audit trail
			$this->track("bigtree_pages","p$id","created-pending");

			return $id;
		}
		
		/*
			Function: createResource
				Creates a resource.
			
			Parameters:
				folder - The folder to place it in.
				file - The file path.
				name - The file name.
				type - The file type.
				is_image - Whether the resource is an image.
				height - The image height (if it's an image).
				width - The image width (if it's an image).
				thumbs - An array of thumbnails (if it's an image).
				list_thumb_margin - The margin for the list thumbnail (if it's an image).
			
			Returns:
				The new resource id.
		*/
		
		function createResource($folder,$file,$name,$type,$is_image = "",$height = 0,$width = 0,$thumbs = array(),$list_thumb_margin = 0) {
			$folder = mysql_real_escape_string($folder);
			$file = mysql_real_escape_string($file);
			$name = mysql_real_escape_string(htmlspecialchars($name));
			$type = mysql_real_escape_string($type);
			$is_image = mysql_real_escape_string($is_image);
			$height = intval($height);
			$width = intval($width);
			$thumbs = mysql_real_escape_string(json_encode($thumbs));
			$list_thumb_margin = intval($list_thumb_margin);
			
			sqlquery("INSERT INTO bigtree_resources (`file`,`date`,`name`,`type`,`folder`,`is_image`,`height`,`width`,`thumbs`,`list_thumb_margin`) VALUES ('$file',NOW(),'$name','$type','$folder','$is_image','$height','$width','$thumbs','$list_thumb_margin')");	
			return sqlid();
		}
		
		/*
			Function: createResourceFolder
				Creates a resource folder.
				Checks permissions.
			
			Paremeters:
				parent - The parent folder.
				name - The name of the new folder.
			
			Returns:
				The new folder id.
		*/
		
		function createResourceFolder($parent,$name) {
			$perm = $this->getResourceFolderPermission($parent);
			if ($perm != "p") {
				die("You don't have permission to make a folder here.");
			}
			
			$parent = mysql_real_escape_string($parent);
			$name = mysql_real_escape_string(htmlspecialchars($name));
			
			sqlquery("INSERT INTO bigtree_resource_folders (`name`,`parent`) VALUES ('$name','$parent')");
			return sqlid();
		}
		
		/*
			Function: createSetting
				Creates a setting.

			Parameters:
				data - An array of settings information. Available fields: "id", "name", "description", "type", "locked", "module", "encrypted", "system"

			Returns:
				True if successful, false if a setting already exists with the ID given.
		*/

		function createSetting($data) {
			// Avoid _SESSION hijacking.
			foreach ($data as $key => $val) {
				if (substr($key,0,1) != "_" && !is_array($val)) {
					$$key = mysql_real_escape_string(htmlspecialchars($val));
				}
			}
			
			// We don't want this encoded since it's a WYSIWYG field.
			$description = mysql_real_escape_string($data["description"]);

			// See if there's already a setting with this ID
			$r = sqlrows(sqlquery("SELECT id FROM bigtree_settings WHERE id = '$id'"));
			if ($r) {
				return false;
			}

			sqlquery("INSERT INTO bigtree_settings (`id`,`name`,`description`,`type`,`locked`,`encrypted`,`system`) VALUES ('$id','$name','$description','$type','$locked','$encrypted','$system')");
			// Audit trail.
			$this->track("bigtree_settings",$id,"created");

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
		
		function createTag($tag) {
			$tag = strtolower(html_entity_decode($tag));
			// Check if the tag exists already.
			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_tags WHERE tag = '".mysql_real_escape_string($tag)."'"));
			
			if (!$f) {
				$meta = metaphone($tag);
				$route = $cms->urlify($tag);
				$oroute = $route;
				$x = 2;
				while ($f = sqlfetch(sqlquery("SELECT * FROM bigtree_tags WHERE route = '$route'"))) {
					$route = $oroute."-".$x;
					$x++;
				}
				sqlquery("INSERT INTO bigtree_tags (`tag`,`metaphone`,`route`) VALUES ('".mysql_real_escape_string($tag)."','$meta','$route')");
				$id = sqlid();
			} else {
				$id = $f["id"];
			}
			
			return $id;
		}
		
		/*
			Function: createTemplate
				Creates a template and its default files/directories.
		
			Paremeters:
				id - Id for the template.
				name - Name
				description - Description
				routed - Basic ("") or Routed ("on")
				level - Access level (0 for everyone, 1 for administrators, 2 for developers)
				module - Related module id
				image - Image
				callouts_enabled - "on" for yes
				resources - An array of resources
		*/
		
		function createTemplate($id,$name,$description,$routed,$level,$module,$image,$callouts_enabled,$resources) {
			// If we're creating a new file, let's populate it with some convenience things to show what resources are available.
			$file_contents = "<?\n	/*\n		Resources Available:\n";
		
			$clean_resources = array();
			foreach ($resources as $resource) {
			    if ($resource["id"]) {
			    	$options = json_decode($resource["options"],true);
			    	foreach ($options as $key => $val) {
			    		if ($key != "name" && $key != "id" && $key != "type")
			    			$resource[$key] = $val;
			    	}
			    	
			    	$file_contents .= '		$'.$resource["id"].' = '.$resource["name"].' - '.$types[$resource["type"]]."\n";
			    	
			    	$resource["id"] = htmlspecialchars($resource["id"]);
			    	$resource["name"] = htmlspecialchars($resource["name"]);
			    	$resource["subtitle"] = htmlspecialchars($resource["subtitle"]);
			    	unset($resource["options"]);
			    	$clean_resources[] = $resource;
			    }
			}
						
			
			$file_contents .= '	*/
?>';		
			
			if ($routed == "on") {
				if (!file_exists($GLOBALS["server_root"]."templates/routed/".$id)) {
					mkdir($GLOBALS["server_root"]."templates/routed/".$id);
					chmod($GLOBALS["server_root"]."templates/routed/".$id,0777);
				}
				if (!file_exists($GLOBALS["server_root"]."templates/routed/".$id."/default.php")) {
					file_put_contents($GLOBALS["server_root"]."templates/routed/".$id."/default.php",$file_contents);
					chmod($GLOBALS["server_root"]."templates/routed/".$id."/default.php",0777);
				}
			} else {
				if (!file_exists($GLOBALS["server_root"]."templates/basic/".$id.".php")) {
					file_put_contents($GLOBALS["server_root"]."templates/basic/".$id.".php",$file_contents);
					chmod($GLOBALS["server_root"]."templates/basic/".$id.".php",0777);
				}
			}
			
			$id = mysql_real_escape_string($id);
			$name = mysql_real_escape_string(htmlspecialchars($name));
			$description = mysql_real_escape_string(htmlspecialchars($description));
			$module = mysql_real_escape_string($module);
			$resources = mysql_real_escape_string(json_encode($clean_resources));
			$image = mysql_real_escape_string($image);
			$level = mysql_real_escape_string($level);
			$callouts_enabled = mysql_real_escape_string($callouts_enabled);
			$routed = mysql_real_escape_string($routed);
			
			sqlquery("INSERT INTO bigtree_templates (`id`,`name`,`module`,`resources`,`image`,`description`,`level`,`callouts_enabled`,`routed`) VALUES ('$id','$name','$module','$resources','$image','$description','$level','$callouts_enabled','$routed')");
		}
		
		/*
			Function: createUser
				Creates a user.
				Checks for developer access.
			
			Parameters:
				data - An array of user data. ("email", "password", "name", "company", "level", "permissions")
			
			Returns:
				id of the newly created user or false if a user already exists with the provided email.
		*/

		function createUser($data) {
			global $config;
			
			// Safely go through the post data
			foreach ($data as $key => $val) {
				if (substr($key,0,1) != "_" && !is_array($val)) {
					$$key = mysql_real_escape_string($val);
				}
			}

			// See if the user already exists
			$r = sqlrows(sqlquery("SELECT * FROM bigtree_users WHERE email = '$email'"));
			if ($r > 0) {
				return false;
			}

			$permissions = mysql_real_escape_string(json_encode($data["permissions"]));
			
			// If the user is trying to create a developer user and they're not a developer, then… no.
			if ($level > $this->Level) {
				$level = $this->Level;
			}
			
			// Hash the password.
			$phpass = new PasswordHash($config["password_depth"], TRUE);
			$password = mysql_real_escape_string($phpass->HashPassword($data["password"]));

			sqlquery("INSERT INTO bigtree_users (`email`,`password`,`name`,`company`,`level`,`permissions`) VALUES ('$email','$password','$name','$company','$level','$permissions')");
			$id = sqlid();
			
			// Audit trail.
			$this->track("bigtree_users",$id,"created");

			return $id;
		}
		
		/*
			Function: deleteCallout
				Deletes a callout and removes its file.
			
			Parameters:
				id - The id of the callout.
		*/
		
		function deleteCallout($id) {
			$id = mysql_real_escape_string($id);
			sqlquery("DELETE FROM bigtree_callouts WHERE id = '$id'");
			unlink($GLOBALS["server_root"]."templates/callouts/$id.php");
		}
		
		/*
			Function: deleteFeed
				Deletes a feed.
			
			Parameters:
				id - The id of the feed.
		*/
		
		function deleteFeed($id) {
			$id = mysql_real_escape_string($id);
			sqlquery("DELETE FROM bigtree_feeds WHERE id = '$id'");
		}
		
		/*
			Function: deleteFieldType
				Deletes a field type and erases its files.
		
			Parameters:
				id - The id of the field type.
		*/
		
		function deleteFieldType($id) {
			unlink($GLOBALS["server_root"]."custom/admin/form-field-types/draw/$id.php");
			unlink($GLOBALS["server_root"]."custom/admin/form-field-types/process/$id.php");
			sqlquery("DELETE FROM bigtree_field_types WHERE id = '".mysql_real_escape_string($id)."'");
		}
		
		/*
			Function: deleteModule
				Deletes a module.
			
			Parameters:
				id - The id of the module.
		*/
		
		function deleteModule($id) {
			$id = mysql_real_escape_string($id);
			
			// Get info and delete the class.
			$module = $this->getModule($id);
			unlink($GLOBALS["server_root"]."custom/inc/modules/".$module["route"].".php");
	
			// Delete all the related auto module actions
			$actions = $this->getModuleActions($id);
			foreach ($actions as $action) {
				if ($action["form"]) {
					sqlquery("DELETE FROM bigtree_module_forms WHERE id = '".$action["form"]."'");
				}
				if ($action["view"]) {
					sqlquery("DELETE FROM bigtree_module_views WHERE id = '".$action["view"]."'");
				}
			}
			
			// Delete actions
			sqlquery("DELETE FROM bigtree_module_actions WHERE module = '$id'");
			
			// Delete the module
			sqlquery("DELETE FROM bigtree_modules WHERE id = '$id'");
		}
		
		/*
			Function: deleteModuleAction
				Deletes a module action.
				Also deletes the related form or view if no other action is using it.
			
			Parameters:
				id - The id of the action to delete.
		*/
		
		function deleteModuleAction($id) {
			$id = mysql_real_escape_string($id);
			
			$a = $this->getModuleAction($id);
			if ($a["form"]) {
				// Only delete the auto-ness if it's the only one using it.
				if (sqlrows(sqlquery("SELECT * FROM bigtree_module_actions WHERE form = '".$a["form"]."'")) == 1) {
					sqlquery("DELETE FROM bigtree_module_forms WHERE id = '".$a["form"]."'");
				}
			}
			if ($a["view"]) {
				// Only delete the auto-ness if it's the only one using it.
				if (sqlrows(sqlquery("SELECT * FROM bigtree_module_actions WHERE view = '".$a["view"]."'")) == 1) {
					sqlquery("DELETE FROM bigtree_module_views WHERE id = '".$a["view"]."'");
				}
			}
			sqlquery("DELETE FROM bigtree_module_actions WHERE id = '$id'");
		}
		
		/*
			Function: deleteModuleForm
				Deletes a module form and its related actions.
			
			Parameters:
				id - The id of the module form.
		*/
		
		function deleteModuleForm($id) {
			$id = mysql_real_escape_string($id);
			sqlquery("DELETE FROM bigtree_module_forms WHERE id = '$id'");
			sqlquery("DELETE FROM bigtree_module_actions WHERE form = '$id'");
		}
		
		/*
			Function: deleteModuleGroup
				Deletes a module group. Sets modules in the group to Misc.
			
			Parameters:
				id - The id of the module group.
		*/
		
		function deleteModuleGroup($id) {
			$id = mysql_real_escape_string($id);
			sqlquery("DELETE FROM bigtree_module_groups WHERE id = '$id'");
			sqlquery("UPDATE bigtree_modules SET `group` = '0' WHERE `group` = '$id'");
		}
		
		/*
			Function: deleteModuleView
				Deletes a module view and its related actions.
			
			Parameters:
				id - The id of the module view.
		*/
		
		function deleteModuleView($id) {
			$id = mysql_real_escape_string($id);
			sqlquery("DELETE FROM bigtree_module_views WHERE id = '$id'");
			sqlquery("DELETE FROM bigtree_module_actions WHERE view = '$id'");
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

		function deletePage($page) {
			global $cms;

			$page = mysql_real_escape_string($page);

			$r = $this->getPageAccessLevel($page);
			if ($r == "p" && $this->canModifyChildren($cms->getPage($page))) {
				// If the page isn't numeric it's most likely prefixed by the "p" so it's pending.
				if (!is_numeric($page)) {
					sqlquery("DELETE FROM bigtree_pending_changes WHERE id = '".mysql_real_escape_string(substr($page,1))."'");
					$this->growl("Pages","Deleted Page");
					$this->track("bigtree_pages","p$page","deleted-pending");
				} else {
					sqlquery("DELETE FROM bigtree_pages WHERE id = '$page'");
					// Delete the children as well.
					$this->deletePageChildren($page);
					$this->growl("Pages","Deleted Page");
					$this->track("bigtree_pages",$page,"deleted");
				}

				return true;
			}
			$this->stop("You do not have permission to delete this page.");
		}
		
		/*
			Function: deletePageChildren
				Deletes the children of a page and recurses downward.
				Does not check permissions.
			
			Parameters:
				id - The parent id to delete children for.
		*/

		function deletePageChildren($id) {
			$q = sqlquery("SELECT * FROM bigtree_pages WHERE parent = '$id'");
			while ($f = sqlfetch($q)) {
				$this->deletePageChildren($f["id"]);
			}
			sqlquery("DELETE FROM bigtree_pages WHERE parent = '$id'");
			$this->track("bigtree_pages",$id,"deleted");
		}
		
		/*
			Function: deletePageDraft
				Deletes a page draft.
				Checks permissions.
			
			Parameters:
				id - The page id to delete the draft for.
		*/
		
		function deletePageDraft($id) {
			$id = mysql_real_escape_string($id);
			// Get the version, check if the user has access to the page the version refers to.
			$access = $this->getPageAccessLevel($id);
			if ($access != "p") {
				$this->stop("You must be a publisher to manage revisions.");
			}
			
			// Delete draft copy
			sqlquery("DELETE FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND `item_id` = '$id'");
		}
		
		/*
			Function: deletePageRevision
				Deletes a page revision.
				Checks permissions.
			
			Parameters:
				id - The page version id.
		*/
		
		function deletePageRevision($id) {
			// Get the version, check if the user has access to the page the version refers to.
			$revision = $this->getPageRevision($id);
			$access = $this->getPageAccessLevel($revision["page"]);
			if ($access != "p") {
				$this->stop("You must be a publisher to manage revisions.");
			}
			
			// Delete the revision
			sqlquery("DELETE FROM bigtree_page_revisions WHERE id = '".$revision["id"]."'");
		}
		
		/*
			Function: deletePendingChange
				Deletes a pending change.
			
			Parameters:
				id - The id of the change.
		*/
		
		function deletePendingChange($id) {
			$id = mysql_real_escape_string($id);
			sqlquery("DELETE FROM bigtree_pending_changes WHERE id = '$id'");
		}
		
		/*
			Function: deleteSetting
				Deletes a setting.
			
			Parameters:
				id - The id of the setting.
		*/
		
		function deleteSetting($id) {
			$id = mysql_real_escape_string($id);
			sqlquery("DELETE FROM bigtree_settings WHERE id = '$id'");
		}
		
		/*
			Function: deleteTemplate
				Deletes a template.
			
			Parameters:
				id - The id of the template.
		*/
		
		function deleteTemplate($id) {
			$id = mysql_real_escape_string($id);
			sqlquery("DELETE FROM bigtree_templates WHERE id = '$id'");
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

		function deleteUser($id) {
			$id = mysql_real_escape_string($id);
			// If this person has higher access levels than the person trying to update them, fail.
			$current = $this->getUser($id);
			if ($current["level"] > $this->Level) {
				return false;
			}

			sqlquery("DELETE FROM bigtree_users WHERE id = '$id'");

			// Audit trail
			$this->track("bigtree_users",$id,"deleted");

			return true;
		}
		
		/*
			Function: doesModuleEditActionExist
				Determines whether there is already an edit action for a module.
			
			Parameters:
				module - The module id to check.
		
			Returns:
				1 or 0, for true or false.
		*/
		
		function doesModuleEditActionExist($module) {
			return sqlrows(sqlquery("SELECT * FROM bigtree_module_actions WHERE module = '".mysql_real_escape_string($module)."' AND route = 'edit'"));
		}
		
		/*
			Function: doesModuleLandingActionExist
				Determines whether there is already a landing action for a module.
			
			Parameters:
				module - The module id to check.
		
			Returns:
				1 or 0, for true or false.
		*/
		
		function doesModuleLandingActionExist($module) {
			return sqlrows(sqlquery("SELECT * FROM bigtree_module_actions WHERE module = '".mysql_real_escape_string($module)."' AND route = ''"));
		}
		
		/*
			Function: emailDailyDigest
				Sends out a daily digest email to all who have subscribed.
		*/

		function emailDailyDigest() {
			$qusers = sqlquery("SELECT * FROM bigtree_users where daily_digest = 'on'");
			while ($user = sqlfetch($qusers)) {
				$changes = $this->getPendingChanges($user["id"]);
				$alerts = $this->getContentAlerts($user["id"]);

				// Start building the email
				$body =  "BigTree Daily Digest\n";
				$body .= "====================\n";
				$body .= $GLOBALS["admin_root"]."\n\n";
				
				if (is_array($alerts) && count($alerts)) {
					$body .= "Content Age Alerts\n";
					$body .= "------------------\n\n";
					
					foreach ($alerts as $alert) {
						$body .= $alert["nav_title"]." - ".$alert["current_age"]." Days Old\n";
						$body .= $GLOBALS["www_root"].$alert["path"]."/\n";
						$body .= $GLOBALS["admin_root"]."pages/edit/".$alert["id"]."/\n\n";
					}
				}

				if (count($changes)) {
					$body .= "Pending Changes\n";
					$body .= "---------------\n\n";

					foreach ($changes as $change) {
						if ($change["title"]) {
							$body .= $change["title"];
						} else {
							$body .= $change["mod"]["name"]." - ";

							if ($change["type"] == "NEW") {
								$body .= "Addition";
							} elseif ($change["type"] == "EDIT") {
								$body .= "Edit";
							}
						}
						$body .= "\n".$change["user"]["name"]." has submitted this change request.\n";
						$body .= $this->getChangeEditLink($change)."\n\n";
						
					}
				}
				
				if (count($alerts) || count($changes)) {
					mail($user["email"],"BigTree Daily Digest",$body,"From: BigTree Digest <mailer@bigtreecms.com>");
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

		function forgotPassword($email) {
			$email = mysql_real_escape_string($email);
			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_users WHERE email = '$email'"));
			if (!$f) {
				return false;
			}

			$hash = mysql_real_escape_string(md5(md5(md5(uniqid("bigtree-hash".microtime(true))))));
			sqlquery("UPDATE bigtree_users SET change_password_hash = '$hash' WHERE id = '".$f["id"]."'");

			mail($email,"Reset Your Password","A user with the IP address ".$_SERVER["REMOTE_ADDR"]." has requested to reset your password.\n\nIf this was you, please click the link below:\n".$GLOBALS["admin_root"]."login/reset-password/$hash/","From: no-reply@bigtreecms.com");
			header("Location: ".$GLOBALS["admin_root"]."login/forgot-success/");
			die();
		}
		
		/*
			Function: get404Total
				Get the total number of 404s of a certain type.
			
			Parameters:
				type - The type to retrieve the count for (301, ignored, 404)
			
			Returns:
				The number of 404s in the table of the given type.
		*/
		
		function get404Total($type) {
			if ($type == "404") {
				$total = sqlfetch(sqlquery("SELECT COUNT(id) AS `total` FROM bigtree_404s WHERE ignored = '' AND redirect_url = ''"));
			} elseif ($type == "301") {
				$total = sqlfetch(sqlquery("SELECT COUNT(id) AS `total` FROM bigtree_404s WHERE ignored = '' AND redirect_url != ''"));
			} elseif ($type == "ignored") {
				$total = sqlfetch(sqlquery("SELECT COUNT(id) AS `total` FROM bigtree_404s WHERE ignored = 'on'"));
			}
			
			return $total["total"];
		}
		
		/*
			Function: getAccessGroups
				Returns a list of all groups the logged in user has access to in a module.
			
			Parameters:
				module - A module id or module entry.
			
			Returns:
				An array of groups if a user has limited access to a module or "true" if the user has access to all groups.
		*/

		function getAccessGroups($module) {
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
			
			Returns:
				The permission level for the given item or module (if item was not passed).
			
			See Also:
				<getCachedAccessLevel>
		*/

		function getAccessLevel($module,$item = array(),$table = "") {
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
				$gv = $item[$module["gbp"]["group_field"]];
				$gp = $this->Permissions["module_gbp"][$id][$gv];

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

		function getActionClass($action,$item) {
			$class = "";
			if ($item["bigtree_pending"] && $action != "edit" && $action != "delete") {
				return "icon_disabled";
			}
			if ($action == "feature") {
				$class = "icon_feature";
				if ($item["featured"]) {
					$class .= " icon_feature_on";
				}
			}
			if ($action == "edit") {
				$class = "icon_edit";
			}
			if ($action == "delete") {
				$class = "icon_delete";
			}
			if ($action == "approve") {
				$class = "icon_approve";
				if ($item["approved"]) {
					$class .= " icon_approve_on";
				}
			}
			if ($action == "archive") {
				$class = "icon_archive";
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

		function getArchivedNavigationByParent($parent) {
			$nav = array();
			$q = sqlquery("SELECT id,nav_title as title,parent,external,new_window,template,publish_at,expire_at,path FROM bigtree_pages WHERE parent = '$parent' AND archived = 'on' ORDER BY nav_title asc");
			while ($nav_item = sqlfetch($q)) {
				$nav_item["external"] = str_replace("{wwwroot}",$GLOBALS["www_root"],$nav_item["external"]);
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
		
		function getAutoModuleActions($module) {
			$items = array();
			$id = mysql_real_escape_string($module);
			$q = sqlquery("SELECT * FROM bigtree_module_actions WHERE module = '$id' AND (form != 0 OR view != 0) AND in_nav = 'on' ORDER BY position DESC, id ASC");
			while ($f = sqlfetch($q)) {
				if ($f["form"]) {
					$f["form"] = BigTreeAutoModule::getForm($f["form"]);
					$f["type"] = "form";
				} elseif ($f["view"]) {
					$f["view"] = BigTreeAutoModule::getView($f["view"]);
					$f["type"] = "view";
				}
				$items[] = $f;
			}
			return $items;
		}
		
		/*
			Function: getBasicTemplates
				Returns a list of non-routed templates ordered by position.	
			
			Returns:
				An array of template entries.
		*/

		function getBasicTemplates() {
			$q = sqlquery("SELECT * FROM bigtree_templates WHERE level <= '".$this->Level."' ORDER BY position DESC, id ASC");
			$items = array();
			while ($f = sqlfetch($q)) {
				if (!$f["routed"]) {
					$items[] = $f;
				}
			}
			return $items;
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
		function getCachedAccessLevel($module,$item = array(),$table = "") {
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
				$gv = $item["gbp_field"];
				$gp = $this->Permissions["module_gbp"][$id][$gv];

				if ($gp != "n") {
					return $gp;
				}
			}

			return $perm;
		}
		
		/*
			Function: getCachedFieldTypes
				Caches available field types and returns them.
			
			Returns:
				Array of three arrays of field types (template, module, and callout).
		*/
		
		function getCachedFieldTypes() {
			// Used cached values if available, otherwise query the DB
			if (file_exists($GLOBALS["server_root"]."cache/form-field-types.btc")) {
				$types = json_decode(file_get_contents($GLOBALS["server_root"]."cache/form-field-types.btc"),true);
			} else {
				$types["module"] = array(
					"text" => "Text",
					"textarea" => "Text Area",
					"html" => "HTML Area",
					"upload" => "Upload",
					"list" => "List",
					"checkbox" => "Checkbox",
					"date" => "Date Picker",
					"time" => "Time Picker",
					"photo-gallery" => "Photo Gallery",
					"array" => "Array of Items",
					"route" => "Generated Route",
					"custom" => "Custom Function"
				);
				$types["template"] = $types["module"];
				$types["callout"] = array(
					"text" => "Text",
					"textarea" => "Text Area",
					"html" => "HTML Area",
					"upload" => "Upload",
					"list" => "List",
					"checkbox" => "Checkbox",
					"date" => "Date Picker",
					"time" => "Time Picker",
					"array" => "Array of Items",
					"custom" => "Custom Function"
				);

				$q = sqlquery("SELECT * FROM bigtree_field_types ORDER BY name");
				while ($f = sqlfetch($q)) {
					if ($f["pages"]) {
						$types["template"][$f["id"]] = $f["name"];
					}
					if ($f["modules"]) {
						$types["module"][$f["id"]] = $f["name"];
					}
					if ($f["callouts"]) {
						$types["callout"][$f["id"]] = $f["name"];
					}
				}
				file_put_contents($GLOBALS["server_root"]."cache/form-field-types.btc",json_encode($types));
			}
			
			return $types;
		}
		
		/*
			Function: getCallout
				Returns a callout entry.
			
			Parameters:
				id - The id of the callout.
			
			Returns:
				A callout entry from bigtree_callouts with resources decoded.
		*/
		
		function getCallout($id) {
			$id = mysql_real_escape_string($id);
			$item = sqlfetch(sqlquery("SELECT * FROM bigtree_callouts WHERE id = '$id'"));
			$item["resources"] = json_decode($item["resources"],true);
			return $item;
		}
		
		/*
			Function: getCallouts
				Returns a list of callouts.
			
			Parameters:
				sort - The order to return the callouts. Defaults to positioned.
		
			Returns:
				An array of callout entries from bigtree_callouts.
		*/

		function getCallouts($sort = "position DESC, id ASC") {
			$callouts = array();
			$q = sqlquery("SELECT * FROM bigtree_callouts ORDER BY $sort");
			while ($f = sqlfetch($q)) {
				$callouts[] = $f;
			}
			return $callouts;
		}
		
		/*
			Function: getChange
				Get a pending change.
			
			Parameters:
				id - The id of the pending change.
			
			Returns:
				A pending change entry from the bigtree_pending_changes table.
		*/

		function getChange($id) {
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

		function getChangeEditLink($change) {
			if (!is_array($change)) {
				$change = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE id = '$change'"));
			}

			if ($change["table"] == "bigtree_pages" && $change["item_id"]) {
				return $GLOBALS["admin_root"]."pages/edit/".$change["item_id"]."/";
			}

			if ($change["table"] == "bigtree_pages") {
				return $GLOBALS["admin_root"]."pages/edit/p".$change["id"]."/";
			}

			$modid = $change["module"];
			$module = sqlfetch(sqlquery("SELECT * FROM bigtree_modules WHERE id = '$modid'"));
			$form = sqlfetch(sqlquery("SELECT * FROM bigtree_module_forms WHERE `table` = '".$change["table"]."'"));
			$action = sqlfetch(sqlquery("SELECT * FROM bigtree_module_actions WHERE `form` = '".$form["id"]."' AND in_nav = ''"));

			if (!$change["item_id"]) {
				$change["item_id"] = "p".$change["id"];
			}

			if ($action) {
				return $GLOBALS["admin_root"].$module["route"]."/".$action["route"]."/".$change["item_id"]."/";
			} else {
				return $GLOBALS["admin_root"].$module["route"]."/edit/".$change["item_id"]."/";
			}
		}
		
		/*
			Function: getContentAlerts
				Gets a list of pages with content older than their Max Content Age that a user follows.
			
			Parameters:
				user - The user id to pull alerts for or a user entry.
			
			Returns:
				An array of arrays containing a page title, path, and id.
		*/
		
		function getContentAlerts($user) {
			if (is_array($user)) {
				$user = $this->getUser($user["id"]);
			} else {
				$user = $this->getUser($user);
			}
			
			if (!is_array($user["alerts"])) {
				return false;
			}
			
			$alerts = array();
			// We're going to generate a list of pages the user cares about first to get their paths.
			$where = array();
			foreach ($user["alerts"] as $alert => $status) {
				$where[] = "id = '".mysql_real_escape_string($alert)."'";
			}
			if (!count($where)) {
				return false;
			}
			// If we care about the whole tree, skip the madness.
			if ($alerts[0] == "on") {
				$q = sqlquery("SELECT nav_title,id,path,updated_at,DATEDIFF('".date("Y-m-d")."',updated_at) AS current_age FROM bigtree_pages WHERE max_age > 0 AND DATEDIFF('".date("Y-m-d")."',updated_at) > max_age ORDER BY current_age DESC");
			} else {
				$paths = array();
				$q = sqlquery("SELECT path FROM bigtree_pages WHERE ".implode(" OR ",$where));
				while ($f = sqlfetch($q)) {
					$paths[] = "path = '".mysql_real_escape_string($f["path"])."' OR path LIKE '".mysql_real_escape_string($f["path"])."/%'";
				}
				// Find all the pages that are old that contain our paths
				$q = sqlquery("SELECT nav_title,id,path,updated_at,DATEDIFF('".date("Y-m-d")."',updated_at) AS current_age FROM bigtree_pages WHERE max_age > 0 AND (".implode(" OR ",$paths).") AND DATEDIFF('".date("Y-m-d")."',updated_at) > max_age ORDER BY current_age DESC");
			}
			
			while ($f = sqlfetch($q)) {
				$alerts[] = $f;
			}
			
			return $alerts;
		}
		
		/*
			Function: getFeeds
				Returns a list of feeds.
				
			Parameters:
				sort - The sort direction, defaults to name.
			
			Returns:
				An array of feed elements from bigtree_feeds sorted by name.
		*/
		
		function getFeeds($sort = "name ASC") {
			$feeds = array();
			$q = sqlquery("SELECT * FROM bigtree_feeds ORDER BY $sort");
			while ($f = sqlfetch($q)) {
				$feeds[] = $f;
			}
			return $feeds;
		}
		
		/*
			Function: getFieldType
				Returns a field type.
			
			Parameters:
				id - The id of the file type.
			
			Returns:
				A field type entry with the "files" column decoded.
		*/
		
		function getFieldType($id) {
			$id = mysql_real_escape_string($id);
			$item = sqlfetch(sqlquery("SELECT * FROM bigtree_field_types WHERE id = '$id'"));
			if (!$item) {
				return false;
			}
			return $item;
		}
		
		/*
			Function: getFieldTypes
				Returns a list of field types.
			
			Parameters:
				sort - The sort directon, defaults to name ASC.
			
			Returns:
				An array of entries from bigtree_field_types.
		*/

		function getFieldTypes($sort = "name ASC") {
			$types = array();
			$q = sqlquery("SELECT * FROM bigtree_field_types ORDER BY $sort");
			while ($f = sqlfetch($q)) {
				$types[] = $f;
			}
			return $types;
		}
		
		/*
			Function: getFullNavigationPath
				Calculates the full navigation path for a given page ID.
			
			Parameters:
				id - The page ID to calculate the navigation path for.
			
			Returns:
				The navigation path (normally found in the "path" column in bigtree_pages).
		*/

		function getFullNavigationPath($id, $path = array()) {
			global $cms;
			
			// We can change $GLOBALS["root_page"] to drive multiple sites from different branches of the Pages tree.
			$root_page = isset($GLOBALS["root_page"]) ? $_GLOBALS["root_page"] : 0;
			
			$f = sqlfetch(sqlquery("SELECT route,id,parent FROM bigtree_pages WHERE id = '$id'"));
			$path[] = $cms->urlify($f["route"]);
			if ($f["parent"] != $root_page && $f["parent"] != 0) {
				return $this->getFullNavigationPath($f["parent"],$path);
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

		function getHiddenNavigationByParent($parent) {
			$nav = array();
			$q = sqlquery("SELECT id,nav_title as title,parent,external,new_window,template,publish_at,expire_at,path FROM bigtree_pages WHERE parent = '$parent' AND in_nav = '' AND archived != 'on' ORDER BY nav_title asc");
			while ($nav_item = sqlfetch($q)) {
				$nav_item["external"] = str_replace("{wwwroot}",$GLOBALS["www_root"],$nav_item["external"]);
				$nav[] = $nav_item;
			}
			return $nav;
		}
		
		/*
			Function: getMessages
				Returns all a user's messages.
			
			Returns:
				An array containing "sent", "read", and "unread" keys that contain an array of messages each.
		*/
		
		function getMessages() {
			$sent = array();
			$read = array();
			$unread = array();
			$q = sqlquery("SELECT bigtree_messages.*, bigtree_users.name AS sender_name FROM bigtree_messages JOIN bigtree_users ON bigtree_messages.sender = bigtree_users.id WHERE sender = '".$this->ID."' OR recipients LIKE '%|".$this->ID."|%' ORDER BY date DESC");
			
			while ($f = sqlfetch($q)) {
				// If we're the sender put it in the sent array.
				if ($f["sender"] == $this->ID) {
					$sent[] = $f;
				} else {
					// If we've been marked read, put it in the read array.
					if ($f["read_by"] && strpos("|".$this->ID."|",$f["read_by"]) !== false) {
						$read[] = $f;
					} else {
						$unread[] = $f;
					}
				}
			}
			
			return array("sent" => $sent, "read" => $read, "unread" => $unread);
		}
		
		/*
			Function: getMessage
				Returns a message from message center.
			
			Paramters:
				id - The id of the message.
		
			Returns:
				An entry from bigtree_messages.
		*/
		
		function getMessage($id) {
			$message = sqlfetch(sqlquery("SELECT * FROM bigtree_messages WHERE id = '".mysql_real_escape_string($id)."'"));
			if ($message["sender"] != $this->ID && strpos("|".$this->ID."|",$message["recipients"]) === false) {
				$this->stop("This message was not sent by you, or to you.");
			}
			return $message;
		}
		
		/*
			Function: getModule
				Returns an entry from the bigtree_modules table.
			
			Parameters:
				id - The id of the module.
			
			Returns:
				A module entry with the "gbp" column decoded.
		*/

		function getModule($id) {
			$id = mysql_real_escape_string($id);
			$module = sqlfetch(sqlquery("SELECT * FROM bigtree_modules WHERE id = '$id'"));
			if (!$module) {
				return false;
			}

			$module["gbp"] = json_decode($module["gbp"],true);
			return $module;
		}
		
		/*
			Function: getModuleAction
				Returns an entry from the bigtree_module_actions table.
			
			Parameters:
				id - The id of the action.
			
			Returns:
				A module action entry.
		*/

		function getModuleAction($id) {
			$id = mysql_real_escape_string($id);
			return sqlfetch(sqlquery("SELECT * FROM bigtree_module_actions WHERE id = '$id'"));
		}
		
		/*
			Function: getModuleActionByRoute
				Returns an entry from the bigtree_module_actions table for the given module and route.
			
			Parameters:
				module - The module to lookup an action for.
				route - The route of the action.
			
			Returns:
				A module action entry.
		*/

		function getModuleActionByRoute($module,$route) {
			$module = mysql_real_escape_string($module);
			$route = mysql_real_escape_string($route);
			return sqlfetch(sqlquery("SELECT * FROM bigtree_module_actions WHERE module = '$module' AND route = '$route'"));
		}
		
		/*
			Function: getModuleActionForForm
				Returns the related module action for an auto module form.
			
			Parameters:
				form - The id of a form or a form entry.
			
			Returns:
				A module action entry.
		*/

		function getModuleActionForForm($form) {
			if (is_array($form)) {
				$form = mysql_real_escape_string($form["id"]);
			} else {
				$form = mysql_real_escape_string($form);
			}
			return sqlfetch(sqlquery("SELECT * FROM bigtree_module_actions WHERE form = '$form'"));
		}
		
		/*
			Function: getModuleActionForView
				Returns the related module action for an auto module view.
			
			Parameters:
				view - The id of a view or a view entry.
			
			Returns:
				A module action entry.
		*/

		function getModuleActionForView($view) {
			if (is_array($form)) {
				$view = mysql_real_escape_string($view["id"]);
			} else {
				$view = mysql_real_escape_string($view);
			}
			return sqlfetch(sqlquery("SELECT * FROM bigtree_module_actions WHERE view = '$view'"));
		}
		
		/*
			Function: getModuleActions
				Returns a list of module actions in positioned order.
			
			Parameters:
				module - A module id or a module entry.
			
			Returns:
				An array of module action entries.
		*/

		function getModuleActions($module) {
			if (is_array($module)) {
				$module = mysql_real_escape_string($module["id"]);
			} else {
				$module = mysql_real_escape_string($module);
			}
			$items = array();
			$q = sqlquery("SELECT * FROM bigtree_module_actions WHERE module = '$module' ORDER BY position DESC, id ASC");
			while ($f = sqlfetch($q)) {
				$items[] = $f;
			}
			return $items;
		}
		
		/*
			Function: getModuleByRoute
				Returns a module entry for the given route.
			
			Parameters:
				route - A module route.
			
			Returns:
				A module entry with the "gbp" column decoded or false if a module was not found.
		*/
		
		function getModuleByRoute($route) {
			$route = mysql_real_escape_string($route);
			$module = sqlfetch(sqlquery("SELECT * FROM bigtree_modules WHERE route = '$route'"));
			if (!$module) {
				return false;
			}

			$module["gbp"] = json_decode($module["gbp"],true);
			return $module;
		}
		
		/*
			Function: getModuleForms
				Gets all module forms.
			
			Returns:
				An array of entries from bigtree_module_forms with "fields" decoded.
		*/
		
		function getModuleForms() {
			$items = array();
			$q = sqlquery("SELECT * FROM bigtree_module_forms");
			while ($f = sqlfetch($q)) {
				$f["fields"] = json_decode($f["fields"],true);
				$items[] = $f;
			}
			return $items;
		}
		
		/*
			Function: getModuleGroup
				Returns a module group entry from the bigtree_module_groups table.
			
			Parameters:
				id - The id of the module group.
			
			Returns:
				A module group entry.
			
			See Also:
				<getModuleGroupByName>
				<getModuleGroupByRoute>
		*/
		
		function getModuleGroup($id) {
			$id = mysql_real_escape_string($id);
			return sqlfetch(sqlquery("SELECT * FROM bigtree_module_groups WHERE id = '$id'"));
		}
		
		/*
			Function: getModuleGroupByName
				Returns a module group entry from the bigtree_module_groups table.
			
			Parameters:
				name - The name of the module group.
			
			Returns:
				A module group entry.
			
			See Also:
				<getModuleGroup>
				<getModuleGroupByRoute>
		*/
			

		function getModuleGroupByName($name) {
			$name = mysql_real_escape_string(strtolower($name));
			return sqlfetch(sqlquery("SELECT * FROM bigtree_module_groups WHERE LOWER(name) = '$name'"));
		}
		
		/*
			Function: getModuleGroupByRoute
				Returns a module group entry from the bigtree_module_groups table.
			
			Parameters:
				route - The route of the module group.
			
			Returns:
				A module group entry.
			
			See Also:
				<getModuleGroup>
				<getModuleGroupByName>
		*/
		
		function getModuleGroupByRoute($route) {
			$name = mysql_real_escape_string($route);
			return sqlfetch(sqlquery("SELECT * FROM bigtree_module_groups WHERE route = '$route'"));
		}
		
		/*
			Function: getModuleGroups
				Returns a list of module groups.
			
			Parameters:
				sort - Sort by (defaults to positioned)
			
			Returns:
				An array of module group entries from bigtree_module_groups.
		*/

		function getModuleGroups($sort = "position DESC, id ASC") {
			$items = array();
			$q = sqlquery("SELECT * FROM bigtree_module_groups ORDER BY $sort");
			while ($f = sqlfetch($q)) {
				$items[$f["id"]] = $f;
			}
			return $items;
		}
		
		/*
			Function: getModuleNavigation
				Returns a list of module actions that are in navigation.
			
			Parameters:
				module - A module id or a module entry.
			
			Returns:
				An array of module actions from bigtree_module_actions.
		*/

		function getModuleNavigation($module) {
			if (is_array($module)) {
				$module = mysql_real_escape_string($module["id"]);
			} else {
				$module = mysql_real_escape_string($module);
			}
			$items = array();
			$q = sqlquery("SELECT * FROM bigtree_module_actions WHERE module = '$module' AND in_nav = 'on' ORDER BY position DESC, id ASC");
			while ($f = sqlfetch($q)) {
				$items[] = $f;
			}
			return $items;
		}
		
		/*
			Function: getModulePackage
				Returns a module package with details decoded.
			
			Parameters:
				id - The id of the module package.
			
			Returns:
				A module package entry from bigtree_module_packages.
		*/

		function getModulePackage($id) {
			$id = mysql_real_escape_string($id);
			$item = sqlfetch(sqlquery("SELECT * FROM bigtree_module_packages WHERE id = '$id'"));
			if (!$item) {
				return false;
			}
			$item["details"] = json_decode($item["details"],true);
			return $item;
		}

		/*
			Function: getModulePackages
				Returns a list of module packages.
			
			Parameters:
				sort - Sort order (defaults to alphabetical by name)
			
			Returns:
				An array of entries from bigtree_module_packages.
		*/

		function getModulePackages($sort = "name ASC") {
			$packages = array();
			$q = sqlquery("SELECT * FROM bigtree_module_packages ORDER BY $sort");
			while ($f = sqlfetch($q)) {
				$packages[] = $f;
			}
			return $packages;
		}
		
		/*
			Function: getModules
				Returns a list of modules.
			
			Parameters:
				sort - The sort order (defaults to oldest first).
				auth - If set to true, only returns modules the logged in user has access to. Defaults to true.
			
			Returns:
				An array of entries from the bigtree_modules table with an additional "group_name" column for the group the module is in.
		*/

		function getModules($sort = "id ASC",$auth = true) {
			$items = array();
			$q = sqlquery("SELECT bigtree_modules.*,bigtree_module_groups.name AS group_name FROM bigtree_modules LEFT JOIN bigtree_module_groups ON bigtree_modules.`group` = bigtree_module_groups.id ORDER BY $sort");
			while ($f = sqlfetch($q)) {
				if (!$auth || $this->checkAccess($f["id"])) {
					$items[$f["id"]] = $f;
				}
			}
			return $items;
		}
		
		/*
			Function: getModulesByGroup
				Returns a list of modules in a given group.
			
			Parameters:
				group - The group to return modules for.
				sort - The sort order (defaults to positioned)
				auth - If set to true, only returns modules the logged in user has access to. Defaults to true.
			
			Returns:
				An array of entries from the bigtree_modules table with an additional "group_name" column for the group the module is in.
		*/

		function getModulesByGroup($group,$sort = "position DESC, id ASC",$auth = true) {
			if (is_array($group)) {
				$group = mysql_real_escape_string($group["id"]);
			} else {
				$group = mysql_real_escape_string($group);
			}
			$items = array();
			$q = sqlquery("SELECT * FROM bigtree_modules WHERE `group` = '$group' ORDER BY $sort");
			while ($f = sqlfetch($q)) {
				if ($this->checkAccess($f["id"]) || !$auth) {
					$items[$f["id"]] = $f;
				}
			}
			return $items;
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

		function getNaturalNavigationByParent($parent,$levels = 1) {
			$nav = array();
			$q = sqlquery("SELECT id,nav_title as title,parent,external,new_window,template,publish_at,expire_at,path FROM bigtree_pages WHERE parent = '$parent' AND in_nav = 'on' AND archived != 'on' ORDER BY position DESC, id ASC");
			while ($nav_item = sqlfetch($q)) {
				$nav_item["external"] = str_replace("{wwwroot}",$GLOBALS["www_root"],$nav_item["external"]);
				if ($levels > 1) {
					$nav_item["children"] = $this->getNaturalNavigationByParent($f["id"],$levels - 1);
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

		function getPageAccessLevel($page) {
			return $this->getPageAccessLevelByUser($page,$this->ID);
		}
		
		/*
			Function: getPageAccessLevel
				Returns the access level for the given user to a given page.
			
			Parameters:
				page - The page id.
				user - The user id.
			
			Returns:
				"p" for publisher, "e" for editor, false for no access.
			
			See Also:
				<getPageAccessLevel>
		*/

		function getPageAccessLevelByUser($page,$user) {
			$u = $this->getUser($user);
			if ($u["level"] > 0) {
				return "p";
			}

			if (!is_numeric($page) && $page[0] == "p") {
				$f = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE id = '".substr($page,1)."'"));
				if ($f["user"] == $user) {
					return "p";
				}
				$pdata = json_decode($f["changes"],true);
				return $this->getPageAccessLevelByUser($pdata["parent"],$user);
			}

			$pp = $this->Permissions["page"][$page];
			if ($pp == "n") {
				return false;
			}

			if ($pp && $pp != "i") {
				return $pp;
			}

			$parent = sqlfetch(sqlquery("SELECT parent FROM bigtree_pages WHERE id = '".mysql_real_escape_string($page)."'"),true);
			$pp = $u["permissions"]["page"][$parent];
			while ((!$pp || $pp == "i") && $parent) {
				$parent = sqlfetch(sqlquery("SELECT parent FROM bigtree_pages WHERE id = '$parent'"),true);
				$pp = $u["permissions"]["page"][$parent];
			}

			if (!$pp || $pp == "i" || $pp == "n") {
				return false;
			}

			return $pp;
		}
		
		/*
			Function: getPageAdminLinks
				Gets a list of pages that link back to the admin.
			
			Returns:
				An array of pages that link to the admin.
		*/
		
		function getPageAdminLinks() {
			$pages = array();
			$q = sqlquery("SELECT * FROM bigtree_pages WHERE resources LIKE '%".$admin_root."%' OR resources LIKE '%".str_replace($www_root,"{wwwroot}",$admin_root)."%'");
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
		
		function getPageChanges($page) {
			$page = mysql_real_escape_string($page);
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
		
		function getPageChildren($page,$sort = "nav_title ASC") {
			$page = mysql_real_escape_string($page);
			$items = array();
			$q = sqlquery("SELECT * FROM bigtree_pages WHERE parent = '$page' AND archived != 'on' ORDER BY $sort");
			while ($f = sqlfetch($q)) {
				$items[] = $f;
			}
			return $items;
		}
		
		/*
			Function: getPageIds
				Returns all page ids in bigtree_pages.
			
			Returns:
				An array of page ids.
		*/
		
		function getPageIds() {
			$ids = array();
			$q = sqlquery("SELECT id FROM bigtree_pages ORDER BY id ASC");
			while ($f = sqlfetch($q)) {
				$ids[] = $f["id"];
			}
			return $ids;
		}
		
		/*
			Function: getPageOfSettings
				Returns a page of settings.
			
			Parameters:
				page - The page to return.
				query - Optional query string to search against.
				sort - Sort order. Defaults to name ASC.
			
			Returns:
				An array of entries from bigtree_settings.
				If the setting is encrypted the value will be "[Encrypted Text]", otherwise it will be decoded.
				If the calling user is a developer, returns locked settings, otherwise they are left out.
		*/

		function getPageOfSettings($page = 0,$query = "") {
			global $cms;
			// If we're querying...
			if ($query) {
				$qparts = explode(" ",$query);
				$qp = array();
				foreach ($qparts as $part) {
					$part = mysql_real_escape_string($part);
					$qp[] = "(name LIKE '%$part%' OR `value` LIKE '%$part%' OR description LIKE '%$part%')";
				}
				// If we're not a developer, leave out locked settings
				if ($this->Level < 2) {
					$q = sqlquery("SELECT * FROM bigtree_settings WHERE ".implode(" AND ",$qp)." AND locked != 'on' AND system != 'on' ORDER BY name LIMIT ".($page*$this->PerPage).",".$this->PerPage);
				// If we are a developer, show them.
				} else {
					$q = sqlquery("SELECT * FROM bigtree_settings WHERE ".implode(" AND ",$qp)." AND system != 'on' ORDER BY name LIMIT ".($page*$this->PerPage).",".$this->PerPage);
				}
			} else {
				// If we're not a developer, leave out locked settings
				if ($this->Level < 2) {
					$q = sqlquery("SELECT * FROM bigtree_settings WHERE locked != 'on' AND system != 'on' ORDER BY name LIMIT ".($page*$this->PerPage).",".$this->PerPage);
				// If we are a developer, show them.
				} else {
					$q = sqlquery("SELECT * FROM bigtree_settings WHERE system != 'on' ORDER BY name LIMIT ".($page*$this->PerPage).",".$this->PerPage);
				}
			}
			
			$items = array();
			while ($f = sqlfetch($q)) {
				foreach ($f as $key => $val) {
					$f[$key] = str_replace("{wwwroot}",$GLOBALS["www_root"],$val);
				}
				$f["value"] = json_decode($f["value"],true);
				if ($f["encrypted"] == "on") {
					$f["value"] = "[Encrypted Text]";
				}
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

		function getPageOfUsers($page = 0,$query = "",$sort = "name ASC") {
			// If we're searching.
			if ($query) {
				$qparts = explode(" ",$query);
				$qp = array();
				foreach ($qparts as $part) {
					$part = mysql_real_escape_string($part);
					$qp[] = "(name LIKE '%$part%' OR email LIKE '%$part%' OR company LIKE '%$part%')";
				}
				$q = sqlquery("SELECT * FROM bigtree_users WHERE ".implode(" AND ",$qp)." ORDER BY $sort LIMIT ".($page * $this->PerPage).",".$this->PerPage);
			// If we're grabbing anyone.
			} else {
				$q = sqlquery("SELECT * FROM bigtree_users ORDER BY $sort LIMIT ".($page * $this->PerPage).",".$this->PerPage);
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

		function getPageRevision($id) {
			$id = mysql_real_escape_string($id);
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
		
		function getPageRevisions($page) {
			$page = mysql_real_escape_string($page);
			
			// Get all previous revisions, add them to the saved or unsaved list
			$unsaved = array();
			$saved = array();
			$q = sqlquery("SELECT bigtree_users.name, bigtree_page_revisions.saved, bigtree_page_revisions.saved_description, bigtree_page_revisions.updated_at, bigtree_page_revisions.id FROM bigtree_page_revisions JOIN bigtree_users ON bigtree_page_revisions.author = bigtree_users.id WHERE page = '$page' ORDER BY updated_at DESC");
			while ($f = sqlfetch($q)) {
			    if ($f["saved"]) {
			    	$saved[] = $f;
			    } else {
			    	$unsaved[] = $f;
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
		
		function getPages() {
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
		
		function getPageSEORating($page,$content) {
			global $cms;
			$template = $cms->getTemplate($page["template"]);
			$tsources = array();
			$h1_field = "";
			$body_fields = array();

			if (is_array($template)) {
				foreach ($template["resources"] as $item) {
					if ($item["seo_body"]) {
						$body_fields[] = $item["id"];
					}
					if ($item["seo_h1"]) {
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
				$q = sqlquery("SELECT * FROM bigtree_pages WHERE title = '".mysql_real_escape_string($page["title"])."' AND id != '".$page["page"]."'");
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
					$recommendations[] = "Your meta description should be no more than 165 characters.  It is currently ".mb_strlen($page["meta_description"])." characters.";
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
					$regular_text .= $content[$field]." ";
					$stripped_text .= strip_tags($content[$field])." ";
				}
				// Check to see if there is any content
				if ($stripped_text) {
					$score += 5;
					$words = $textStats->word_count($stripped_text);
					$readability = $textStats->flesch_kincaid_reading_ease($stripped_text);
					$number_of_links = substr_count($regular_text,"<a ");
					$number_of_external_links = substr_count($regular_text,'href="http://');

					// See if there are at least 300 words.
					if ($words >= 300) {
						$score += 15;
					} else {
						$recommendations[] = "You should enter at least 300 words of page content.  You currently have ".$words." word(s).";
					}

					// See if we have any links
					if ($number_of_links) {
						$score += 5;
						// See if we have at least one link per 120 words.
						if (floor($words / 120) <= $number_of_links) {
							$score += 5;
						} else {
							$recommendations[] = "You should have at least one link for every 120 words of page content.  You currently have $number_of_links link(s).  You should have at least ".floor($words / 120).".";
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
						$recommendations[] = "Your readability score is ".($read_score*100)."%.  Using shorter sentences and words with less syllables will make your site easier to read by search engines and users.";
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
					$recommendations[] = "Your content is around ".ceil(2 + ($age / (30*24*60*60)))." months old.  Updating your page more frequently will make it rank higher.";
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

		function getPendingChange($id) {
			$id = mysql_real_escape_string($id);
			$item = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE id = '$id'"));
			if (!$item) {
				return false;
			}
			$item["changes"] = json_decode($item["changes"],true);
			return $item;
		}
		
		/*
			Function: getPendingChanges
				Returns a list of changes that the logged in user has access to publish.

			Parameters:
				user - The user id to retrieve changes for. Defaults to the logged in user.

			Returns:
				An array of changes sorted by most recent.
		*/

		function getPendingChanges($user = false) {
			if (!$user) {
				$user = $this->getUser($this->ID);
			} else {
				$user = $this->getUser($user);
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
			if (is_array($user["permissions"]["module_gbp"])) {
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
					$r = $this->getPageAccessLevel($id);
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
						$level = $this->getAccessLevel($this->getModule($f["module"]),$item["item"],$f["table"]);
						if ($level == "p") {
							$ok = true;
						}
					}
				}

				// We're a publisher, get the info about the change and put it in the change list.
				if ($ok) {
					$mod = $this->getModule($f["module"]);
					$user = $this->getUser($f["user"]);
					$comments = unserialize($f["comments"]);
					if (!is_array($comments)) {
						$comments = array();
					}

					$f["mod"] = $mod;
					$f["user"] = $user;
					$f["comments"] = $comments;
					$changes[] = $f;
				}
			}

			return $changes;
		}
		
		/*
			Function: getPendingNavigationByParent
				Returns a list of pending pages under a given parent ordered by most recent.
			
			Parameters:
				parent - The ID of the parent page
				in_nav - "on" returns pages in navigation, "" returns hidden pages
			
			Returns:
				An array of pending page titles/ids.
		*/

		function getPendingNavigationByParent($parent,$in_nav = "on") {
			$nav = array();
			$titles = array();
			$q = sqlquery("SELECT * FROM bigtree_pending_changes WHERE pending_page_parent = '$parent' AND `table` = 'bigtree_pages' AND type = 'NEW' ORDER BY date DESC");
			while ($f = sqlfetch($q)) {
				$page = json_decode($f["changes"],true);
				if ($page["in_nav"] == $in_nav) {
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
			Function: getPendingPage
				Returns a page from the database with all its pending changes applied.

			Parameters:
				id - The ID of the live page or the ID of a pending page with "p" preceding the ID.

			Returns:
				A decoded page array with pending changes applied and related tags.

			See Also:
				<BigTreeCMS.getPage>
		*/

		function getPendingPage($id) {
			global $cms;
			
			// Get the live page.
			if (is_numeric($id)) {
				global $cms;
				$page = $cms->getPage($id);
				if (!$page) {
					return false;
				}
				$page["tags"] = $this->getTagsForPage($id);
				// Get pending changes for this page.
				$f = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = '".$page["id"]."'"));
			} else {
				$page = array();
				// Get the page.
				$f = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE `id` = '".mysql_real_escape_string(substr($id,1))."'"));
				if ($f) {
					$f["id"] = $id;
				} else {
					return false;
				}
			}

			// Sweep through pending changes and apply them to the page
			if ($f) {
				$page["updated_at"] = $f["date"];
				$changes = json_decode($f["changes"],true);
				foreach ($changes as $key => $val) {
					if ($key == "external") {
						$val = $cms->getInternalPageLink($val);
					}
					$page[$key] = $val;
				}
				// Decode the tag changes, apply them back.
				$tags = array();
				$temp_tags = json_decode($f["tags_changes"],true);
				if (is_array($temp_tags)) {
					foreach ($temp_tags as $tag) {
						$tags[] = sqlfetch(sqlquery("SELECT * FROM bigtree_tags WHERE id = '$tag'"));
					}
				}
				$page["tags"] = $tags;
				// Say that changes exist
				$page["changes_applied"] = true;
			}
			return $page;
		}
		
		/*
			Function: getContentsOfResourceFolder
				Returns a list of resources and subfolders in a folder (based on user permissions).

			Parameters:
				folder - The id of a folder or a folder entry.
				sort - The column to sort the folder's files on (default: date DESC).

			Returns:
				An array of two arrays - folders and resources - that a user has access to.
		*/

		function getContentsOfResourceFolder($folder, $sort = "date DESC") {
			if (is_array($folder)) {
				$folder = $folder["id"];
			}
			$folder = mysql_real_escape_string($folder);

			$folders = array();
			$resources = array();

			$q = sqlquery("SELECT * FROM bigtree_resource_folders WHERE parent = '$folder' ORDER BY name");
			while ($f = sqlfetch($q)) {
				if ($this->Level > 0 || $this->getResourceFolderPermission($f["id"]) != "n") {
					$folders[] = $f;
				}
			}

			$q = sqlquery("SELECT * FROM bigtree_resources WHERE folder = '$folder' ORDER BY $sort");
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
		
		function getResourceByFile($file) {
			$item = sqlfetch(sqlquery("SELECT * FROM bigtree_resources WHERE file = '".mysql_real_escape_string($file)."'"));
			if (!$item) {
				return false;
			}
			$item["file"] = str_replace("{wwwroot}",$GLOBALS["www_root"],$item["file"]);
			$item["thumbs"] = json_decode($item["thumbs"],true);
			foreach ($item["thumbs"] as &$thumb) {
				$thumb = str_replace("{wwwroot}",$GLOBALS["www_root"],$thumb);
			}
			return $item;
		}
		
		/*
			Function: getResourceFolder
				Returns a resource folder.
			
			Parameters:
				id - The id of the folder.
			
			Returns:
				A resource folder entry.
		*/
		
		function getResourceFolder($id) {
			$id = mysql_real_escape_string($id);
			return sqlfetch(sqlquery("SELECT * FROM bigtree_resource_folders WHERE id = '$id'"));
		}

		/*
			Function: getResourceFolderBreadcrumb
				Returns a breadcrumb of the given folder.

			Parameters:
				folder - The id of a folder or a folder entry.

			Returns:
				An array of arrays containing the name and id of folders above.
		*/

		function getResourceFolderBreadcrumb($folder,$crumb = array()) {
			if (!is_array($folder)) {
				$folder = sqlfetch(sqlquery("SELECT * FROM bigtree_resource_folders WHERE id = '".mysql_real_escape_string($folder)."'"));
			}

			if ($folder) {
				$crumb[] = array("id" => $folder["id"], "name" => $folder["name"]);
			}

			if ($folder["parent"]) {
				return $this->getResourceFolderBreadcrumb($folder["parent"],$crumb);
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
		
		function getResourceFolderChildren($id) {
			$items = array();
			$id = mysql_real_escape_string($id);
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

		function getResourceFolderPermission($folder) {
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
					$folder = sqlfetch(sqlquery("SELECT parent FROM bigtree_resource_folders WHERE id = '".mysql_real_escape_string($id)."'"));
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
				Returns a list of routed templates ordered by position.
			
			Returns:
				An array of template entries.
		*/

		function getRoutedTemplates() {
			$q = sqlquery("SELECT * FROM bigtree_templates WHERE level <= '".$this->Level."' ORDER BY position DESC, id ASC");
			$items = array();
			while ($f = sqlfetch($q)) {
				if ($f["routed"]) {
					$items[] = $f;
				}
			}
			return $items;
		}
		
		/*
			Function: getSetting
				Returns a setting.
			
			Parameters:
				id - The id of the setting to return.
			
			Returns:
				A setting entry with its value properly decoded and decrypted.
				Returns false if the setting could not be found.
		*/

		function getSetting($id) {
			global $config;
			$id = mysql_real_escape_string($id);

			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_settings WHERE id = '$id'"));
			if (!$f) {
				return false;
			}

			foreach ($f as $key => $val) {
				$f[$key] = str_replace("{wwwroot}",$GLOBALS["www_root"],$val);
			}
			if ($f["encrypted"]) {
				$v = sqlfetch(sqlquery("SELECT AES_DECRYPT(`value`,'".mysql_real_escape_string($config["settings_key"])."') AS `value` FROM bigtree_settings WHERE id = '$id'"));
				$f["value"] = $v["value"];
			}
			$f["value"] = json_decode($f["value"],true);
			return $f;
		}
		
		/*
			Function: getSettings
				Returns a list of all settings.
				Checks for developer access. 
			
			Parameters:
				sort - Order to return the settings. Defaults to name ASC.
			
			Returns:
				An array of entries from bigtree_settings.
				If the setting is encrypted the value will be "[Encrypted Text]", otherwise it will be decoded.
				If the calling user is a developer, returns locked settings, otherwise they are left out.
		*/

		function getSettings($sort = "name ASC") {
			$items = array();
			if ($this->Level < 2) {
				$q = sqlquery("SELECT * FROM bigtree_settings WHERE locked != 'on' AND system != 'on' ORDER BY $sort");
			} else {
				$q = sqlquery("SELECT * FROM bigtree_settings WHERE system != 'on' ORDER BY $sort");
			}
			while ($f = sqlfetch($q)) {
				foreach ($f as $key => $val) {
					$f[$key] = str_replace("{wwwroot}",$GLOBALS["www_root"],$val);
				}
				$f["value"] = json_decode($f["value"],true);
				if ($f["encrypted"] == "on") {
					$f["value"] = "[Encrypted Text]";
				}
				$items[] = $f;
			}
			return $items;
		}
		
		/*
			Function: getSettingsPageCount
				Returns the number of pages of settings.
			
			Parameters:
				query - Optional string to query against.
			
			Returns:
				The number of pages of settings.
		*/

		function getSettingsPageCount($query = "") {
			// If we're searching.
			if ($query) {
				$qparts = explode(" ",$query);
				$qp = array();
				foreach ($qparts as $part) {
					$part = mysql_real_escape_string($part);
					$qp[] = "(name LIKE '%$part%' OR value LIKE '%$part%' OR description LIKE '%$part%')";
				}
				// Administrator
				if ($this->Level < 2) {
					$q = sqlquery("SELECT id FROM bigtree_settings WHERE system != 'on' AND locked != 'on' AND ".implode(" AND ",$qp));
				// Developer
				} else {
					$q = sqlquery("SELECT id FROM bigtree_settings WHERE system != 'on' AND ".implode(" AND ",$qp));
				}
			} else {
				// Administrator
				if ($this->Level < 2) {
					$q = sqlquery("SELECT id FROM bigtree_settings WHERE system != 'on' AND locked != 'on'");
				// Developer
				} else {
					$q = sqlquery("SELECT id FROM bigtree_settings WHERE system != 'on'");
				}
			}

			$r = sqlrows($q);
			$pages = ceil($r / $this->PerPage);
			if ($pages == 0) {
				$pages = 1;
			}

			return $pages;
		}
		
		/*
			Function: getTag
				Returns a tag for the given id.
			
			Parameters:
				id - The id of the tag.
			
			Returns:
				A bigtree_tags entry.
		*/
		
		function getTag($id) {
			$id = mysql_real_escape_string($id);
			return sqlfetch(sqlquery("SELECT * FROM bigtree_tags WHERE id = '$id'"));
		}
		
		/*
			Function: getTagsForPage
				Returns a list of tags a page is tagged with.
			
			Parameters:
				page - Either a page id or a page entry.
			
			Returns:
				An array of tags.
		*/

		function getTagsForPage($page) {
			if (is_array($page)) {
				$page = mysql_real_escape_string($page["id"]);
			} else {
				$page = mysql_real_escape_string($page);
			}
			
			$tags = array();
			$q = sqlquery("SELECT bigtree_tags.* FROM bigtree_tags JOIN bigtree_tags_rel WHERE bigtree_tags_rel.tag = bigtree_tags.id AND bigtree_tags_rel.entry = '$page' AND bigtree_tags_rel.module = '0' ORDER BY bigtree_tags.tag");
			while ($f = sqlfetch($q)) {
				$tags[] = $f;
			}
			return $tags;
		}
		
		/*
			Function: getTemplates
				Returns a list of templates.
			
			Parameters:
				sort - Sort order, defaults to positioned.
			
			Returns:
				An array of template entries.
		*/

		function getTemplates($sort = "position DESC, id ASC") {
			$items = array();
			$q = sqlquery("SELECT * FROM bigtree_templates ORDER BY $sort");
			while ($f = sqlfetch($q)) {
				$items[] = $f;
			}
			return $items;
		}
		
		/*
			Function: getUnreadMessageCount
				Returns the number of unread messages for the logged in user.
			
			Returns:
				The number of unread messages.
		*/
		
		function getUnreadMessageCount() {
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
		
		function getUser($id) {
			$id = mysql_real_escape_string($id);
			$item = sqlfetch(sqlquery("SELECT * FROM bigtree_users WHERE id = '$id'"));
			if ($item["level"] > 0) {
				$permissions = array();
				$q = sqlquery("SELECT * FROM bigtree_modules");
				while ($f = sqlfetch($q)) {
					$permissions["module"][$f["id"]] = "p";
				}
				$item["permissions"] = $permissions;
			} else {
				$item["permissions"] = json_decode($item["permissions"],true);
			}
			$item["alerts"] = json_decode($item["alerts"],true);
			return $item;
		}
		
		/*
			Function: getUserByEmail
				Gets a user entry for a given email.
			
			Parameters:
				email - The email to find.
			
			Returns:
				A user entry from bigtree_users.
		*/
		
		function getUserByEmail($email) {
			$email = mysql_real_escape_string($email);
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
		
		function getUserByHash($hash) {
			$hash = mysql_real_escape_string($hash);
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

		function getUsers($sort = "name ASC") {
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

		function getUsersPageCount($query = "") {
			// If we're searching.
			if ($query) {
				$qparts = explode(" ",$query);
				$qp = array();
				foreach ($qparts as $part) {
					$part = mysql_real_escape_string($part);
					$qp[] = "(name LIKE '%$part%' OR email LIKE '%$part%' OR company LIKE '%$part%')";
				}
				$q = sqlquery("SELECT id FROM bigtree_users WHERE ".implode(" AND ",$qp));
			// If we're showing all.
			} else {
				$q = sqlquery("SELECT id FROM bigtree_users");
			}

			$r = sqlrows($q);
			$pages = ceil($r / $this->PerPage);
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

		function growl($title,$message,$type = "success") {
			$_SESSION["bigtree"]["flash"] = array("message" => $message, "title" => $title, "type" => $type);
		}
		
		/*
			Function: htmlClean
				Removes things that shouldn't be in the <body> of an HTML document from a string.
			
			Parameters:
				html - A string of HTML
			
			Returns:
				A clean string of HTML for echoing in <body>
		*/

		function htmlClean($html) {
			return str_replace("<br></br>","<br />",strip_tags($html,"<a><abbr><address><area><article><aside><audio><b><base><bdo><blockquote><body><br><button><canvas><caption><cite><code><col><colgroup><command><datalist><dd><del><details><dfn><div><dl><dt><em><emded><fieldset><figcaption><figure><footer><form><h1><h2><h3><h4><h5><h6><header><hgroup><hr><i><iframe><img><input><ins><keygen><kbd><label><legend><li><link><map><mark><menu><meter><nav><noscript><object><ol><optgroup><option><output><p><param><pre><progress><q><rp><rt><ruby><s><samp><script><section><select><small><source><span><strong><style><sub><summary><sup><table><tbody><td><textarea><tfoot><th><thead><time><title><tr><ul><var><video><wbr>"));
		}
		
		/*
			Function: ignore404
				Ignores a 404 error.
				Checks permissions.
			
			Parameters:
				id - The id of the reported 404.
		*/
		
		function ignore404($id) {
			$this->requireLevel(1);
			$id = mysql_real_escape_string($id);
			sqlquery("UPDATE bigtree_404s SET ignored = 'on' WHERE id = '$id'");
		}
		
		/*
			Function: iplExists
				Determines whether an internal page link still exists or not.
			
			Parameters:
				ipl - An internal page link
			
			Returns:
				True if it is still a valid link, otherwise false.
		*/

		function iplExists($ipl) {
			global $cms;
			$ipl = explode("//",$ipl);
			
			// See if the page it references still exists.
			$nav_id = $ipl[1];
			if (!sqlrows(sqlquery("SELECT id FROM bigtree_pages WHERE id = '$nav_id'"))) {
				return false;
			}
			
			// Decode the commands attached to the page
			$commands = json_decode(base64_decode($ipl[2]),true);
			// If there are no commands, we're good.
			if (!isset($commands[0]) || !$commands[0]) {
				return true;
			}
			// If it's a hash tag link, we're also good.
			if (substr($commands[0],0,1) == "#") {
				return true;
			}
			// Get template for the navigation id to see if it's a routed template
			$t = sqlfetch(sqlquery("SELECT bigtree_templates.routed FROM bigtree_templates JOIN bigtree_pages ON bigtree_templates.id = bigtree_pages.template WHERE bigtree_pages.id = '$nav_id'"));
			// If we're a routed template, we're good.
			if ($t["routed"]) {
				return true;
			}
			
			// We may have been on a page, but there's extra routes that don't go anywhere or do anything so it's a 404.
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
		
		function lockCheck($table,$id,$include,$force = false,$in_admin = true) {
			global $www_root,$admin_root;
			$table = mysql_real_escape_string($table);
			$id = mysql_real_escape_string($id);
			
			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_locks WHERE `table` = '$table' AND item_id = '$id'"));
			if ($f && $f["user"] != $this->ID && strtotime($f["last_accessed"]) > (time()-300) && !$force) {
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
			
			Returns:
				false if login failed, otherwise redirects back to the page the person requested.
		*/

		function login($email,$password,$stay_logged_in = false) {
			global $path;
			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_users WHERE email = '".mysql_real_escape_string($email)."'"));
			$phpass = new PasswordHash($config["password_depth"], TRUE);
			$ok = $phpass->CheckPassword($password,$f["password"]);
			if ($ok) {
				if ($stay_logged_in) {
					setcookie('bigtree[email]',$f["email"],time()+31*60*60*24,str_replace($GLOBALS["domain"],"",$GLOBALS["www_root"]));
					setcookie('bigtree[password]',$f["password"],time()+31*60*60*24,str_replace($GLOBALS["domain"],"",$GLOBALS["www_root"]));
				}

				$_SESSION["bigtree"]["id"] = $f["id"];
				$_SESSION["bigtree"]["email"] = $f["email"];
				$_SESSION["bigtree"]["level"] = $f["level"];
				$_SESSION["bigtree"]["name"] = $f["name"];
				$_SESSION["bigtree"]["permissions"] = json_decode($f["permissions"],true);

				if ($path[1] == "login") {
					header("Location: ".$GLOBALS["admin_root"]);
				} else {
					header("Location: ".$GLOBALS["domain"].$_SERVER["REQUEST_URI"]);
				}
				die();
			} else {
				return false;
			}
		}
		
		/*
			Function: logout
				Logs out of the CMS.
				Destroys the user's session and unsets the login cookies, then sends the user back to the login page.
		*/

		function logout() {
			setcookie("bigtree[email]","",time()-3600,str_replace($GLOBALS["domain"],"",$GLOBALS["www_root"]));
			setcookie("bigtree[password]","",time()-3600,str_replace($GLOBALS["domain"],"",$GLOBALS["www_root"]));
			unset($_SESSION["bigtree"]);
			header("Location: ".$GLOBALS["admin_root"]);
			die();
		}
		
		/*
			Function: makeIPL
				Creates an internal page link out of a URL.
				
			Paramters:
				url - A URL
			
			Returns:
				An internal page link (if possible) or just the same URL (if it's not internal).
		*/

		function makeIPL($url) {
			global $cms;
			$command = explode("/",rtrim(str_replace($GLOBALS["www_root"],"",$url),"/"));
			list($navid,$commands) = $cms->getNavId($command);
			if (!$navid) {
				return str_replace(array($GLOBALS["www_root"],$GLOBALS["resource_root"]),"{wwwroot}",$url);
			}
			return "ipl://".$navid."//".base64_encode(json_encode($commands));
		}
		
		/*
			Function: markMessageRead
				Marks a message as read by the currently logged in user.
			
			Parameters:
				id - The message id.
		*/
		
		function markMessageRead($id) {
			$message = $this->getMessage($id);
			if (!$message) {
				return false;
			}
			$read_by = str_replace("|".$this->ID."|","",$message["read_by"])."|".$this->ID."|";
			sqlquery("UPDATE bigtree_messages SET read_by = '".mysql_real_escape_string($read_by)."' WHERE id = '".$message["id"]."'");
			return true;
		}
		
		/*
			Function: moduleActionExists
				Checks to see if an action exists for a given route and module.
			
			Parameters:
				module - The module to check.
				route - The route of the action to check.
			
			Returns:
				true if an action exists, otherwise false.
		*/

		function moduleActionExists($module,$route) {
			$module = mysql_real_escape_string($module);
			$route = mysql_real_escape_string($route);
			$f = sqlfetch(sqlquery("SELECT id FROM bigtree_module_actions WHERE module = '$module' AND route = '$route'"));
			if ($f) {
				return true;
			}
			return false;
		}
		
		/*
			Function: pingSearchEngines
				Sends the latest sitemap.xml out to search engine ping services if enabled in settings.	
		*/

		function pingSearchEngines() {
			global $cms;
			if ($cms->getSetting("ping-search-engines") == "on") {
				$google = file_get_contents("http://www.google.com/webmasters/tools/ping?sitemap=".urlencode($GLOBALS["www_root"]."sitemap.xml"));
				$ask = file_get_contents("http://submissions.ask.com/ping?sitemap=".urlencode($GLOBALS["www_root"]."sitemap.xml"));
				$yahoo = file_get_contents("http://search.yahooapis.com/SiteExplorerService/V1/ping?sitemap=".urlencode($GLOBALS["www_root"]."sitemap.xml"));
				$bing = file_get_contents("http://www.bing.com/webmaster/ping.aspx?siteMap=".urlencode($GLOBALS["www_root"]."sitemap.xml"));
			}
		}
		
		/*
			Function: refreshLock
				Refreshes a lock.
			
			Paramters:
				id - The id of the lock.
		*/
		
		function refreshLock($id) {
			$id = mysql_real_escape_string($id);
			sqlquery("UPDATE bigtree_locks SET last_accessed = NOW() WHERE id = '$id' AND user = '".$this->ID."'");
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

		function requireAccess($module) {
			global $cms,$admin_root,$css,$js,$site;
			if ($this->Level > 0)
				return "p";
			if (!isset($this->Permissions[$module]) || $this->Permissions[$module] == "") {
				ob_clean();
				include BigTree::path("admin/pages/_denied.php");
				$content = ob_get_clean();
				include BigTree::path("admin/layouts/default.php");
				die();
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

		function requireLevel($level) {
			global $admin,$cms,$admin_root,$css,$js,$site;
			if (!isset($this->Level) || $this->Level < $level) {
				ob_clean();
				include BigTree::path("admin/pages/_denied.php");
				$content = ob_get_clean();
				include BigTree::path("admin/layouts/default.php");
				die();
			}
		}
		
		/*
			Function: requirePublisher
				Checks the logged in user's access to a given module to make sure they are a publisher.
				Throws a permission denied page and stops page execution if the user doesn't have access.
			
			Parameters:
				module - The id of the module to check access to.
				
			Returns:
				The permission level of the logged in user.
		*/

		function requirePublisher($module) {
			global $cms,$admin_root,$css,$js,$site;
			if ($this->Level > 0)
				return true;
			if ($this->Permissions[$module] != "p") {
				ob_clean();
				include BigTree::path("admin/pages/_denied.php");
				$content = ob_get_clean();
				include BigTree::path("admin/layouts/default.php");
				die();
			}
			return true;
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
		
		function saveCurrentPageRevision($page,$description) {
			$page = mysql_real_escape_string($page);
			$description = mysql_real_escape_string($description);
			
			// Get the current page.
			$current = sqlfetch(sqlquery("SELECT * FROM bigtree_pages WHERE id = '$page'"));
			foreach ($current as $key => $val) {
				$$key = mysql_real_escape_string($val);
			}
			
			// Copy it to the saved versions
			sqlquery("INSERT INTO bigtree_page_revisions (`page`,`title`,`meta_keywords`,`meta_description`,`template`,`external`,`new_window`,`resources`,`callouts`,`author`,`updated_at`,`saved`,`saved_description`) VALUES ('$page','$title','$meta_keywords','$meta_description','$template','$external','$new_window','$resources','$callouts','$last_edited_by','$updated_at','on','$description')");
			
			return sqlid();
		}
		
		/*
			Function: search404s
				Searches 404s, returns results.
			
			Parameters:
				type - The type of results (301, 404, or ignored).
				query - The search query.
			
			Returns:
				An array of entries from bigtree_404s.
		*/
		
		function search404s($type,$query = "") {
			$items = array();
			
			if ($query) {
				$s = mysql_real_escape_string($query);
				if ($type == "301") {
					$q = sqlquery("SELECT * FROM bigtree_404s WHERE ignored = '' AND (broken_url LIKE '%$s%' OR redirect_url LIKE '%$s%') AND redirect_url != '' ORDER BY requests DESC LIMIT 50");
				} elseif ($type == "ignored") {
					$q = sqlquery("SELECT * FROM bigtree_404s WHERE ignored != '' AND (broken_url LIKE '%$s%' OR redirect_url LIKE '%$s%') ORDER BY requests DESC LIMIT 50");
				} else {
					$q = sqlquery("SELECT * FROM bigtree_404s WHERE ignored = '' AND broken_url LIKE '%$s%' AND redirect_url = '' ORDER BY requests DESC LIMIT 50");
				}
			} else {
				if ($type == "301") {
					$q = sqlquery("SELECT * FROM bigtree_404s WHERE ignored = '' AND redirect_url != '' ORDER BY requests DESC LIMIT 50");
				} elseif ($type == "ignored") {
					$q = sqlquery("SELECT * FROM bigtree_404s WHERE ignored != '' ORDER BY requests DESC LIMIT 50");
				} else {
					$q = sqlquery("SELECT * FROM bigtree_404s WHERE ignored = '' AND redirect_url = '' ORDER BY requests DESC LIMIT 50");
				}
			}
			
			while ($f = sqlfetch($q)) {
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
		
		function searchPages($query,$fields = array("nav_title"),$max = 10) {
			$results = array();
			$terms = explode(" ",$query);
	
			foreach ($terms as $term) {
				$term = mysql_real_escape_string(strtolower($term));
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

		function searchResources($query, $sort = "date DESC") {
			$query = mysql_real_escape_string($query);
			$folders = array();
			$resources = array();
			$permission_cache = array();

			$q = sqlquery("SELECT * FROM bigtree_resource_folders WHERE name LIKE '%$query%' ORDER BY name");
			while ($f = sqlfetch($q)) {
				$f["permission"] = $this->getResourceFolderPermission($f);
				// We're going to cache the folder permissions so we don't have to fetch them a bunch of times if many files have the same folder.
				$permission_cache[$f["id"]] = $f["permission"];

				$folders[] = $f;
			}

			$q = sqlquery("SELECT * FROM bigtree_resources WHERE name LIKE '%$query%' ORDER BY $sort");
			while ($f = sqlfetch($q)) {
				// If we've already got the permission cahced, use it.  Otherwise, fetch it and cache it.
				if ($permission_cache[$f["folder"]]) {
					$f["permission"] = $permission_cache[$f["folder"]];
				} else {
					$f["permission"] = $this->getResourceFolderPermission($f["folder"]);
					$permission_cache[$f["folder"]] = $f["permission"];
				}

				$resources[] = $f;
			}

			return array("folders" => $folders, "resources" => $resources);
		}
		
		/*
			Function: searchTags
				Finds existing tags that are similar.
			
			Parameters:
				tag - A tag to find similar tags for.
			
			Returns:
				An array of up to 8 similar tags.
		*/
		
		function searchTags($tag) {
			$tags = array();
			$meta = metaphone($tag);
			$close_tags = array();
			$dist = array();
			$q = sqlquery("SELECT * FROM bigtree_tags");
			while ($f = sqlfetch($q)) {
				$distance = levenshtein($f["metaphone"],$meta);
				if ($distance < 2) {
					$tags[] = $f["tag"];
					$dist[] = $distance;
				}
			}
			
			array_multisort($dist,SORT_ASC,$tags);
			
			if (count($tags) > 8) {
				$tags = array_slice($tags,0,8);
			}
			
			return $tags;
		}
		
		/*
			Function: set404Redirect
				Sets the redirect address for a 404.
				Checks permissions.
			
			Parameters:
				id - The id of the 404.
				url - The redirect URL.
		*/
		
		function set404Redirect($id,$url) {
			$this->requireLevel(1);
			$id = mysql_real_escape_string($id);
			$url = mysql_real_escape_string($url);
			sqlquery("UPDATE bigtree_404s SET redirect_url = '$url' WHERE id = '$id'");
		}
		
		/*
			Function: setCalloutPosition
				Sets the position of a callout.
			
			Parameters:
				id - The id of the callout.
				position - The position to set.
		*/
		
		function setCalloutPosition($id,$position) {
			$id = mysql_real_escape_string($id);
			$position = mysql_real_escape_string($position);
			sqlquery("UPDATE bigtree_callouts SET position = '$position' WHERE id = '$id'");
		}
		
		/*
			Function: setModuleActionPosition
				Sets the position of a module action.
			
			Parameters:
				id - The id of the module action.
				position - The position to set.
		*/
		
		function setModuleActionPosition($id,$position) {
			$id = mysql_real_escape_string($id);
			$position = mysql_real_escape_string($position);
			sqlquery("UPDATE bigtree_module_actions SET position = '$position' WHERE id = '$id'");
		}
		
		/*
			Function: setModuleGroupPosition
				Sets the position of a module group.
			
			Parameters:
				id - The id of the module group.
				position - The position to set.
		*/
		
		function setModuleGroupPosition($id,$position) {
			$id = mysql_real_escape_string($id);
			$position = mysql_real_escape_string($position);
			sqlquery("UPDATE bigtree_module_groups SET position = '$position' WHERE id = '$id'");
		}
		
		/*
			Function: setModulePosition
				Sets the position of a module.
			
			Parameters:
				id - The id of the module.
				position - The position to set.
		*/
		
		function setModulePosition($id,$position) {
			$id = mysql_real_escape_string($id);
			$position = mysql_real_escape_string($position);
			sqlquery("UPDATE bigtree_modules SET position = '$position' WHERE id = '$id'");
		}
		
		/*
			Function: setPagePosition
				Sets the position of a page.
			
			Parameters:
				id - The id of the page.
				position - The position to set.
		*/
		
		function setPagePosition($id,$position) {
			$id = mysql_real_escape_string($id);
			$position = mysql_real_escape_string($position);
			sqlquery("UPDATE bigtree_pages SET position = '$position' WHERE id = '$id'");
		}
		
		/*
			Function: setPasswordHashForUser
				Creates a change password hash for a user.
			
			Parameters:
				user - A user entry.
			
			Returns:
				A change password hash.
		*/
		
		function setPasswordHashForUser($user) {
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
		
		function setTemplatePosition($id,$position) {
			$id = mysql_real_escape_string($id);
			$position = mysql_real_escape_string($position);
			sqlquery("UPDATE bigtree_templates SET position = '$position' WHERE id = '$id'");
		}
		
		/*
			Function: settingExists
				Determines whether a setting exists for a given id.
			
			Parameters:
				id - The setting id to check for.
			
			Returns:
				1 if the setting exists, otherwise 0.
		*/

		function settingExists($id) {
			return sqlrows(sqlquery("SELECT id FROM bigtree_settings WHERE id = '".mysql_real_escape_string($id)."'"));
		}
		
		/*
			Function: stop
				Stops processing of the Admin area and shows a message in the default layout.
			
			Parameters:
				message - Content to show (error, permission denied, etc)
		*/

		function stop($message = "") {
			global $cms,$admin,$www_root,$admin_root,$site,$breadcrumb;
			echo $message;
			$content = ob_get_clean();
			include BigTree::path("admin/layouts/default.php");
			die();
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

		function submitPageChange($page,$changes) {
			global $cms;
			
			if ($page[0] == "p") {
				// It's still pending...
				$existing_page = array();
				$pending = true;
				$type = "NEW";
			} else {
				// It's an existing page
				$pending = false;
				$existing_page = $cms->getPage($page);
				$type = "EDIT";
			}

			$template = $existing_page["template"];
			if (!$pending) {
				$existing_pending_change = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = '$page'"));
			} else {
				$existing_pending_change = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE id = '".substr($page,1)."'"));
			}
			
			// Save tags separately
			$tags = mysql_real_escape_string(json_encode($changes["_tags"]));
			unset($changes["_tags"]);

			// If there's already a change in the queue, update it with this latest info.
			if ($existing_pending_change) {
				$comments = json_decode($f["comments"],true);
				if ($existing_pending_change["user"] == $this->ID) {
					$comments[] = array(
						"user" => "BigTree",
						"date" => date("F j, Y @ g:ia"),
						"comment" => "A new revision has been made."
					);
				} else {
					$user = $this->getUser($this->ID);
					$comments[] = array(
						"user" => "BigTree",
						"date" => date("F j, Y @ g:ia"),
						"comment" => "A new revision has been made.  Owner switched to ".$user["name"]."."
					);
				}
				
				// If this is a pending change, just replace all the changes
				if ($pending) {
					$changes = mysql_real_escape_string(json_encode($changes));
				// Otherwise, we need to check what's changed.
				} else {
					$original_changes = json_decode($existing_pending_change["changes"],true);
					if (isset($original_changes["template"])) {
						$template = $original_changes["template"];
					}
					if (isset($changes["external"])) {
						$changes["external"] = $this->makeIPL($changes["external"]);
					}

					foreach ($changes as $key => $val) {
						if ($val != $existing_page[$key] && isset($existing_page[$key])) {
							$original_changes[$key] = $val;
						}
					}

					$changes = mysql_real_escape_string(json_encode($original_changes));
				}

				$comments = mysql_real_escape_string(json_encode($comments));
				sqlquery("UPDATE bigtree_pending_changes SET comments = '$comments', changes = '$changes', tags_changes = '$tags', date = NOW(), user = '".$this->ID."', type = '$type' WHERE id = '".$existing_pending_change["id"]."'");
				
				$this->track("bigtree_pages",$page,"updated-draft");

			// We're submitting a change to a presently published page with no pending changes.
			} else {
				$original_changes = array();

				foreach ($changes as $key => $val) {
					if ($key == "external") {
						$val = $this->makeIPL($val);
					}

					if (isset($existing_page[$key]) && $val != $existing_page[$key]) {
						$original_changes[$key] = $val;
					}
				}

				$changes = mysql_real_escape_string(json_encode($original_changes));
				if ($type == "DELETE") {
					sqlquery("INSERT INTO bigtree_pending_changes (`user`,`date`,`table`,`item_id`,`changes`,`type`,`title`) VALUES ('".$this->ID."',NOW(),'bigtree_pages','$page','$changes','DELETE','Page Deletion Pending')");
				} else {
					sqlquery("INSERT INTO bigtree_pending_changes (`user`,`date`,`table`,`item_id`,`changes`,`tags_changes`,`type`,`title`) VALUES ('".$this->ID."',NOW(),'bigtree_pages','$page','$changes','$tags','EDIT','Page Change Pending')");
				}

				$this->track("bigtree_pages",$page,"saved-draft");
			}

			return sqlid();
		}
		
		/*
			Function: track
				Logs a user's actions to the audit trail table.
			
			Parameters:
				table - The table affected by the user.
				entry - The primary key of the entry affected by the user.
				type - The action taken by the user (delete, edit, create, etc.)
		*/

		function track($table,$entry,$type) {
			$table = mysql_real_escape_string($table);
			$entry = mysql_real_escape_string($entry);
			$type = mysql_real_escape_string($type);
			sqlquery("INSERT INTO bigtree_audit_trail (`table`,`user`,`entry`,`date`,`type`) VALUES ('$table','".$this->ID."','$entry',NOW(),'$type')");
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

		function unarchivePage($page) {
			global $cms;
			
			if (is_array($page)) {
				$page = mysql_real_escape_string($page["id"]);
			} else {
				$page = mysql_real_escape_string($page);
			}
			$access = $this->getPageAccessLevel($page);
			if ($access == "p" && $this->canModifyChildren($cms->getPage($page))) {
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

		function unarchivePageChildren($id) {
			$q = sqlquery("SELECT * FROM bigtree_pages WHERE parent = '$id'");
			while ($f = sqlfetch($q)) {
				if ($f["archived_inherited"]) {
					sqlquery("UPDATE bigtree_pages SET archived = '', archived_inherited = '' WHERE id = '".$f["id"]."'");
					$this->track("bigtree_pages",$f["id"],"unarchived");
					$this->archivePageChildren($f["id"]);
				}
			}
		}
		
		/*
			Function: ungrowl
				Destroys the growl session.
		*/

		function ungrowl() {
			unset($_SESSION["bigtree"]["flash"]);
		}
		
		/*
			Function: urlExists
				Attempts to connect to a URL using cURL.
			
			Parameters:
				url - The URL to connect to.
			
			Returns:
				true if it can connect, false if connection failed.
		*/

		function urlExists($url) {
			$handle = curl_init($url);
			if ($handle === false) {
				return false;
			}
			curl_setopt($handle, CURLOPT_HEADER, false);
			curl_setopt($handle, CURLOPT_FAILONERROR, true);
			// Request as Firefox so that servers don't reject us for not having headers.
			curl_setopt($handle, CURLOPT_HTTPHEADER, Array("User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.15) Gecko/20080623 Firefox/2.0.0.15") );
			curl_setopt($handle, CURLOPT_NOBODY, true);
			curl_setopt($handle, CURLOPT_RETURNTRANSFER, false);
			$connectable = curl_exec($handle);
			curl_close($handle);
			return $connectable;
		}
		
		/*
			Function: unCache
				Removes the cached copy of a given page.
				
			Parameters:
				page - Either a page id or a page entry.
		*/

		function unCache($page) {
			global $cms;
			if (is_array($page)) {
				$file = $GLOBALS["server_root"]."cache/".base64_encode($page["path"]."/");
			} else {
				$file = $GLOBALS["server_root"]."cache/".base64_encode(str_replace($GLOBALS["www_root"],"",$cms->getLink($page)));		
			}
			if (file_exists($file)) {
				@unlink($file);
			}
		}
		
		/*
			Function: unignore404
				Unignores a 404.
				Checks permissions.
			
			Parameters:
				id - The id of the 404.
		*/
		
		function unignore404($id) {
			$this->requireLevel(1);
			$id = mysql_real_escape_string($id);
			sqlquery("UPDATE bigtree_404s SET ignored = '' WHERE id = '$id'");
		}
		
		/*
			Function: unlock
				Removes a lock from a table entry.
			
			Parameters:
				table - The table the entry is in.
				id - The id of the entry.
		*/
		
		function unlock($table,$id) {
			sqlquery("DELETE FROM bigtree_locks WHERE `table` = '".mysql_real_escape_string($table)."' AND item_id = '".mysql_real_escape_string($id)."'");
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
		*/
		
		function updateCallout($id,$name,$description,$level,$resources) {
			$r = array();
			foreach ($resources as $resource) {
				if ($resource["id"] && $resource["id"] != "type") {
					$options = json_decode($resource["options"],true);
					foreach ($options as $key => $val) {
						if ($key != "name" && $key != "id" && $key != "type")
							$resource[$key] = $val;
					}
					$resource["id"] = htmlspecialchars($resource["id"]);
					$resource["name"] = htmlspecialchars($resource["name"]);
					$resource["subtitle"] = htmlspecialchars($resource["subtitle"]);
					unset($resource["options"]);
					$r[] = $resource;
				}
			}
			
			$id = mysql_real_escape_string($id);
			$name = mysql_real_escape_string(htmlspecialchars($name));
			$description = mysql_real_escape_string(htmlspecialchars($description));
			$level = mysql_real_escape_string($level);
			$resources = mysql_real_escape_string(json_encode($r));
			
			sqlquery("UPDATE bigtree_callouts SET resources = '$resources', name = '$name', description = '$description', level = '$level' WHERE id = '$id'");
		}
		
		/*
			Function: updateChildPagePaths
				Updates the paths for pages who are descendants of a given page to reflect the page's new route.
				Also sets route history if the page has changed paths.
			
			Parameters:
				page - The page id.
		*/

		function updateChildPagePaths($page) {
			global $cms;
			
			$page = mysql_real_escape_string($page);
			$q = sqlquery("SELECT * FROM bigtree_pages WHERE parent = '$page'");
			while ($f = sqlfetch($q)) {
				$oldpath = $f["path"];
				$path = $this->getFullNavigationPath($f["id"]);
				if ($oldpath != $path) {
					sqlquery("DELETE FROM bigtree_route_history WHERE old_route = '$path' OR old_route = '$oldpath'");
					sqlquery("INSERT INTO bigtree_route_history (`old_route`,`new_route`) VALUES ('$oldpath','$path')");
					sqlquery("UPDATE bigtree_pages SET path = '$path' WHERE id = '".$f["id"]."'");
					$this->updateChildPagePaths($f["id"]);
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
				options - The feed type options.
				fields - The fields.
		*/
		
		function updateFeed($id,$name,$description,$table,$type,$options,$fields) {
			$options = json_decode($options,true);
			foreach ($options as &$option) {
				$option = str_replace($www_root,"{wwwroot}",$option);
			}
			
			// Fix stuff up for the db.
			$id = mysql_real_escape_string($id);
			$name = mysql_real_escape_string(htmlspecialchars($name));
			$description = mysql_real_escape_string(htmlspecialchars($description));
			$table = mysql_real_escape_string($table);
			$type = mysql_real_escape_string($type);
			$options = mysql_real_escape_string(json_encode($options));
			$fields = mysql_real_escape_string(json_encode($fields));
			
			sqlquery("UPDATE bigtree_feeds SET name = '$name', description = '$description', `table` = '$table', type = '$type', fields = '$fields', options = '$options' WHERE id = '$id'");
		}
		
		/*
			Function: updateFieldType
				Updates a field type.
			
			Parameters:
				id - The id of the field type.
				name - The name.
				pages - Whether it can be used as a page resource or not ("on" is yes)
				modules - Whether it can be used as a module resource or not ("on" is yes)
				callouts - Whether it can be used as a callout resource or not ("on" is yes)
		*/
		
		function updateFieldType($id,$name,$pages,$modules,$callouts) {
			$id = mysql_real_escape_string($id);
			$name = mysql_real_escape_string(htmlspecialchars($name));
			$pages = mysql_real_escape_string($pages);
			$modules = mysql_real_escape_string($modules);
			$callouts = mysql_real_escape_string($callouts);
			
			sqlquery("UPDATE bigtree_field_types SET name = '$name', pages = '$pages', modules = '$modules', callouts = '$callouts' WHERE id = '$id'");
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
		*/
		
		function updateModule($id,$name,$group,$class,$permissions) {
			$id = mysql_real_escape_string($id);
			$name = mysql_real_escape_string(htmlspecialchars($name));
			$group = mysql_real_escape_string($group);
			$class = mysql_real_escape_string($class);
			$permissions = mysql_real_escape_string(json_encode($permissions));
			sqlquery("UPDATE bigtree_modules SET name = '$name', `group` = '$group', class = '$class', `gbp` = '$permissions' WHERE id = '$id'");
		
			// Remove cached class list.
			unlink($GLOBALS["server_root"]."cache/module-class-list.btc");
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
		*/
		
		function updateModuleAction($id,$name,$route,$in_nav,$icon) {
			$id = mysql_real_escape_string($id);
			$route = mysql_real_escape_string(htmlspecialchars($route));
			$in_nav = mysql_real_escape_string($in_nav);
			$icon = mysql_real_escape_string($icon);
			$name = mysql_real_escape_string(htmlspecialchars($name));
			
			$item = $this->getModuleAction($id);

			$oroute = $route;
			$x = 2;
			while ($f = sqlfetch(sqlquery("SELECT * FROM bigtree_module_actions WHERE module = '".$item["module"]."' AND route = '$route' AND id != '$id'"))) {
				$route = $oroute."-".$x;
				$x++;
			}
			
			sqlquery("UPDATE bigtree_module_actions SET name = '$name', route = '$route', class = '$icon', in_nav = '$in_nav' WHERE id = '$id'");
		}
		
		/*
			Function: updateModuleForm
				Updates a module form.
			
			Parameters:
				id - The id of the form.
				title - The title of the form.
				table - The table for the form data.
				fields - The form fields.
				javascript - Optional Javascript file to include in the form.
				css - Optional CSS file to include in the form.
				callback - Optional callback function to run after the form processes.
				default_position - Default position for entries to the form (if the view is positioned).
				suffix - Optional add/edit suffix for the form.
		*/
		
		function updateModuleForm($id,$title,$table,$fields,$javascript = "",$css = "",$callback = "",$default_position = "",$suffix = "") {
			$id = mysql_real_escape_string($id);
			$title = mysql_real_escape_string(htmlspecialchars($title));
			$table = mysql_real_escape_string($table);
			$fields = mysql_real_escape_string(json_encode($fields));
			$javascript - mysql_real_escape_string(htmlspecialchars($javascript));
			$css - mysql_real_escape_string(htmlspecialchars($css));
			$callback - mysql_real_escape_string($callback);
			$default_position - mysql_real_escape_string($default_position);
			
			sqlquery("UPDATE bigtree_module_forms SET title = '$title', `table` = '$table', fields = '$fields', javascript = '$javascript', css = '$css', callback = '$callback', default_position = '$default_position' WHERE id = '$id'");
			
			$oroute = str_replace(array("add-","edit-","add","edit"),"",$action["route"]);
			if ($suffix != $oroute) {
				sqlquery("UPDATE bigtree_module_actions SET route = 'add-$suffix' WHERE module = '".$action["module"]."' AND route = 'add-$oroute'");
				sqlquery("UPDATE bigtree_module_actions SET route = 'edit-$suffix' WHERE module = '".$action["module"]."' AND route = 'edit-$oroute'");
			}
		}
		
		/*
			Function: updateModuleGroup
				Updates a module group's name.
			
			Parameters:
				id - The id of the module group to update.
				name - The name of the module group.
		*/
		
		function updateModuleGroup($id,$name,$in_nav) {
			global $cms;
			
			$id = mysql_real_escape_string($id);
			$name = mysql_real_escape_string(htmlspecialchars($name));
			
			// Get a unique route
			$x = 2;
			$route = $cms->urlify($name);
			$oroute = $route;
			$q = sqlquery("SELECT * FROM bigtree_module_groups WHERE route = '" . mysql_real_escape_string($route) . "'");
			while ($g = sqlfetch($q)) {
				if ($g["id"] != $id) {
					$route = $oroute."-".$x;
					$x++;
				}
			}
			
			// Just to be safe
			$route = mysql_real_escape_string($route);
			
			sqlquery("UPDATE bigtree_module_groups SET name = '$name', route = '$route', in_nav = '$in_nav' WHERE id = '$id'");
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
				options - View options array.
				fields - Field array.
				actions - Actions array.
				suffix - Add/Edit suffix.
				uncached - Don't cache the view.
				preview_url - Optional preview URL.
				
			Returns:
				The id for view.
		*/
		
		function updateModuleView($id,$title,$description,$table,$type,$options,$fields,$actions,$suffix,$uncached = "",$preview_url = "") {
			$id = mysql_real_escape_string($id);
			$title = mysql_real_escape_string(htmlspecialchars($title));
			$description = mysql_real_escape_string(htmlspecialchars($description));
			$table = mysql_real_escape_string($table);
			$type = mysql_real_escape_string($type);
			$options = mysql_real_escape_string(json_encode($options));
			$fields = mysql_real_escape_string(json_encode($fields));
			$actions = mysql_real_escape_string(json_encode($actions));
			$suffix = mysql_real_escape_string($suffix);
			$uncached = mysql_real_escape_string($uncached);
			$preview_url = mysql_real_escape_string(htmlspecialchars($preview_url));
			
			sqlquery("UPDATE bigtree_module_views SET title = '$title', description = '$description', `table` = '$table', type = '$type', options = '$options', fields = '$fields', actions = '$actions', suffix = '$suffix', uncached = '$uncached', preview_url = '$preview_url' WHERE id = '$id'");
		}
		
		/*
			Function: updateModuleViewFields
				Updates the fields for a module view.
			
			Paramters:
				view - The view id.
				fields - A fields array.
		*/
		
		function updateModuleViewFields($view,$fields) {
			$view = mysql_real_escape_string($view);
			$fields = mysql_real_escape_string(json_encode($fields));
			sqlquery("UPDATE bigtree_module_views SET `fields` = '$fields' WHERE id = '$view'");
		}
		
		/*
			Function: updatePage
				Updates a page.
				Does not check permissions.
			
			Paramters:
				page - The page id to update.
				data - The page data to update with.	
		*/

		function updatePage($page,$data) {
			global $cms;

			$page = mysql_real_escape_string($page);

			// Save the existing copy as a draft, remove drafts for this page that are one month old or older.
			sqlquery("DELETE FROM bigtree_page_revisions WHERE page = '$page' AND updated_at < '".date("Y-m-d",strtotime("-31 days"))."' AND saved != 'on'");
			// Get the current copy
			$current = sqlfetch(sqlquery("SELECT * FROM bigtree_pages WHERE id = '$page'"));
			foreach ($current as $key => $val) {
				$$key = mysql_real_escape_string($val);
			}
			// Copy it to the saved versions
			sqlquery("INSERT INTO bigtree_page_revisions (`page`,`title`,`meta_keywords`,`meta_description`,`template`,`external`,`new_window`,`resources`,`callouts`,`author`,`updated_at`) VALUES ('$page','$title','$meta_keywords','$meta_description','$template','$external','$new_window','$resources','$callouts','$last_edited_by','$updated_at')");

			// Remove this page from the cache
			$this->unCache($page);

			// Set local variables in a clean fashion that prevents _SESSION exploitation.  Also, don't let them somehow overwrite $page and $current.
			foreach ($data as $key => $val) {
				if (substr($key,0,1) != "_" && $key != "current" && $key != "page") {
					if (is_array($val)) {
						$$key = mysql_real_escape_string(json_encode($val));
					} else {
						$$key = mysql_real_escape_string($val);
					}
				}
			}
			$in_nav = mysql_real_escape_string($data["in_nav"]);
			$redirect_lower = mysql_real_escape_string($data["redirect_lower"]);

			// Make an ipl:// or {wwwroot}'d version of the URL
			if ($external) {
				$external = $this->makeIPL($external);
			}

			// If somehow we didn't provide a parent page (like, say, the user didn't have the right to change it) then pull the one from before.  Actually, this might be exploitable… look into it later.
			if (!isset($data["parent"])) {
				$parent = $current["parent"];
			}

			// Create a route if we don't have one, otherwise, make sure the one they provided doesn't suck.
			$route = $data["route"];
			if (!$route) {
				$route = $cms->urlify($data["nav_title"]);
			} else {
				$route = $cms->urlify($route);
			}

			// Get a unique route
			$oroute = $route;
			$x = 2;
			// Reserved paths.
			if ($parent == 0) {
				while (file_exists($GLOBALS["server_root"]."site/".$route."/")) {
					$route = $oroute."-".$x;
					$x++;
				}
			}
			// Existing pages.
			$f = sqlfetch(sqlquery("SELECT id FROM bigtree_pages WHERE `route` = '$route' AND parent = '$parent' AND id != '$page'"));
			while ($f) {
				$route = $oroute."-".$x;
				$f = sqlfetch(sqlquery("SELECT id FROM bigtree_pages WHERE `route` = '$route' AND parent = '$parent' AND id != '$page'"));
				$x++;
			}

			// We have no idea how this affects the nav, just wipe it all.
			if ($current["nav_title"] != $nav_title || $current["route"] != $route || $current["in_nav"] != $in_nav || $current["parent"] != $parent) {
				$this->clearCache();
			}

			// Make sure we set the publish date to NULL if it wasn't provided or we'll have a page that got published at 0000-00-00
			if ($publish_at) {
				$publish_at = "'".date("Y-m-d",strtotime($publish_at))."'";
			} else {
				$publish_at = "NULL";
			}

			// Same goes for the expiration date.
			if ($expire_at) {
				$expire_at = "'".date("Y-m-d",strtotime($expire_at))."'";
			} else {
				$expire_at = "NULL";
			}

			// Set the full path, saves DB access time on the front end.
			if ($parent) {
				$path = $this->getFullNavigationPath($parent)."/".$route;
			} else {
				$path = $route;
			}

			// htmlspecialchars stuff so that it doesn't need to be re-encoded when echo'd on the front end.
			$title = htmlspecialchars($title);
			$nav_title = htmlspecialchars($nav_title);
			$meta_description = htmlspecialchars($meta_description);
			$meta_keywords = htmlspecialchars($meta_keywords);
			$external = htmlspecialchars($external);

			// Update the database
			sqlquery("UPDATE bigtree_pages SET `parent` = '$parent', `nav_title` = '$nav_title', `route` = '$route', `path` = '$path', `in_nav` = '$in_nav', `title` = '$title', `template` = '$template', `external` = '$external', `new_window` = '$new_window', `resources` = '$resources', `callouts` = '$callouts', `meta_keywords` = '$meta_keywords', `meta_description` = '$meta_description', `last_edited_by` = '".$this->ID."', updated_at = NOW(), publish_at = $publish_at, expire_at = $expire_at, max_age = '$max_age' WHERE id = '$page'");

			// Remove any pending drafts
			sqlquery("DELETE FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = '$page'");

			// Remove old paths from the redirect list
			sqlquery("DELETE FROM bigtree_route_history WHERE old_route = '$path' OR old_route = '".$current["path"]."'");

			// Create an automatic redirect from the old path to the new one.
			if ($current["path"] != $path) {
				sqlquery("INSERT INTO bigtree_route_history (`old_route`,`new_route`) VALUES ('$oldpath','$newpath')");

				// Update all child page routes, ping those engines, clean those caches
				$this->updateChildPagePaths($page);
				$this->pingSearchEngines();
				$this->clearCache();
			}

			// Handle tags
			sqlquery("DELETE FROM bigtree_tags_rel WHERE module = '0' AND entry = '$page'");
			if (is_array($data["_tags"])) {
				foreach ($data["_tags"] as $tag) {
					sqlquery("INSERT INTO bigtree_tags_rel (`module`,`entry`,`tag`) VALUES ('0','$page','$tag')");
				}
			}
			
			// Audit trail.
			$this->track("bigtree_pages",$page,"updated");

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
		
		function updatePageParent($page,$parent) {
			$page = mysql_real_escape_string($page);
			$parent = mysql_real_escape_string($parent);
			
			if ($this->Level < 1) {
				$this->stop("You are not allowed to move pages.");
			}
			
			sqlquery("UPDATE bigtree_pages SET parent = '$parent' WHERE id = '$page'");
			$path = $this->getFullNavigationPath($page);
			sqlquery("UPDATE bigtree_pages SET path = '".mysql_real_escape_string($path)."' WHERE id = '$page'");
			$this->updateChildPagePaths($page);
		}
		
		/*
			Function: updatePageRevision
				Updates a page revision to save it as a favorite.
				Checks permissions.
			
			Parameters:
				id - The page revision id.
				description - Saved description.
		*/
		
		function updatePageRevision($id,$description) {
			// Get the version, check if the user has access to the page the version refers to.
			$revision = $this->getPageRevision($id);
			$access = $this->getPageAccessLevel($revision["page"]);
			if ($access != "p") {
				$this->stop("You must be a publisher to manage revisions.");
			}
			
			// Save the version's description and saved status
			$description = mysql_real_escape_string(htmlspecialchars($description));
			sqlquery("UPDATE bigtree_page_revisions SET saved = 'on', saved_description = '$description' WHERE id = '".$revision["id"]."'");
		}
		
		/*
			Function: updatePendingChange
				Updated a pending change.
			
			Parameters:
				id - The id of the pending change.
				changes - The changes to the fields in the entry.
				mtm_changes - Many to Many changes.
				tags_changes - Tags changes.
		*/
		
		function updatePendingChange($id,$changes,$mtm_changes = array(),$tags_changes = array()) {
			$id = mysql_real_escape_string($id);
			$changes = mysql_real_escape_string(json_encode($changes));
			$mtm_changes = mysql_real_escape_string(json_encode($mtm_changes));
			$tags_changes = mysql_real_escape_string(json_encode($tags_changes));
			
			sqlquery("UPDATE bigtree_pending_changes SET changes = '$changes', mtm_changes = '$mtm_changes', tags_changes = '$tags_changes', date = NOW(), author = '".$this->ID."' WHERE id = '$id'");
		}

		/*
			Function: updateProfile
				Updates a user's name, company, digest setting, and (optionally) password.

			Parameters:
				data - Array containing name / company / daily_digest / password.
		*/

		function updateProfile($data) {
			global $config;

			foreach ($data as $key => $val) {
				if (substr($key,0,1) != "_" && !is_array($val)) {
					$$key = mysql_real_escape_string($val);
				}
			}

			$id = mysql_real_escape_string($this->ID);

			if ($data["password"]) {
				$phpass = new PasswordHash($config["password_depth"], TRUE);
				$password = mysql_real_escape_string($phpass->HashPassword($data["password"]));
				sqlquery("UPDATE bigtree_users SET `password` = '$password', `name` = '$name', `company` = '$company', `daily_digest` = '$daily_digest' WHERE id = '$id'");
			} else {
				sqlquery("UPDATE bigtree_users SET `name` = '$name', `company` = '$company', `daily_digest` = '$daily_digest' WHERE id = '$id'");
			}
		}
		
		/*
			Function: updateResource
				Updates a resource.
			
			Parameters:
				id - The id of the resource.
				name - The name of the resource.
		*/
		
		function updateResource($id,$name) {
			$id = mysql_real_escape_string($id);
			$name = mysql_real_escape_string(htmlspecialchars($title));
			sqlquery("UPDATE bigtree_resources SET name = '$name' WHERE id = '$id'");
		}
		
		/*
			Function: updateSetting
				Updates a setting.
			
			Parameters:
				old_id - The current id of the setting to update.
				data - The new data for the setting ("id", "type", "name", "description", "locked", "encrypted")
			
			Returns:
				true if successful, false if a setting exists for the new id already.
		*/

		function updateSetting($old_id,$data) {
			global $config;
			
			// Get the existing setting information.
			$existing = $this->getSetting($old_id);
			$old_id = mysql_real_escape_string($old_id);
			
			// Globalize the data and clean it up.
			foreach ($data as $key => $val) {
				if (substr($key,0,1) != "_" && !is_array($val)) {
					$$key = mysql_real_escape_string(htmlspecialchars($val));
				}
			}
			
			// We don't want this encoded since it's a WYSIWYG field.
			$description = mysql_real_escape_string($data["description"]);
			
			// See if we have an id collision with the new id.
			if ($old_id != $id && $this->settingExists($id)) {
				return false;
			}
			
			sqlquery("UPDATE bigtree_settings SET id = '$id', type = '$type', name = '$name', description = '$description', locked = '$locked', encrypted = '$encrypted' WHERE id = '$old_id'");

			// If encryption status has changed, update the value
			if ($existing["encrypted"] && !$encrypted) {
				sqlquery("UPDATE bigtree_settings SET value = AES_DECRYPT(value,'".mysql_real_escape_string($config["settings_key"])."') WHERE id = '$id'");
			}
			if (!$existing["encrypted"] && $encrypted) {
				sqlquery("UPDATE bigtree_settings SET value = AES_ENCRYPT(value,'".mysql_real_escape_string($config["settings_key"])."') WHERE id = '$id'");
			}
			
			// Audit trail.
			$this->track("bigtree_settings",$id,"updated");

			return true;
		}
		
		/*
			Function: updateSettingValue
				Updates the value of a setting.
			
			Paramters:
				id - The id of the setting to update.
				value - A value to set (can be a string or array).
		*/

		function updateSettingValue($id,$value) {
			global $config;
			$item = $this->getSetting($id);
			$id = mysql_real_escape_string($id);

			$value = mysql_real_escape_string(json_encode($value));

			if ($item["encrypted"]) {
				sqlquery("UPDATE bigtree_settings SET `value` = AES_ENCRYPT('$value','".mysql_real_escape_string($config["settings_key"])."') WHERE id = '$id'");
			} else {
				sqlquery("UPDATE bigtree_settings SET `value` = '$value' WHERE id = '$id'");
			}
			
			// Audit trail
			$this->track("bigtree_settings",$id,"updated-value");
		}
		
		/*
			Function: updateTemplate
				Updates a template.
		
			Paremeters:
				id - The id of the template to update.
				name - Name
				description - Description
				level - Access level (0 for everyone, 1 for administrators, 2 for developers)
				module - Related module id
				image - Image
				callouts_enabled - "on" for yes
				resources - An array of resources
		*/
		
		function updateTemplate($id,$name,$description,$level,$module,$image,$callouts_enabled,$resources) {
			$clean_resources = array();
			foreach ($resources as $resource) {
			    if ($resource["id"]) {
			    	$options = json_decode($resource["options"],true);
			    	foreach ($options as $key => $val) {
			    		if ($key != "title" && $key != "id" && $key != "subtitle" && $key != "type") {
			    			$resource[$key] = $val;
			    		}
			    	}
			    	
			    	$resource["id"] = htmlspecialchars($resource["id"]);
			    	$resource["title"] = htmlspecialchars($resource["title"]);
			    	$resource["subtitle"] = htmlspecialchars($resource["subtitle"]);
			    	unset($resource["options"]);
			    	$clean_resources[] = $resource;
			    }
			}
			
			$id = mysql_real_escape_string($id);
			$name = mysql_real_escape_string(htmlspecialchars($name));
			$description = mysql_real_escape_string(htmlspecialchars($description));
			$module = mysql_real_escape_string($module);
			$resources = mysql_real_escape_string(json_encode($clean_resources));
			$image = mysql_real_escape_string($image);
			$level = mysql_real_escape_string($level);
			$callouts_enabled = mysql_real_escape_string($callouts_enabled);
			
			sqlquery("UPDATE bigtree_templates SET resources = '$resources', image = '$image', name = '$name', module = '$module', description = '$description', level = '$level', callouts_enabled = '$callouts_enabled' WHERE id = '$id'");
		}

		/*
			Function: updateUser
				Updates a user.

			Parameters:
				id - The user's "id"
				data - A key/value array containing email, name, company, level, permissions, alerts, daily_digest, and (optionally) password.

			Returns:
				True if successful.  False if the logged in user doesn't have permission to change the user or there was an email collision.
		*/

		function updateUser($id,$data) {
			global $config;
			$id = mysql_real_escape_string($id);

			// See if there's an email collission
			$r = sqlrows(sqlquery("SELECT * FROM bigtree_users WHERE email = '".mysql_real_escape_string($data["email"])."' AND id != '$id'"));
			if ($r) {
				return false;
			}


			// If this person has higher access levels than the person trying to update them, fail.
			$current = $this->getUser($id);
			if ($current["level"] > $this->Level) {
				return false;
			}

			// If we didn't pass in a level because we're editing ourselves, use the current one.
			if (!$level || $this->ID == $current["id"]) {
				$level = $current["level"];
			}

			foreach ($data as $key => $val) {
				if (substr($key,0,1) != "_" && !is_array($val)) {
					$$key = mysql_real_escape_string($val);
				}
			}

			$permissions = mysql_real_escape_string(json_encode($data["permissions"]));
			$alerts = mysql_real_escape_string(json_encode($data["alerts"]));

			if ($data["password"]) {
				$phpass = new PasswordHash($config["password_depth"], TRUE);
				$password = mysql_real_escape_string($phpass->HashPassword($data["password"]));
				sqlquery("UPDATE bigtree_users SET `email` = '$email', `password` = '$password', `name` = '$name', `company` = '$company', `level` = '$level', `permissions` = '$permissions', `alerts` = '$alerts', `daily_digest` = '$daily_digest' WHERE id = '$id'");
			} else {
				sqlquery("UPDATE bigtree_users SET `email` = '$email', `name` = '$name', `company` = '$company', `level` = '$level', `permissions` = '$permissions', `alerts` = '$alerts', `daily_digest` = '$daily_digest' WHERE id = '$id'");
			}

			$this->track("bigtree_users",$id,"updated");

			return true;
		}
		
		/*
			Function: updateUserPassword
				Updates a user's password.
			
			Parameters:
				id - The user's id.
				password - The new password.
		*/
		
		function updateUserPassword($id,$password) {
			global $config;
			
			$id = mysql_real_escape_string($id);
			$phpass = new PasswordHash($config["password_depth"], TRUE);
			$password = mysql_real_escape_string($phpass->HashPassword($password));
			sqlquery("UPDATE bigtree_users SET password = '$password' WHERE id = '$id'");
		}
	}
?>