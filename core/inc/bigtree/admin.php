<?php
	/*
		Class: BigTreeAdmin
			The main class used by the admin for manipulating and retrieving data.
	*/

	class BigTreeAdminBase {

		// Static variables
		public static $ActionClasses = array("add","delete","list","edit","refresh","gear","truck","token","export","redirect","help","error","ignored","world","server","clock","network","car","key","folder","calendar","search","setup","page","computer","picture","news","events","blog","form","category","map","done","warning","user","question","sports","credit_card","cart","cash_register","lock_key","bar_graph","comments","email","weather","pin","planet","mug","atom","shovel","cone","lifesaver","target","ribbon","dice","ticket","pallet","lightning","camera","video","twitter","facebook","trail","crop","cloud","phone","music","house","featured","heart","link","flag","bug","games","coffee","airplane","bank","gift","badge","award","radio");
		public static $CronPlugins = array();
		public static $DailyDigestPlugins = array(
			"core" => array(
				"pending-changes" => array(
					"name" => "Pending Changes",
					"function" => "BigTreeAdmin::dailyDigestChanges"
				),
				"messages" => array(
					"name" => "Unread Messages",
					"function" => "BigTreeAdmin::dailyDigestMessages"
				),
				"alerts" => array(
					"name" => "Content Age Alerts",
					"function" => "BigTreeAdmin::dailyDigestAlerts"
				)
			),
			"extension" => array()
		);
		public static $DashboardPlugins = array(
			"core" => array(
				"analytics" => "Google Analytics",
				"pending-changes" => "Pending Changes",
				"messages" => "Messages"
			),
			"extension" => array()
		);
		public static $DB;
		public static $IconClasses = array("gear","truck","token","export","redirect","help","error","ignored","world","server","clock","network","car","key","folder","calendar","search","setup","page","computer","picture","news","events","blog","form","category","map","user","question","sports","credit_card","cart","cash_register","lock_key","bar_graph","comments","email","weather","pin","planet","mug","atom","shovel","cone","lifesaver","target","ribbon","dice","ticket","pallet","camera","video","twitter","facebook");
		public static $InterfaceTypes = array(
			"core" => array(
				"views" => array(
					"name" => "View",
					"icon" => "category",
					"description" => "Views are lists of database content. Views can have associated actions such as featuring, archiving, approving, editing, and deleting content."
				),
				"reports" => array(
					"name" => "Report",
					"icon" => "graph",
					"description" => "Reports allow your admin users to filter database content. Reports can either generate a filtered view (based on an existing View interface) or export the data to a CSV."
				),
				"forms" => array(
					"name" => "Form",
					"icon" => "form",
					"description" => "Forms are used for creating and editing database content by admin users."
				),
				"embeds" => array(
					"name" => "Embeddable Form",
					"icon" => "file_default",
					"description" => "Embeddable forms allow your front-end users to create database content using your existing field types via iframes."
				)
			),
			"extension" => array()
		);
		public static $PerPage = 15;
		public static $ReservedColumns = array(
			"id",
			"position",
			"archived",
			"approved"
		);
		public static $ReservedTLRoutes = array(
			"ajax",
			"css",
			"feeds",
			"js",
			"sitemap.xml",
			"_preview",
			"_preview-pending"
		);
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
		public static $ViewTypes = array(
			"core" => array(
				"searchable" => "Searchable List",
				"draggable" => "Draggable List",
				"nested" => "Nested Draggable List",
				"grouped" => "Grouped List",
				"images" => "Image List",
				"images-grouped" => "Grouped Image List"
			),
			"extension" => array()
		);
		
		/*
			Constructor:
				Initializes the user's permissions.
		*/

		function __construct() {
			global $bigtree;

			// Handle authentication
			$this->Auth = new BigTree\Auth;

			// Admin environment
			$this->ID = $this->Auth->ID;
			$this->User = $this->Auth->User;
			$this->Level = $this->Auth->Level;
			$this->Name = $this->Auth->Name;
			$this->Permissions = $this->Auth->Permissions;

			$extension_cache_file = SERVER_ROOT."cache/bigtree-extension-cache.json";

			// Handle extension cache
			if ($bigtree["config"]["debug"] || !file_exists($extension_cache_file)) {
				$plugins = array(
					"cron" => array(),
					"daily-digest" => array(),
					"dashboard" => array(),
					"interfaces" => array(),
					"view-types" => array()
				);

				$extension_ids = static::$DB->fetchAllSingle("SELECT id FROM bigtree_extensions");
				foreach ($extension_ids as $extension_id) {
					// Load up the manifest
					$manifest = json_decode(file_get_contents(SERVER_ROOT."extensions/$extension_id/manifest.json"),true);
					if (!empty($manifest["plugins"]) && is_array($manifest["plugins"])) {
						foreach ($manifest["plugins"] as $type => $list) {
							foreach ($list as $id => $plugin) {
								$plugins[$type][$extension_id][$id] = $plugin;
							}
						}
					}
				}

				// If no longer in debug mode, cache it
				if (!$bigtree["config"]["debug"]) {
					file_put_contents($extension_cache_file,BigTree::json($plugins));
				}
			} else {
				$plugins = json_decode(file_get_contents($extension_cache_file),true);
			}
			
			static::$CronPlugins = $plugins["cron"];
			static::$DailyDigestPlugins["extension"] = $plugins["daily-digest"];
			static::$DashboardPlugins["extension"] = $plugins["dashboard"];
			static::$InterfaceTypes["extension"] = $plugins["interfaces"];
			static::$ViewTypes["extension"] = $plugins["view-types"];

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
			list($admin_route) = explode("/",str_replace(WWW_ROOT,"",rtrim(ADMIN_ROOT,"/")));
			static::$ReservedTLRoutes[] = $admin_route;

			// Check for Per Page value
			$per_page = intval(BigTreeCMS::getSetting("bigtree-internal-per-page"));
			if ($per_page) {
				static::$PerPage = $per_page;
			}
		}

		/*
			Function: allocateResources
				Assigns resources from $this->IRLsCreated

			Parameters:
				module - Module ID to assign to
				entry - Entry ID to assign to
		*/

		static function allocateResources($module,$entry) {
			BigTree\Resource::allocate($module,$entry);
		}

		/*
			Function: archivePage
				Archives a page.
				Checks permissions.

			Parameters:
				page - Either a page id or page entry.

			Returns:
				true if successful. false if the logged in user doesn't have permission.

			See Also:
				<archivePageChildren>
		*/

		function archivePage($page) {
			$page = new BigTree\Page($page);

			// Only users with publisher access that can also modify this page's children can archive it
			if ($page->UserAccessLevel == "p" && $page->UserCanModifyChildren) {
				$page->archive();
				return true;
			}

			// No access
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
			$page = new BigTree\Page($page);
			$page->archiveChildren();
		}

		/*
			Function: autoIPL
				Automatically converts links to internal page links.

			Parameters:
				html - A string of contents that may contain URLs

			Returns:
				A string with hard links converted into internal page links.
		*/

		static function autoIPL($html) {
			return BigTree\Link::encode($html);
		}

		/*
			Function: backupDatabase
				Backs up the entire database to a given file.

			Parameters:
				file - Full file path to dump the database to.

			Returns:
				true if successful.
		*/

		static function backupDatabase($file) {
			return static::$DB->backup($file);
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

		function canAccessGroup($module,$group) {
			$module = new BigTree\Module($module);
			return $module->getGroupAccessLevel($group);
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
			$page = new BigTree\Page($page);
			return $page->UserCanModifyChildren;
		}

		/*
			Function: changePassword
				Changes a user's password via a password change hash and redirects to a success page.

			Parameters:
				hash - The unique hash generated by <forgotPassword>.
				password - The user's new password.

			Returns:
				true if successful

			See Also:
				<forgotPassword>

		*/

		static function changePassword($hash,$password) {
			$user = BigTree\User::getByHash($hash);
			if (!$user) {
				return false;
			}

			// Update password/hash
			$user->ChangePasswordHash = "";
			$user->Password = $password;
			$user->save();

			// Remove bans
			$user->removeBans();
		}

		/*
			Function: checkAccess
				Determines whether the logged in user has access to a module or not.

			Parameters:
				module - A module from the bigtree_modules table.
				action - Optionally, a module action array to also check levels against.

			Returns:
				true if the user can access the module, otherwise false.
		*/

		function checkAccess($module,$action = false) {
			if ($action) {
				$action = new ModuleAction($action);
				return $action->UserCanAccess;
			}

			$module = new Module($module);
			return $module->UserCanAccess;
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

		static function checkHTML($relative_path,$html,$external = false) {
			return BigTree\Link::integrity($relative_path,$html,$external);
		}

		/*
			Function: clearCache
				Removes all files in the cache directory.
		*/

		static function clearCache() {
			$d = opendir(SERVER_ROOT."cache/");
			while ($f = readdir($d)) {
				if ($f != "." && $f != ".." && !is_dir(SERVER_ROOT."cache/".$f)) {
					unlink(SERVER_ROOT."cache/".$f);
				}
			}
		}

		/*
			Function: clearDead404s
				Removes all 404s that don't have 301 redirects.
		*/

		function clearDead404s() {
			BigTree\Redirect::clearEmpty();
		}

		/*
			Function: create301
				Creates a 301 redirect.

			Parameters:
				from - The 404 path
				to - The 301 target
		*/

		function create301($from,$to) {
			BigTree\Redirect::create($from,$to);
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

			Returns:
				true if successful, false if an invalid ID was passed or the ID is already in use
		*/

		function createCallout($id,$name,$description,$level,$resources,$display_field,$display_default) {
			$callout = BigTree\Callout::create($id,$name,$description,$level,$resources,$display_field,$display_default);
			return $callout ? true : false;
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

		function createCalloutGroup($name,$callouts) {
			$group = BigTree\CalloutGroup::create($name,$callouts);
			return $group->ID;
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
			$feed = BigTree\Feed::create($name,$description,$table,$type,$options,$fields);
			return $feed->Route;
		}

		/*
			Function: createFieldType
				Creates a field type and its files.

			Parameters:
				id - The id of the field type.
				name - The name.
				use_cases - Associate array of sections in which the field type can be used (i.e. array("pages" => "on", "modules" => "","callouts" => "","settings" => ""))
				self_draw - Whether this field type will draw its <fieldset> and <label> ("on" or a falsey value)

			Returns:
				true if successful, false if an invalid ID was passed or the ID is already in use
		*/

		function createFieldType($id,$name,$use_cases,$self_draw) {
			$field_type = BigTree\FieldType::create($id,$name,$use_cases,$self_draw);

			return $field_type ? true : false;
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
			BigTree\Message::create($this->ID,$subject,$message,$recipients,$in_response_to);
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
				developer_only - Sets a module to be only accessible/visible to developers (defaults to false).

			Returns:
				The new module id.
		*/

		function createModule($name,$group,$class,$table,$permissions,$icon,$route = false,$developer_only = false) {
			$module = BigTree\Module::create($name,$group,$class,$table,$permissions,$icon,$route,$developer_only);
			return $module->ID;
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
				interface - Related module interface.
				level - The required access level.
				position - The position in navigation.

			Returns:
				The action's route.
		*/

		function createModuleAction($module,$name,$route,$in_nav,$icon,$interface,$level = 0,$position = 0) {
			$action = BigTree\ModuleAction::create($module,$name,$route,$in_nav,$icon,$interface,$level,$position);
			return $action->Route;
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

		function createModuleEmbedForm($module,$title,$table,$fields,$hooks = array(),$default_position = "",$default_pending = "",$css = "",$redirect_url = "",$thank_you_message = "") {
			$form = BigTree\ModuleEmbedForm::create($module,$title,$table,$fields,$hooks,$default_position,$default_pending,$css,$redirect_url,$thank_you_message);
			return htmlspecialchars($form->EmbedCode);
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

			Returns:
				The new form id.
		*/

		function createModuleForm($module,$title,$table,$fields,$hooks = array(),$default_position = "",$return_view = false,$return_url = "",$tagging = "") {
			$form = BigTree\ModuleForm::create($module,$title,$table,$fields,$hooks,$default_position,$return_view,$return_url,$tagging);
			return $form->ID;
		}

		/*
			Function: createModuleGroup
				Creates a module group.

			Parameters:
				name - The name of the group.

			Returns:
				The id of the newly created group.
		*/

		function createModuleGroup($name) {
			$group = BigTree\ModuleGroup::create($name);
			return $group->ID;
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

		function createModuleReport($module,$title,$table,$type,$filters,$fields = "",$parser = "",$view = "") {
			$interface = BigTree\ModuleInterface::create("report",$module,$title,$table,array(
				"type" => $type,
				"filters" => $filters,
				"fields" => $fields,
				"parser" => $parser,
				"view" => $view ? $view : null
			));

			return $interface->ID;
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
				options - View options array.
				fields - Field array.
				actions - Actions array.
				related_form - Form ID to handle edits.
				preview_url - Optional preview URL.

			Returns:
				The id for view.
		*/

		function createModuleView($module,$title,$description,$table,$type,$options,$fields,$actions,$related_form,$preview_url = "") {
			$view = BigTree\ModuleView::create($module,$title,$description,$table,$type,$options,$fields,$actions,$related_form,$preview_url);
			return $view->ID;
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
			// Defaults
			$parent = 0;
			$title = $nav_title = $meta_description = $meta_keywords = $external = $template = $in_nav = "";
			$seo_invisible = $publish_at = $expire_at = $trunk = $new_window = $max_age = false;
			$resources = array();

			// Loop through the posted data, make sure no session hijacking is done.
			foreach ($data as $key => $val) {
				if (substr($key,0,1) != "_") {
					$$key = $val;
				}
			}

			// Reset trunk if user isn't developer
			if ($this->Level < 2) {
				$trunk = "";
			}

			$page = BigTree\Page::create($trunk,$parent,$in_nav,$nav_title,$title,$route,$meta_description,$seo_invisible,$template,$external,$new_window,$fields,$publish_at,$expire_at,$max_age,$data["_tags"]);
			return $page->ID;
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
			$change = BigTree\PendingChange::create($table,$item_id,$changes,$mtm_changes,$tags_changes,$module);
			return $change->ID;
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
			// Set the trunk flag back to no if the user isn't a developer
			if ($this->Level < 2) {
				$data["trunk"] = "";
			} else {
				$data["trunk"] = static::$DB->escape($data["trunk"]);
			}

			$change = BigTree\PendingChange::createPage($data["trunk"],$data["parent"],$data["in_nav"],$data["nav_title"],$data["title"],$data["route"],$data["meta_description"],$data["seo_invisible"],$data["template"],$data["external"],$data["new_window"],$data["resources"],$data["publish_at"],$data["expire_at"],$data["max_age"],$data["_tags"]);
			return $change->ID;
		}

		/*
			Function: createResource
				Creates a resource.

			Parameters:
				folder - The folder to place it in.
				file - The file path.
				md5 - The MD5 hash of the file.
				name - The file name.
				type - The file type.
				is_image - Whether the resource is an image.
				height - The image height (if it's an image).
				width - The image width (if it's an image).
				thumbs - An array of thumbnails (if it's an image).

			Returns:
				The new resource id.
		*/

		function createResource($folder,$file,$md5,$name,$type,$is_image = "",$height = 0,$width = 0,$thumbs = array()) {
			$resource = BigTree\Resource::create($folder,$file,$md5,$name,$type,$is_image,$height,$width,$thumbs);
			return $resource->ID;
		}

		/*
			Function: createResourceFolder
				Creates a resource folder.
				Checks permissions.

			Parameters:
				parent - The parent folder.
				name - The name of the new folder.

			Returns:
				The new folder id or false if not allowed.
		*/

		function createResourceFolder($parent,$name) {
			// Backwards compatibility as ResourceFolder doesn't check permissions
			$permission = $this->getResourceFolderPermission($parent);
			if ($permission != "p") {
				return false;
			}

			$folder = BigTree\ResourceFolder::create($parent,$name);
			return $folder->ID;
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
			// Setup defaults
			$id = $name = $extension = $description = $type = $options = $locked = $encrypted = $system = "";

			// Loop through and create our expected parameters.
			foreach ($data as $key => $val) {
				if (substr($key,0,1) != "_") {
					$$key = $val;
				}
			}

			$setting = BigTree\Settings::create($id,$name,$description,$type,$options,$extension,$system,$encrypted,$locked);
			return $setting ? true : false;
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
			$tag = BigTree\Tag::create($tag);
			return $tag->ID;
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

			Returns:
				true if successful, false if there's an id collision or a bad ID is passed
		*/

		function createTemplate($id,$name,$routed,$level,$module,$resources) {
			$template = BigTree\Template::create($id,$name,$routed,$level,$module,$resources);
			return $template ? true : false;
		}

		/*
			Function: createUser
				Creates a user (and checks access levels to ensure permissions are met).
				Supports pre-4.3 syntax by passing an array as the first parameter.

			Parameters:
				data - An array of user data. ("email", "password", "name", "company", "level", "permissions","alerts")

			Returns:
				id of the newly created user or false if a user already exists with the provided email.
		*/

		function createUser($data) {
			// Loop through and create our expected parameters.
			foreach ($data as $key => $val) {
				if (substr($key,0,1) != "_") {
					$$key = $val;
				}
			}

			$user = BigTree\User::create($email,$password,$name,$company,$level,$permissions,$alerts,$daily_digest);
			return $user ? $user->ID : false;
		}

		/*
			Function: delete404
				Deletes a 404 error.
				Checks permissions.

			Parameters:
				id - The id of the reported 404.
		*/

		function delete404($id) {
			$this->requireLevel(1);

			$redirect = new BigTree\Redirect($id);
			$redirect->delete();
		}

		/*
			Function: deleteCallout
				Deletes a callout and removes its file.

			Parameters:
				id - The id of the callout.
		*/

		function deleteCallout($id) {
			$callout = new BigTree\Callout($id);
			$callout->delete();
		}

		/*
			Function: deleteCalloutGroup
				Deletes a callout group.

			Parameters:
				id - The id of the callout group.
		*/

		function deleteCalloutGroup($id) {
			$group = new BigTree\CalloutGroup($id);
			$group->delete();
		}

		/*
			Function: deleteExtension
				Uninstalls an extension from BigTree and removes its related components and files.

			Parameters:
				id - The extension ID.
		*/

		function deleteExtension($id) {
			$extension = new BigTree\Extension($id);
			$extension->delete();
		}

		/*
			Function: deleteFeed
				Deletes a feed.

			Parameters:
				id - The id of the feed.
		*/

		function deleteFeed($id) {
			$feed = new BigTree\Feed($id);
			$feed->delete();
		}

		/*
			Function: deleteFieldType
				Deletes a field type and erases its files.

			Parameters:
				id - The id of the field type.
		*/

		function deleteFieldType($id) {
			$field_type = new BigTree\FieldType($id);
			$field_type->delete();
		}

		/*
			Function: deleteModule
				Deletes a module.

			Parameters:
				id - The id of the module.
		*/

		function deleteModule($id) {
			$module = new BigTree\Module($id);
			$module->delete();
		}

		/*
			Function: deleteModuleAction
				Deletes a module action.
				Also deletes the related interface if no other action is using it.

			Parameters:
				id - The id of the action to delete.
		*/

		function deleteModuleAction($id) {
			$action = new BigTree\ModuleAction($id);
			$action->delete();
		}

		/*
			Function: deleteModuleEmbedForm
				Deletes an embeddable module form.
				This method is deprecated in favor of deleteModuleInterface.

			Parameters:
				id - The id of the embeddable form.

			See Also:
				<deleteModuleInterface>
		*/

		function deleteModuleEmbedForm($id) {
			$form = new BigTree\ModuleEmbedForm($id);
			$form->delete();
		}

		/*
			Function: deleteModuleForm
				Deletes a module form and its related actions.
				This method is deprecated in favor of deleteModuleInterface.

			Parameters:
				id - The id of the module form.

			See Also:
				<deleteModuleInterface>
		*/

		function deleteModuleForm($id) {
			$form = new BigTree\ModuleForm($id);
			$form->delete();
		}

		/*
			Function: deleteModuleGroup
				Deletes a module group. Sets modules in the group to Misc.

			Parameters:
				id - The id of the module group.
		*/

		function deleteModuleGroup($id) {
			$group = new BigTree\ModuleGroup($id);
			$group->delete();
		}

		/*
			Function: deleteModuleReport
				Deletes a module report and its related actions.
				This method is deprecated in favor of deleteModuleInterface.

			Parameters:
				id - The id of the module report.

			See Also:
				<deleteModuleInterface>
		*/

		function deleteModuleReport($id) {
			$report = new BigTree\ModuleReport($id);
			$report->delete();
		}

		/*
			Function: deleteModuleView
				Deletes a module view and its related actions.
				This method is deprecated in favor of deleteModuleInterface.

			Parameters:
				id - The id of the module view.

			See Also:
				<deleteModuleInterface>
		*/

		function deleteModuleView($id) {
			$view = new BigTree\ModuleView($id);
			$view->delete();
		}

		/*
			Function: deletePackage
				Uninstalls a package from BigTree and removes its related components and files.

			Parameters:
				id - The package ID.
		*/

		function deletePackage($id) {
			$this->deleteExtension($id);
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
			// Published page
			if (is_numeric($page)) {
				$page = new BigTree\Page($page);
				if ($page->UserAccessLevel == "p" && $page->UserCanModifyChildren) {
					$page->delete();
					return true;
				}
			} else {
				$pending_change = new BigTree\PendingChange(substr($page,1));
				$page = new BigTree\Page($page);
				if ($page->UserAccessLevel == "p" && $page->UserCanModifyChildren) {
					$pending_change->delete();
					return true;
				}
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
			$page = new BigTree\Page($id);
			$page->deleteChildren();
		}

		/*
			Function: deletePageDraft
				Deletes a page draft.
				Checks permissions.

			Parameters:
				id - The page id to delete the draft for.
		*/

		function deletePageDraft($id) {
			$page = new BigTree\Page($id);

			// Get the version, check if the user has access to the page the version refers to.
			if ($page->UserAccessLevel != "p") {
				$this->stop("You must be a publisher to manage revisions.");
			}

			$page->deleteDraft();
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
			$page = BigTree\Page::getRevision($id);
			if (!$page) {
				return false;
			}

			// Force publisher access
			if ($page->UserAccessLevel != "p") {
				$this->stop("You must be a publisher to manage revisions.");
			}

			// Delete the revision
			$page->deleteRevision($id);
		}

		/*
			Function: deletePendingChange
				Deletes a pending change.

			Parameters:
				id - The id of the change.
		*/

		function deletePendingChange($id) {
			static::$DB->delete("bigtree_pending_changes",$id);
			$this->track("bigtree_pending_changes",$id,"deleted");
		}

		/*
			Function: deleteResource
				Deletes a resource.

			Parameters:
				id - The id of the resource.
		*/

		function deleteResource($id) {
			$resource = new BigTree\Resource($id);
			$resource->delete();
		}

		/*
			Function: deleteResourceFolder
				Deletes a resource folder and all of its sub folders and resources.

			Parameters:
				id - The id of the resource folder.
		*/

		function deleteResourceFolder($id) {
			$folder = new BigTree\ResourceFolder($id);
			$folder->delete();
		}

		/*
			Function: deleteSetting
				Deletes a setting.

			Parameters:
				id - The id of the setting.
		*/

		function deleteSetting($id) {
			$setting = new BigTree\Setting($id);
			$setting->delete();
		}

		/*
			Function: deleteTemplate
				Deletes a template and its related files.

			Parameters:
				id - The id of the template.

			Returns:
				true if successful.
		*/

		function deleteTemplate($id) {
			$template = new BigTree\Template($id);
			if (!$template) {
				return false;
			}

			$template->delete();
			return true;
		}

		/*
			Function: deleteUser
				Deletes a user

			Parameters:
				id - The user id to delete.

			Returns:
				true if successful. false if the logged in user does not have permission to delete the user.
		*/

		function deleteUser($id) {
			$user = new BigTree\User($id);
			$user->delete();
		}

		/*
			Function: disconnectGoogleAnalytics
				Turns of Google Analytics settings in BigTree and deletes cached information.
		*/

		function disconnectGoogleAnalytics() {
			BigTree\GoogleAnalytics\API::disconnect();
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

		static function doesModuleActionExist($module,$route) {
			return BigTree\ModuleAction::exists($module,$route);
		}

		/*
			Function: drawArrayLevel
				An internal function used for drawing callout and matrix resource data.
		*/

		static function drawArrayLevel($keys,$level,$field = false) {
			if ($field === false) {
				global $field;
			}

			$field = new BigTree\Field($field);
			$field->drawArrayLevel();
		}

		/*
			Function: drawField
				A helper function that draws a field type.

			Parameters:
				field - Field array
		*/

		static function drawField($field) {
			$field = new BigTree\Field($field);
			$field->draw();
		}

		/*
			Function: dailyDigestAlerts
				Generates markup for daily digest alerts for a given user.

			Parameters:
				user - A user entry

			Returns:
				HTML markup for daily digest email
		*/

		static function dailyDigestAlerts($user) {
			$alerts = static::getContentAlerts($user);
			$alerts_markup = "";
			$wrapper = '<div style="margin: 20px 0 30px;">
							<h3 style="color: #333; font-size: 18px; font-weight: normal; margin: 0 0 10px; padding: 0;">Content Age Alerts</h3>
							<table cellspacing="0" cellpadding="0" style="border: 1px solid #eee; border-width: 1px 1px 0; width: 100%;">
								<thead style="background: #ccc; color: #fff; font-size: 10px; text-align: left; text-transform: uppercase;">
									<tr>
										<th style="font-weight: normal; padding: 4px 0 3px 15px;" align="left">Page</th>
										<th style="font-weight: normal; padding: 4px 20px 3px 15px; text-align: right; width: 50px;" align="left">Age</th>
										<th style="font-weight: normal; padding: 4px 0 3px; text-align: center; width: 50px;" align="left">View</th>
										<th style="font-weight: normal; padding: 4px 0 3px; text-align: center; width: 50px;" align="left">Edit</th>
									</tr>
								</thead>
								<tbody style="color: #333; font-size: 13px;">
									{content_alerts}
								</tbody>
							</table>
						</div>';

			// Alerts
			if (is_array($alerts) && count($alerts)) {
				foreach ($alerts as $alert) {
					$alerts_markup .= '<tr>
										<td style="border-bottom: 1px solid #eee; padding: 10px 0 10px 15px;">'.$alert["nav_title"].'</td>
										<td style="border-bottom: 1px solid #eee; padding: 10px 20px 10px 15px; text-align: right;">'.$alert["current_age"].' Days</td>
										<td style="border-bottom: 1px solid #eee; padding: 10px 0; text-align: center;"><a href="'.WWW_ROOT.$alert["path"].'/"><img src="'.ADMIN_ROOT.'images/email/launch.gif" alt="Launch" /></a></td>
										<td style="border-bottom: 1px solid #eee; padding: 10px 0; text-align: center;"><a href="'.ADMIN_ROOT."pages/edit/".$alert["id"].'/"><img src="'.ADMIN_ROOT.'images/email/edit.gif" alt="Edit" /></a></td>
									 </tr>';
				}
			}

			if ($alerts_markup) {
				return str_replace("{content_alerts}",$alerts_markup,$wrapper);
			}
			return "";
		}

		/*
			Function: dailyDigestChanges
				Generates markup for daily digest pending changes for a given user.

			Parameters:
				user - A user entry

			Returns:
				HTML markup for daily digest email
		*/

		static function dailyDigestChanges($user) {
			$changes = static::getPublishableChanges($user["id"]);
			$changes_markup = "";
			$wrapper = '<div style="margin: 20px 0 30px;">
							<h3 style="color: #333; font-size: 18px; font-weight: normal; margin: 0 0 10px; padding: 0;">Pending Changes</h3>
							<table cellspacing="0" cellpadding="0" style="border: 1px solid #eee; border-width: 1px 1px 0; width: 100%;">
								<thead style="background: #ccc; color: #fff; font-size: 10px; text-align: left; text-transform: uppercase;">
									<tr>
										<th style="font-weight: normal; padding: 4px 0 3px 15px; width: 150px;" align="left">Author</th>
										<th style="font-weight: normal; padding: 4px 0 3px 15px; width: 180px;" align="left">Module</th>
										<th style="font-weight: normal; padding: 4px 0 3px 15px;" align="left">Type</th>
										<th style="font-weight: normal; padding: 4px 0 3px; text-align: center; width: 50px;" align="left">View</th>
									</tr>
								</thead>
								<tbody style="color: #333; font-size: 13px;">
									{pending_changes}
								</tbody>
							</table>
						</div>';

			if (count($changes)) {
				foreach ($changes as $change) {
					$changes_markup .= '<tr>';
					$changes_markup .= '<td style="border-bottom: 1px solid #eee; padding: 10px 0 10px 15px;">'.$change["user"]["name"].'</td>';
					if ($change["title"]) {
						$changes_markup .= '<td style="border-bottom: 1px solid #eee; padding: 10px 0 10px 15px;">Pages</td>';
					} else {
						$changes_markup .= '<td style="border-bottom: 1px solid #eee; padding: 10px 0 10px 15px;">'.$change["mod"]["name"].'</td>';
					}
					if (is_null($change["item_id"])) {
						$changes_markup .= '<td style="border-bottom: 1px solid #eee; padding: 10px 0 10px 15px;">Addition</td>';
					} else {
						$changes_markup .= '<td style="border-bottom: 1px solid #eee; padding: 10px 0 10px 15px;">Edit</td>';
					}
					$changes_markup .= '<td style="border-bottom: 1px solid #eee; padding: 10px 0; text-align: center;"><a href="'.static::getChangeEditLink($change).'"><img src="'.ADMIN_ROOT.'images/email/launch.gif" alt="Launch" /></a></td>' . "\r\n";
					$changes_markup .= '</tr>';
				}

				return str_replace("{pending_changes}",$changes_markup,$wrapper);
			} else {
				return "";
			}
		}

		/*
			Function: dailyDigestMessages
				Generates markup for daily digest messages for a given user.

			Parameters:
				user - A user entry

			Returns:
				HTML markup for daily digest email
		*/

		static function dailyDigestMessages($user) {
			$messages = static::getMessages($user["id"]);
			$messages_markup = "";
			$wrapper = '<div style="margin: 20px 0 30px;">
							<h3 style="color: #333; font-size: 18px; font-weight: normal; margin: 0 0 10px; padding: 0;">Unread Messages</h3>
							<table cellspacing="0" cellpadding="0" style="border: 1px solid #eee; border-width: 1px 1px 0; width: 100%;">
								<thead style="background: #ccc; color: #fff; font-size: 10px; text-align: left; text-transform: uppercase;">
									<tr>
										<th style="font-weight: normal; padding: 4px 0 3px 15px; width: 150px;" align="left">Sender</th>
										<th style="font-weight: normal; padding: 4px 0 3px 15px; width: 180px;" align="left">Subject</th>
										<th style="font-weight: normal; padding: 4px 0 3px 15px;" align="left">Date</th>
									</tr>
								</thead>
								<tbody style="color: #333; font-size: 13px;">
									{unread_messages}
								</tbody>
							</table>
						</div>';

			if (count($messages["unread"])) {
				foreach ($messages["unread"] as $message) {
					$messages_markup .= '<tr>
											<td style="border-bottom: 1px solid #eee; padding: 10px 0 10px 15px;">'.$message["sender_name"].'</td>
											<td style="border-bottom: 1px solid #eee; padding: 10px 0 10px 15px;">'.$message["subject"].'</td>
											<td style="border-bottom: 1px solid #eee; padding: 10px 0 10px 15px;">'.date("n/j/y g:ia",strtotime($message["date"])).'</td>
										</tr>';
				}

				return str_replace("{unread_messages}",$messages_markup,$wrapper);
			} else {
				return "";
			}
		}

		/*
			Function: emailDailyDigest
				Sends out a daily digest email to all who have subscribed.
		*/

		function emailDailyDigest() {
			global $bigtree;

			// We're going to show the site's title in the email
			$site_title = static::$DB->fetchSingle("SELECT `nav_title` FROM `bigtree_pages` WHERE id = '0'");

			// Find out what blocks are on
			$extension_settings = BigTreeCMS::getSetting("bigtree-internal-extension-settings");
			$digest_settings = $extension_settings["digest"];

			// Get a list of blocks we'll draw in emails
			$blocks = array();
			$positions = array();

			// Start email service
			$email_service = new BigTreeEmailService;
		
			// We're going to get the position setups and the multi-sort the list to get it in order
			foreach (BigTreeAdmin::$DailyDigestPlugins["core"] as $id => $details) {
				if (empty($digest_settings[$id]["disabled"])) {
					$blocks[] = $details["function"];
					$positions[] = isset($digest_settings[$id]["position"]) ? $digest_settings[$id]["position"] : 0;
				}
			}
			foreach (BigTreeAdmin::$DailyDigestPlugins["extension"] as $extension => $set) {
				foreach ($set as $id => $details) {
					$id = $extension."*".$id;
					if (empty($digest_settings[$id]["disabled"])) {
						$blocks[] = $details["function"];
						$positions[] = isset($digest_settings[$id]["position"]) ? $digest_settings[$id]["position"] : 0;
					}
				}
			}
			array_multisort($positions,SORT_DESC,$blocks);

			// Loop through each user who has opted in to emails
			$daily_digest_users = static::$DB->fetchAll("SELECT * FROM bigtree_users WHERE daily_digest = 'on'");
			foreach ($daily_digest_users as $user) {
				$block_markup = "";

				foreach ($blocks as $function) {
					$block_markup .= call_user_func($function,$user);
				}

				// Send it
				if (trim($block_markup)) {
					$body = file_get_contents(BigTree::path("admin/email/daily-digest.html"));
					$body = str_ireplace("{www_root}", $bigtree["config"]["www_root"], $body);
					$body = str_ireplace("{admin_root}", $bigtree["config"]["admin_root"], $body);
					$body = str_ireplace("{site_title}", $site_title, $body);
					$body = str_ireplace("{date}", date("F j, Y",time()), $body);
					$body = str_ireplace("{blocks}", $block_markup, $body);

					// If we don't have a from email set, third parties most likely will fail so we're going to use local sending
					if ($email_service->Settings["bigtree_from"]) {
						$reply_to = "no-reply@".(isset($_SERVER["HTTP_HOST"]) ? str_replace("www.","",$_SERVER["HTTP_HOST"]) : str_replace(array("http://www.","https://www.","http://","https://"),"",DOMAIN));
						$email_service->sendEmail("$site_title Daily Digest",$body,$user["email"],$email_service->Settings["bigtree_from"],"BigTree CMS",$reply_to);
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

		static function forgotPassword($email) {
			$user = BigTree\User::getByEmail($email);
			if (!$user) {
				return false;
			}

			$user->initPasswordReset();
			BigTree::redirect($login_root."forgot-success/");
		}

		/*
			Function: get404Total
				Get the total number of 404s of a certain type.

			Parameters:
				type - The type to retrieve the count for (301, ignored, 404)

			Returns:
				The number of 404s in the table of the given type.
		*/

		static function get404Total($type) {
			if ($type == "404") {
				return static::$DB->fetchSingle("SELECT COUNT(*) FROM bigtree_404s WHERE ignored = '' AND redirect_url = ''");
			} elseif ($type == "301") {
				return static::$DB->fetchSingle("SELECT COUNT(*) FROM bigtree_404s WHERE ignored = '' AND redirect_url != ''");
			} elseif ($type == "ignored") {
				return static::$DB->fetchSingle("SELECT COUNT(*) FROM bigtree_404s WHERE ignored = 'on'");
			}
			return false;
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
			$module = new BigTree\Module($module);
			return $module->UserAccessibleGroups;
		}

		/*
			Function: getAccessLevel
				Returns the permission level for a given module and item.
				Can be called non-statically to check for the logged in user.

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

		function getAccessLevel($module,$item = array(),$table = "",$user = false) {
			// UserAccessLevel uses the $admin object, so we need fake it
			if ($user !== false) {
				global $admin;

				$saved = array("level" => $admin->Level, "permissions" => $admin->Permissions);
				$admin->Level = $user["level"];
				$admin->Permissions = $user["permissions"];
			}

			$module = new BigTree\Module($module);
			$permission = $module->getEntryAccessLevel($item,$table);

			// Restore permissions
			if ($user !== false) {
				$admin->Level = $saved["level"];
				$admin->Permissions = $saved["permissions"];
			}
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

		static function getActionClass($action,$item) {
			return BigTree\ModuleView::generateActionClass($action,$item);
		}

		/*
			Function: getArchivedNavigationByParent
				Returns an alphabetic list of navigation that is archived under the given parent.

			Parameters:
				parent - The ID of the parent page

			Returns:
				An array of page entries.
		*/

		static function getArchivedNavigationByParent($parent) {
			$page = new BigTree\Page($parent);
			return $page->getArchivedChildren(true);
		}

		/*
			Function: getBasicTemplates
				Returns a list of non-routed templates ordered by position that the logged in user has access to.
			
			Parameters:
				sort - Sort order, defaults to positioned

			Returns:
				An array of template entries.
		*/

		function getBasicTemplates($sort = "position DESC, id ASC") {
			$list = BigTree\Template::allByRouted("",$sort,true);
			foreach ($list as $key => $template) {
				if ($template["level"] > $this->Level) {
					unset($list[$key]);
				}
			}
			return $list;
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
			$module = new BigTree\Module($module);
			return $module->getCachedAccessLevel($item,$table);
		}

		/*
			Function: getCachedFieldTypes
				Caches available field types and returns them.

			Parameters:
				split - Whether to split the field types into separate default / custom arrays (defaults to false)

			Returns:
				Array of three arrays of field types (template, module, and callout).
		*/

		static function getCachedFieldTypes($split = false) {
			return BigTree\FieldType::reference($split);
		}

		/*
			Function: getCallout
				Returns a callout entry.

			Parameters:
				id - The id of the callout.

			Returns:
				A callout entry from bigtree_callouts with resources decoded.
		*/

		static function getCallout($id) {
			$callout = new BigTree\Callout($id);
			return $callout->Array;
		}

		/*
			Function: getCalloutGroup
				Returns a callout group entry from the bigtree_callout_groups table.

			Parameters:
				id - The id of the callout group.

			Returns:
				A callout group entry.
		*/

		static function getCalloutGroup($id) {
			$group = new BigTree\CalloutGroup($id);
			return $group->Array;
		}

		/*
			Function: getCalloutGroups
				Returns a list of callout groups sorted by name.

			Returns:
				An array of callout group entries from bigtree_callout_groups.
		*/

		static function getCalloutGroups() {
			return BigTree\CalloutGroup::all("name ASC",true);
		}

		/*
			Function: getCallouts
				Returns a list of callouts.

			Parameters:
				sort - The order to return the callouts. Defaults to positioned.

			Returns:
				An array of callout entries from bigtree_callouts.
		*/

		static function getCallouts($sort = "position DESC, id ASC") {
			return BigTree\Callout::all($sort,true);
		}

		/*
			Function: getCalloutsAllowed
				Returns a list of callouts the logged-in user is allowed access to.

			Parameters:
				sort - The order to return the callouts. Defaults to positioned.

			Returns:
				An array of callout entries from bigtree_callouts.
		*/

		function getCalloutsAllowed($sort = "position DESC, id ASC") {
			return BigTree\Callout::allAllowed($sort,true);
		}

		/*
			Function: getCalloutsInGroups
				Returns a list of callouts in a given set of groups.

			Parameters:
				groups - An array of group IDs to retrieve callouts for.
				auth - If set to true, only returns callouts the logged in user has access to. Defaults to true.

			Returns:
				An alphabetized array of entries from the bigtree_callouts table.
		*/

		function getCalloutsInGroups($groups,$auth = true) {
			return BigTree\Callout::allInGroups($groups,$auth,true);
		}

		/*
			Function: getChangeEditLink
				Returns a link to where the item involved in the pending change can be edited.

			Parameters:
				change - The ID of the change or the change array from the database.

			Returns:
				A string containing a link to the admin.
		*/

		static function getChangeEditLink($change) {
			$change = new BigTree\PendingChange($change);
			return $change->EditLink;
		}

		/*
			Function: getContentAlerts
				Gets a list of pages with content older than their Max Content Age that a user follows.

			Parameters:
				user - The user id to pull alerts for or a user entry

			Returns:
				An array of arrays containing a page title, path, and id.
		*/

		static function getContentAlerts($user) {
			if (!is_array($user)) {
				$user = static::getUser($user);
			}

			// Alerts is empty, nothing to check
			$user["alerts"] = array_filter((array)$user["alerts"]);
			if (!$user["alerts"]) {
				return array();
			}

			// If we care about the whole tree, skip the madness.
			if ($user["alerts"][0] == "on") {
				return static::$DB->fetchAll("SELECT nav_title, id, path, updated_at, DATEDIFF('".date("Y-m-d")."',updated_at) AS current_age
											  FROM bigtree_pages 
											  WHERE max_age > 0 AND DATEDIFF('".date("Y-m-d")."',updated_at) > max_age 
											  ORDER BY current_age DESC");
			} else {
				// We're going to generate a list of pages the user cares about first to get their paths.
				foreach ($user["alerts"] as $alert => $status) {
					$where[] = "id = '".static::$DB->escape($alert)."'";
				}

				// Now from this we'll build a path query
				$path_query = array();
				$path_strings = static::$DB->fetchAllSingle("SELECT path FROM bigtree_pages WHERE ".implode(" OR ",$where));
				foreach ($path_strings as $path) {
					$path = static::$DB->escape($path);
					$path_query[] = "path = '$path' OR path LIKE '$path/%'";
				}

				// Only run if the pages requested still exist
				if (count($path_query)) {
					// Find all the pages that are old that contain our paths
					$alerts = static::$DB->fetchAll("SELECT nav_title, id, path, updated_at, DATEDIFF('".date("Y-m-d")."',updated_at) AS current_age 
													 FROM bigtree_pages 
													 WHERE max_age > 0 AND (".implode(" OR ",$path_query).") AND DATEDIFF('".date("Y-m-d")."',updated_at) > max_age 
													 ORDER BY current_age DESC");
				}
			}

			return array();
		}

		/*
			Function: getExtension
				Returns information about a package or extension.

			Parameters:
				id - The package/extension ID.

			Returns:
				A package/extension.
		*/
		
		static function getExtension($id) {
			$extension = new BigTree\Extension($id);
			return $extension->Array;
		}

		/*
			Function: getExtensions
				Returns a list of installed/created extensions.

			Parameters:
				sort - Column/direction to sort (defaults to last_updated DESC)

			Returns:
				An array of extensions.
		*/
		
		static function getExtensions($sort = "last_updated DESC") {
			return BigTree\Extension::allByType("extension",$sort,true);
		}

		/*
			Function: getFeeds
				Returns a list of feeds.

			Parameters:
				sort - The sort direction, defaults to name.

			Returns:
				An array of feed elements from bigtree_feeds sorted by name.
		*/

		static function getFeeds($sort = "name ASC") {
			return BigTree\Feed::all($sort,true);
		}

		/*
			Function: getFieldType
				Returns a field type.

			Parameters:
				id - The id of the file type.

			Returns:
				A field type entry with the "use_cases" column decoded.
		*/

		static function getFieldType($id) {
			$field_type = new BigTree\FieldType($id);
			return $field_type->Array;
		}

		/*
			Function: getFieldTypes
				Returns a list of field types.

			Parameters:
				sort - The sort directon, defaults to name ASC.

			Returns:
				An array of entries from bigtree_field_types.
		*/

		static function getFieldTypes($sort = "name ASC") {
			return BigTree\FieldType::all($sort,true);
		}

		/*
			Function: getFullNavigationPath
				Calculates the full navigation path for a given page ID.

			Parameters:
				id - The page ID to calculate the navigation path for.

			Returns:
				The navigation path (normally found in the "path" column in bigtree_pages).
		*/

		static function getFullNavigationPath($id, $path = array()) {
			$page = new BigTree\Page($id);
			return $page->regeneratePath();
		}

		/*
			Function: getHiddenNavigationByParent
				Returns an alphabetic list of navigation that is hidden under the given parent.

			Parameters:
				parent - The ID of the parent page

			Returns:
				An array of page entries.
		*/

		static function getHiddenNavigationByParent($parent) {
			$page = new BigTree\Page($parent);
			return $page->getHiddenChildren();
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

		function getMessage($id) {
			$message = new BigTree\Message($id);

			if ($message->Sender != $this->ID && !in_array($this->ID,$message->Recipients)) {
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

		function getMessageChain($id) {
			$message = new BigTree\Message($id);
			$chain = $message->Chain;

			// Convert to arrays
			foreach ($chain as &$item) {
				$item = $item->Array;
			}

			return $chain;
		}

		/*
			Function: getMessages
				Returns all a user's messages.

			Parameters:
				user - User ID to retrieve messages for

			Returns:
				An array containing "sent", "read", and "unread" keys that contain an array of messages each.
		*/

		static function getMessages($user) {
			return BigTree\Message::allByUser($user,true);
		}

		/*
			Function: getModule
				Returns an entry from the bigtree_modules table.

			Parameters:
				id - The id of the module.

			Returns:
				A module entry with the "gbp" column decoded.
		*/

		static function getModule($id) {
			$module = new BigTree\Module($id);
			$module = $module->Array;
			$module["gbp"] = $module["group_based_permissions"];

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

		static function getModuleAction($id) {
			$action = new BigTree\ModuleAction($id);
			return $action->Array;
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

		static function getModuleActionByRoute($module,$route) {
			// For landing routes.
			if (!count($route)) {
				$route = array("");
			}

			$commands = array();

			while (count($route)) {
				$action = static::$DB->fetch("SELECT * FROM bigtree_module_actions WHERE module = ? AND route = ?", $module, implode("/",$route));

				// If we found an action for this sequence, return it with the extra URL route commands
				if ($action) {
					return array("action" => $action, "commands" => array_reverse($commands));
				}

				// Otherwise strip off the last route as a command and try again
				$commands[] = array_pop($route);
			}

			return false;
		}

		/*
			Function: getModuleActionForForm
				Returns the related module action for an auto module form. Prioritizes edit action over add.
				DEPRECATED - Please use getModuleActionForInterface.

			Parameters:
				form - The id of a form or a form entry.

			Returns:
				A module action entry.

			See Also:
				<getModuleActionForInterface>
		*/

		static function getModuleActionForForm($form) {
			return static::getModuleActionForInterface($form);
		}

		/*
			Function: getModuleActionForInterface
				Returns the related module action for a given module interface. Prioritizes edit action over add.
		
			Parameters:
				interface - The id of an interface or interface array.

			Returns:
				A module action entry.
		*/

		static function getModuleActionForInterface($interface) {
			$action = BigTree\ModuleAction::getByInterface($interface);
			return $action->Array;
		}

		/*
			Function: getModuleActionForReport
				Returns the related module action for an auto module report.
				DEPRECATED - Please use getModuleActionForInterface.

			Parameters:
				report - The id of a report or a report entry.

			Returns:
				A module action entry.

			See Also:
				<getModuleActionForInterface>
		*/

		static function getModuleActionForReport($report) {
			return static::getModuleActionForInterface($report);
		}

		/*
			Function: getModuleActionForView
				Returns the related module action for an auto module view.
				DEPRECATED - Please use getModuleActionForInterface.

			Parameters:
				view - The id of a view or a view entry.

			Returns:
				A module action entry.

			See Also:
				<getModuleActionForInterface>
		*/

		static function getModuleActionForView($view) {
			return static::getModuleActionForInterface($view);
		}

		/*
			Function: getModuleActions
				Returns a list of module actions in positioned order.

			Parameters:
				module - A module id or a module entry.

			Returns:
				An array of module action entries.
		*/

		static function getModuleActions($module) {
			return BigTree\ModuleAction::allByModule($module,true);
		}

		/*
			Function: getModuleByClass
				Returns a module entry for the given class name.

			Parameters:
				class - A module class.

			Returns:
				A module entry with the "gbp" column decoded or false if a module was not found.
		*/

		static function getModuleByClass($class) {
			$module = BigTree\Module::getByClass($class);
			$module = $module->Array;
			$module["gbp"] = $module["group_based_permissions"];

			return $module;
		}

		/*
			Function: getModuleByRoute
				Returns a module entry for the given route.

			Parameters:
				route - A module route.

			Returns:
				A module entry with the "gbp" column decoded or false if a module was not found.
		*/

		static function getModuleByRoute($route) {
			$module = BigTree\Module::getByRoute($class);
			$module = $module->Array;
			$module["gbp"] = $module["group_based_permissions"];

			return $module;
		}

		/*
			Function: getModuleEmbedForms
				Gets embeddable forms from bigtree_module_interfaces.

			Parameters:
				sort - The field to sort by (defaults to title ASC)
				module - Specific module to pull forms for (defaults to all modules).

			Returns:
				An array of embeddable form entries from bigtree_module_interfaces.
		*/

		static function getModuleEmbedForms($sort = "title ASC",$module = false) {
			$interfaces = BigTree\ModuleInterface::allByModuleAndType($module,"embeddable-form",$sort,true);

			// Return previous table format
			$forms = array();
			foreach ($interfaces as $interface) {
				$settings = json_decode($interface["settings"],true);
				$forms[] = array(
					"id" => $interface["id"],
					"module" => $interface["module"],
					"title" => $interface["title"],
					"table" => $interface["table"],
					"fields" => BigTree::arrayValue($settings["fields"]),
					"default_position" => $settings["default_position"],
					"default_pending" => $settings["default_pending"],
					"css" => $settings["css"],
					"hash" => $settings["hash"],
					"redirect_url" => $settings["redirect_url"],
					"thank_you_message" => $settings["thank_you_message"],
					"hooks" => $settings["hooks"]
				);
			}

			return $forms;
		}

		/*
			Function: getModuleForms
				Gets forms from bigtree_module_interfaces with fields decoded.

			Parameters:
				sort - The field to sort by (defaults to title ASC)
				module - Specific module to pull forms for (defaults to all modules).

			Returns:
				An array of entries from bigtree_module_interfaces with "fields" decoded.
		*/

		static function getModuleForms($sort = "title ASC",$module = false) {
			$interfaces = BigTree\ModuleInterface::allByModuleAndType($module,"form",$sort,true);

			// Return previous table format
			$forms = array();
			foreach ($interfaces as $interface) {
				$settings = json_decode($interface["settings"],true);
				$forms[] = array(
					"id" => $interface["id"],
					"module" => $interface["module"],
					"title" => $interface["title"],
					"table" => $interface["table"],
					"fields" => BigTree::arrayValue($settings["fields"]),
					"default_position" => $settings["default_position"],
					"return_view" => $settings["return_view"],
					"return_url" => $settings["return_url"],
					"tagging" => $settings["tagging"],
					"hooks" => $settings["hooks"]
				);
			}
			return $forms;
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

		static function getModuleGroup($id) {
			$group = new BigTree\ModuleGroup($id);
			return $group->Array;
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


		static function getModuleGroupByName($name) {
			$group = BigTree\ModuleGroup::getByName($name);
			return $group ? $group->Array : false;
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

		static function getModuleGroupByRoute($route) {
			$group = BigTree\ModuleGroup::getByRoute($name);
			return $group ? $group->Array : false;
		}

		/*
			Function: getModuleGroups
				Returns a list of module groups.

			Parameters:
				sort - Sort by (defaults to positioned)

			Returns:
				An array of module group entries from bigtree_module_groups.
		*/

		static function getModuleGroups($sort = "position DESC, id ASC") {
			$raw_groups = BigTree\ModuleGroup::all($sort,true);
			$groups = array();
			
			foreach ($raw_groups as $group) {
				$groups[$group["id"]] = $group;
			}

			return $groups;
		}

		/*
			Function: getModuleNavigation
				Returns a list of module actions that are in navigation.

			Parameters:
				module - A module id or a module entry.

			Returns:
				An array of module actions from bigtree_module_actions.
		*/

		static function getModuleNavigation($module) {
			return static::$DB->fetchAll("SELECT * FROM bigtree_module_actions WHERE module = ? AND in_nav = 'on' ORDER BY position DESC, id ASC",
										 is_array($module) ? $module["id"] : $module);
		}

		/*
			Function: getModuleReports
				Gets reports interfaces from the bigtree_module_interfaces table.

			Parameters:
				sort - The field to sort by (defaults to title ASC)
				module - Specific module to pull reports for (defaults to all modules).

			Returns:
				An array of report interfaces from bigtree_module_interfaces.
		*/

		static function getModuleReports($sort = "title ASC",$module = false) {
			$interfaces = BigTree\ModuleInterface::allByModuleAndType($module,"report",$sort,true);

			// Support the old table format
			$reports = array();
			foreach ($interfaces as $interface) {
				$settings = json_decode($interface["settings"],true);
				$reports[] = array(
					"id" => $interface["id"],
					"module" => $interface["module"],
					"title" => $interface["title"],
					"table" => $interface["table"],
					"type" => $settings["type"],
					"filters" => $settings["filters"],
					"fields" => $settings["fields"],
					"parser" => $settings["parser"],
					"view" => $settings["view"]
				);
			}
			return $reports;
		}

		/*
			Function: getModules
				Returns an array of modules.

			Parameters:
				sort - The sort order (defaults to oldest first).
				auth - If set to true, only returns modules the logged in user has access to. Defaults to true.

			Returns:
				An array of entries from the bigtree_modules table.
		*/

		function getModules($sort = "id ASC",$auth = true) {
			if (!$auth) {
				return BigTree\Module::all($sort,true);
			}

			$results = array();
			$modules = BigTree\Module::all($sort);
			foreach ($modules as $module) {
				if ($module->UserCanAccess) {
					$results[] = $module->Array;
				}
			}

			return $results;
		}

		/*
			Function: getModulesByGroup
				Returns a list of modules in a given group.

			Parameters:
				group - The group to return modules for.
				sort - The sort order (defaults to positioned)
				auth - If set to true, only returns modules the logged in user has access to. Defaults to true.

			Returns:
				An array of entries from the bigtree_modules table.
		*/

		function getModulesByGroup($group,$sort = "position DESC, id ASC",$auth = true) {
			$group = is_array($group) ? $group["id"] : $group;
			return BigTree\Module::allByGroup($group,$sort,true,$auth);
		}

		/*
			Function: getModuleViews
				Returns a list of all view entries in the bigtree_module_interfaces table.

			Parameters:
				sort - The column to sort by (defaults to title ASC)
				module - Specific module to pull views for (defaults to all modules).

			Returns:
				An array of view entries with "fields" decoded.
		*/

		static function getModuleViews($sort = "title ASC",$module = false) {
			$interfaces = BigTree\ModuleInterface::allByModuleAndType($module,"view",$sort,true);

			// Support the old table format
			$views = array();
			foreach ($interfaces as $interface) {
				$settings = json_decode($interface["settings"],true);
				$views[] = array(
					"id" => $interface["id"],
					"module" => $interface["module"],
					"title" => $interface["title"],
					"table" => $interface["table"],
					"type" => $settings["type"],
					"fields" => $settings["fields"],
					"options" => $settings["options"],
					"actions" => $settings["actions"],
					"preview_url" => $settings["preview_url"],
					"related_form" => $settings["related_form"]
				);
			}

			return $views;
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

		static function getNaturalNavigationByParent($parent,$levels = 1) {
			$nav = static::$DB->fetchAll("SELECT id, nav_title AS title, template, publish_at, expire_at, ga_page_views 
										  FROM bigtree_pages 
										  WHERE parent = '$parent' AND in_nav = 'on' AND archived != 'on' 
										  ORDER BY position DESC, id ASC");
			if ($levels > 1) {
				foreach ($nav as &$item) {
					$item["children"] = static::getNaturalNavigationByParent($item["id"],$levels - 1);
				}
			}

			return $nav;
		}

		/*
			Function: getPackage
				Returns information about a package or extension.

			Parameters:
				id - The package/extension ID.

			Returns:
				A package/extension.
		*/

		static function getPackage($id) {
			return static::$DB->fetch("SELECT * FROM bigtree_extensions WHERE id = ?", $id);
		}

		/*
			Function: getPackages
				Returns a list of installed/created packages.

			Parameters:
				sort - Column/direction to sort (defaults to last_updated DESC)

			Returns:
				An array of packages.
		*/

		static function getPackages($sort = "last_updated DESC") {
			return static::$DB->fetchAll("SELECT * FROM bigtree_extensions WHERE type = 'package' ORDER BY $sort");
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
			return $this->getPageAccessLevelByUser($page,array(
				"id" => $this->ID,
				"level" => $this->Level,
				"permissions" => $this->Permissions
			));
		}

		/*
			Function: getPageAccessLevelByUser
				Returns the access level for the provided user to a page.

			Parameters:
				page - The page id.
				user - The user entry or user id.

			Returns:
				"p" for publisher, "e" for editor, false for no access.

			See Also:
				<getPageAccessLevel>
		*/

		static function getPageAccessLevelByUser($page,$user) {
			// See if this is a pending change, if so, grab the change's parent page and check permission levels for that instead.
			if (!is_numeric($page) && $page[0] == "p") {
				$pending_change = static::$DB->fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", substr($page,1));
				$changes = json_decode($pending_change["changes"],true);
				return static::getPageAccessLevelByUser($changes["parent"],$user);
			}

			// If we don't have a user entry, turn it into an entry
			if (!is_array($user)) {
				$user = static::getUser($user);
			}

			$level = $user["level"];
			$permissions = $user["permissions"];
		
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
			$page_parent = static::$DB->fetchSingle("SELECT parent FROM bigtree_pages WHERE id = ?", $page);

			// Grab the parent's permission. Keep going until we find a permission that isn't inherit or until we hit a parent of 0.
			$parent_permission = $permissions["page"][$page_parent];
			while ((!$parent_permission || $parent_permission == "i") && $page_parent) {
				$parent_id = static::$DB->fetchSingle("SELECT parent FROM bigtree_pages WHERE id = ?", $page_parent);
				$parent_permission = $permissions["page"][$parent_id];
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

		static function getPageAdminLinks() {
			global $bigtree;

			$admin_root = static::$DB->escape($bigtree["config"]["admin_root"]);
			$partial_root = static::$DB->escape(str_replace($bigtree["config"]["www_root"],"{wwwroot}",$bigtree["config"]["admin_root"]));

			return static::$DB->fetchAll("SELECT * FROM bigtree_pages 
										  WHERE resources LIKE '%$admin_root%' OR 
										  		resources LIKE '%$partial_root%' OR
										  		REPLACE(resources,'{adminroot}js/embeddable-form.js','') LIKE '%{adminroot}%'
										  ORDER BY nav_title ASC");
		}

		/*
			Function: getPageChanges
				Returns pending changes for a given page.

			Parameters:
				page - The page id.

			Returns:
				An entry from bigtree_pending_changes with changes decoded.
		*/

		static function getPageChanges($page) {
			$change = static::$DB->fetch("SELECT * FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = ?", $page);
			if (!$change) {
				return false;
			}

			$change["changes"] = json_decode($change["changes"],true);
			return $change;
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

		static function getPageChildren($page,$sort = "nav_title ASC") {
			return static::$DB->fetchAll("SELECT * FROM bigtree_pages WHERE parent = ? AND archived != 'on' ORDER BY $sort", $page);
		}

		/*
			Function: getPageLineage
				Returns all the ids of pages above this page not including the homepage.
			
			Parameters:
				page - Page ID
			
			Returns:
				Array of IDs
		*/
		
		function getPageLineage($page) {
			$parents = array();

			while ($page = static::$DB->fetchSingle("SELECT parent FROM bigtree_pages WHERE id = ?", $page)) {
				$parents[] = $page;
			}

			return $parents;
		}

		/*
			Function: getPageIds
				Returns all the IDs in bigtree_pages for pages that aren't archived.

			Returns:
				An array of page ids.
		*/

		static function getPageIds() {
			return static::$DB->fetchAllSingle("SELECT id FROM bigtree_pages WHERE archived != 'on' ORDER BY id ASC");
		}

		/*
			Function: getPageIDForPath
				Provides the page ID for a given path array.
				This is equivalent to BigTreeCMS::getNavId.
			
			Parameters:
				path - An array of path elements from a URL
				previewing - Whether we are previewing or not.
			
			Returns:
				An array containing the page ID and any additional commands.
		*/
		
		static function getPageIDForPath($path,$previewing = false) {
			$commands = array();
			
			if (!$previewing) {
				$publish_at = "AND (publish_at <= NOW() OR publish_at IS NULL) AND (expire_at >= NOW() OR expire_at IS NULL)";
			} else {
				$publish_at = "";
			}
			
			// See if we have a straight up perfect match to the path.
			$page = static::$DB->fetch("SELECT bigtree_pages.id, bigtree_templates.routed 
										FROM bigtree_pages LEFT JOIN bigtree_templates 
										ON bigtree_pages.template = bigtree_templates.id 
										WHERE path = ? AND archived = '' $publish_at", implode("/",$path));
			if ($page) {
				return array($page["id"],$commands,$page["routed"]);
			}
			
			// Guess we don't, let's chop off commands until we find a page.
			$x = 0;
			while ($x < count($path)) {
				$x++;
				$commands[] = $path[count($path)-$x];
				$path_string = implode("/",array_slice($path,0,-1 * $x));
				
				// We have additional commands, so we're now making sure the template is also routed, otherwise it's a 404.
				$page = static::$DB->fetch("SELECT bigtree_pages.id FROM bigtree_pages JOIN bigtree_templates 
											ON bigtree_pages.template = bigtree_templates.id 
											WHERE bigtree_pages.path = ? AND 
												  bigtree_pages.archived = '' AND 
												  bigtree_templates.routed = 'on' $publish_at", $path_string);
				if ($page) {
					return array($page["id"],array_reverse($commands),"on");
				}
			}
			
			return array(false,false,false);
		}

		/*
			Function: getPageOfSettings
				Returns a page of settings the logged in user has access to.

			Parameters:
				page - The page to return.
				query - Optional query string to search against.
				sort - Sort order. Defaults to name ASC.

			Returns:
				An array of entries from bigtree_settings.
				If the setting is encrypted the value will be "[Encrypted Text]", otherwise it will be decoded.
				If the calling user is a developer, returns locked settings, otherwise they are left out.
		*/

		function getPageOfSettings($page = 1,$query = "") {
			$query_parts = array(1);

			// If we're querying...
			if ($query) {
				$string_parts = explode(" ",$query);
				foreach ($string_parts as $part) {
					$part = static::$DB->escape($part);
					$query_parts[] = "(name LIKE '%$part%' OR `value` LIKE '%$part%')";
				}
			}

			// Check whether we should return developer only settings
			$locked = ($this->Level < 2) ? " AND locked = ''" : "";

			// Get the page
			$settings = static::$DB->fetchAll("SELECT * FROM bigtree_settings 
											   WHERE ".implode(" AND ",$query_parts)." $locked AND system = '' 
											   ORDER BY name LIMIT ".(($page - 1) * static::$PerPage).",".static::$PerPage);

			// Decode values
			foreach ($settings as &$setting) {
				if ($setting["encrypted"]) {

				} else {
					$setting["value"] = json_decode($setting["value"],true);
	
					if (is_array($setting["value"])) {
						$setting["value"] = BigTree::untranslateArray($setting["value"]);
					} else {
						$setting["value"] = BigTreeCMS::replaceInternalPageLinks($setting["value"]);
					}
				}

				$setting["description"] = BigTreeCMS::replaceInternalPageLinks($setting["description"]);
			}

			return $settings;
		}

		/*
			Function: getPageRevision
				Returns a version of a page from the bigtree_page_revisions table.

			Parameters:
				id - The id of the page version.

			Returns:
				A page version entry from the table.
		*/

		static function getPageRevision($id) {
			$page = BigTree\Page::getRevision($id);
			return $page->Array;
		}

		/*
			Function: getPageRevisions
				Get all revisions for a page.

			Parameters:
				page - The page id to get revisions for.

			Returns:
				An array of "saved" revisions and "unsaved" revisions.
		*/

		static function getPageRevisions($page) {
			$saved = $unsaved = array();
			$revisions = static::$DB->fetchAll("SELECT bigtree_users.name, 
													   bigtree_users.email, 
													   bigtree_page_revisions.saved, 
													   bigtree_page_revisions.saved_description, 
													   bigtree_page_revisions.updated_at, 
													   bigtree_page_revisions.id 
												FROM bigtree_page_revisions JOIN bigtree_users 
												ON bigtree_page_revisions.author = bigtree_users.id 
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

		static function getPages() {
			return static::$DB->fetchAll("SELECT * FROM bigtree_pages ORDER BY id ASC");
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

		static function getPageSEORating($page,$content) {
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

			include_once SERVER_ROOT."core/inc/lib/Text-Statistics/src/DaveChild/TextStatistics/Text.php";
			include_once SERVER_ROOT."core/inc/lib/Text-Statistics/src/DaveChild/TextStatistics/Maths.php";
			include_once SERVER_ROOT."core/inc/lib/Text-Statistics/src/DaveChild/TextStatistics/Syllables.php";
			include_once SERVER_ROOT."core/inc/lib/Text-Statistics/src/DaveChild/TextStatistics/Pluralise.php";
			include_once SERVER_ROOT."core/inc/lib/Text-Statistics/src/DaveChild/TextStatistics/Resource.php";
			include_once SERVER_ROOT."core/inc/lib/Text-Statistics/src/DaveChild/TextStatistics/TextStatistics.php";
			$textStats = new \DaveChild\TextStatistics\TextStatistics;
			$recommendations = array();

			$score = 0;

			// Check if they have a page title.
			if ($page["title"]) {
				$score += 5;

				// They have a title, let's see if it's unique
				$count = static::$DB->fetchSingle("SELECT COUNT(*) FROM bigtree_pages 
												   WHERE title = ? AND id != ?", $page["title"], $page["id"]);
				if (!$count) {
					// They have a unique title
					$score += 5;
				} else {
					$recommendations[] = "Your page title should be unique. ".($count - 1)." other page(s) have the same title.";
				}

				// Check title length / word count
				$words = $textStats->wordCount($page["title"]);
				$length = mb_strlen($page["title"]);

				// Minimum of 4 words, less than 72 characters
				if ($words >= 4 && $length <= 72) {
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
				$meta_length = mb_strlen($page["meta_description"]);
				if ($meta_length <= 165) {
					$score += 5;
				} else {
					$recommendations[] = "Your meta description should be no more than 165 characters.  It is currently $meta_length characters.";
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
					$words = $textStats->wordCount($stripped_text);
					$readability = $textStats->fleschKincaidReadingEase($stripped_text);
					if ($readability < 0) {
						$readability = 0;
					}
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
						$recommendations[] = "Your readability score is ".($read_score*100)."%.  Using shorter sentences and words with fewer syllables will make your site easier to read by search engines and users.";
						$score += ceil($read_score * 20);
					}
				} else {
					$recommendations[] = "You should enter page content.";
				}

				// Check page freshness
				$updated = strtotime($page["updated_at"]);
				$age = time() - $updated - (60 * 24 * 60 * 60);
				// See how much older it is than 2 months.
				if ($age > 0) {
					$age_score = 10 - floor(2 * ($age / (30 * 24 * 60 * 60)));
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
				$color = BigTree::colorMesh("#CCAC00","#FF0000",100 - (100 * $score / 50));
			} elseif ($score <= 80) {
				$color = BigTree::colorMesh("#008000","#CCAC00",100 - (100 * ($score - 50) / 30));
			}

			return array("score" => $score, "recommendations" => $recommendations, "color" => $color);
		}

		/*
			Function: getPendingChange
				Returns a pending change from the bigtree_pending_changes table.

			Parameters:
				id - The id of the change.
				decode - Whether to decode change columns (defaults to true)

			Returns:
				A entry from the table with the "changes" column decoded.
		*/

		static function getPendingChange($id,$decode = true) {
			$change = static::$DB->fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?",$id);
			if (!$change || !$decode) {
				return $change;
			}

			$change["changes"] = json_decode($change["changes"],true);
			$change["mtm_changes"] = json_decode($change["mtm_changes"],true);
			$change["tags_changes"] = json_decode($change["tags_changes"],true);
			return $change;
		}

		// For backwards compatibility
		static function getChange($id) { return static::getPendingChange($id,false); }

		/*
			Function: getPublishableChanges
				Returns a list of changes that the given user has access to publish.

			Parameters:
				user - A user entry or user ID

			Returns:
				An array of changes sorted by most recent.
		*/

		static function getPublishableChanges($user) {
			$publishable_changes = array();

			if (!is_array($user)) {
				$user = static::getUser($user);
			}

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

			$changes = static::$DB->fetchAll("SELECT * FROM bigtree_pending_changes 
											  WHERE ".implode(" OR ",$search)." 
											  ORDER BY date DESC");

			foreach ($changes as $change) {
				$ok = false;

				// Append a p if this isn't a change but rather a pending item
				if (!$change["item_id"]) {
					$id = "p".$change["id"];
				} else {
					$id = $change["item_id"];
				}

				// If they're an admin, they've got it.
				if ($user["level"] > 0) {
					$ok = true;
				// Check permissions on a page if it's a page.
				} elseif ($change["table"] == "bigtree_pages") {
					$access_level = static::getPageAccessLevelByUser($id,$user);
					// If we're a publisher, this is ours!
					if ($access_level == "p") {
						$ok = true;
					}
				} else {
					// Check our list of modules.
					if ($user["permissions"]["module"][$change["module"]] == "p") {
						$ok = true;
					} else {
						// Check our group based permissions
						$item = BigTreeAutoModule::getPendingItem($change["table"],$id);
						$access_level = static::getAccessLevel(static::getModule($change["module"]),$item["item"],$change["table"],$user);
						if ($access_level == "p") {
							$ok = true;
						}
					}
				}

				// We're a publisher, get the info about the change and put it in the change list.
				if ($ok) {
					$change["mod"] = static::getModule($change["module"]);
					$change["user"] = static::getUser($change["user"]);
					$publishable_changes[] = $change;
				}
			}

			return $publishable_changes;
		}

		/*
			Function: getPendingChanges
				Returns a list of changes for a given user.

			Parameters:
				user - The user id to retrieve changes for. Defaults to the logged in user.

			Returns:
				An array of changes sorted by most recent.
		*/

		function getPendingChanges($user = false) {
			if (is_array($user)) {
				$user = $user["id"];
			} elseif (!$user) {
				$user = $this->ID;
			}

			return static::$DB->fetchAll("SELECT * FROM bigtree_pending_changes WHERE user = ? ORDER BY date DESC", $user);
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

		static function getPendingNavigationByParent($parent,$in_nav = true) {
			$nav = $titles = array();
			$changes = static::$DB->fetchAll("SELECT * FROM bigtree_pending_changes 
										  WHERE pending_page_parent = ? AND `table` = 'bigtree_pages' AND item_id IS NULL 
										  ORDER BY date DESC", $parent);

			foreach ($changes as $change) {
				$page = json_decode($change["changes"],true);

				// Only get the portion we're asking for
				if (($page["in_nav"] && $in_nav) || (!$page["in_nav"] && !$in_nav)) {
					$page["bigtree_pending"] = true;
					$page["title"] = $page["nav_title"];
					$page["id"] = "p".$change["id"];
					
					$titles[] = $page["nav_title"];
					$nav[] = $page;
				}
			}

			// Sort by title
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

		static function getContentsOfResourceFolder($folder, $sort = "date DESC") {
			if ($folder) {
				$folder = new BigTree\ResourceFolder($folder);
			} else {
				$folder = BigTree\ResourceFolder::root();
			}

			return $folder->Contents;
		}

		/*
			Function: getResourceByFile
				Returns a resource with the given file name.

			Parameters:
				file - The file name.

			Returns:
				An entry from bigtree_resources with file and thumbs decoded.
		*/

		static function getResourceByFile($file) {
			$resource = BigTree\Resource::getByFile($file);
			return $resource ? $resource->Array : false;
		}

		/*
			Function: getResource
				Returns a resource.

			Parameters:
				id - The id of the resource.

			Returns:
				A resource entry with thumbnails decoded.
		*/

		static function getResource($id) {
			$resource = new BigTree\Resource($id);
			return $resource->Array;
		}

		/*
			Function: getResourceAllocation
				Returns the places a resource is used.

			Parameters:
				id - The id of the resource.

			Returns:
				An array of entries from the bigtree_resource_allocation table.
		*/

		static function getResourceAllocation($id) {
			return BigTreeCMS::$DB->fetchAll("SELECT * FROM bigtree_resource_allocation WHERE resource = ? ORDER BY updated_at DESC", $id);
		}

		/*
			Function: getResourceFolder
				Returns a resource folder.

			Parameters:
				id - The id of the folder.

			Returns:
				A resource folder entry.
		*/

		static function getResourceFolder($id) {
			$folder = new BigTree\ResourceFolder($id);
			return $folder->Array;
		}

		/*
			Function: getResourceFolderAllocationCounts
				Returns the number of items inside a folder and it's subfolders and the number of allocations of the contained resources.

			Parameters:
				folder - The id of the folder.

			Returns:
				A keyed array of "resources", "folders", and "allocations" for the number of resources, sub folders, and allocations.
		*/

		static function getResourceFolderAllocationCounts($folder) {
			$folder = new BigTree\ResourceFolder($folder);
			return $folder->Statistics;
		}

		/*
			Function: getResourceFolderBreadcrumb
				Returns a breadcrumb of the given folder.

			Parameters:
				folder - The id of a folder or a folder entry.

			Returns:
				An array of arrays containing the name and id of folders above.
		*/

		static function getResourceFolderBreadcrumb($folder,$crumb = array()) {
			$folder = new BigTree\ResourceFolder($folder);
			return $folder->Breadcrumb;
		}

		/*
			Function: getResourceFolderChildren
				Returns the child folders of a resource folder.

			Parameters:
				id - The id of the parent folder.

			Returns:
				An array of resource folder entries.
		*/

		static function getResourceFolderChildren($id) {
			return BigTree\ResourceFolder::allByParent($id,"name ASC",true);
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
			return BigTree\ResourceFolder::access($folder);
		}

		/*
			Function: getRoutedTemplates
				Returns a list of routed templates ordered by position that the logged in user has access to.

			Parameters:
				sort - Sort order, defaults to positioned

			Returns:
				An array of template entries.
		*/

		function getRoutedTemplates($sort = "position DESC, id ASC") {
			$list = BigTree\Template::allByRouted("on",$sort,true);
			foreach ($list as $key => $template) {
				if ($template["level"] > $this->Level) {
					unset($list[$key]);
				}
			}
			return $list;
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

		static function getSetting($id,$decode = true) {
			$setting = new BigTree\Setting($id,$decode);
			return $setting->Array;
		}

		/*
			Function: getSettings
				Returns a list of all settings that the logged in user has access to.

			Parameters:
				sort - Order to return the settings. Defaults to name ASC.

			Returns:
				An array of entries from bigtree_settings.
				If the setting is encrypted the value will be "[Encrypted Text]", otherwise it will be decoded.
				If the calling user is a developer, returns locked settings, otherwise they are left out.
		*/

		function getSettings($sort = "name ASC") {
			$settings = BigTree\Setting::all($sort,true);
			
			if ($this->Level > 1) {
				return $settings;
			}

			// Only draw settings the admin can use
			$filtered_settings = array();
			foreach ($settings as $setting) {
				if (!$setting["locked"]) {
					$filtered_settings[] = $setting;
				}
			}

			return $filtered_settings;
		}

		/*
			Function: getSystemSettings
				Returns a list of user defined (no bigtree-internal- prefix) system settings without decoded values.

			Parameters:
				sort - Order to return the settings. Defaults to name ASC.

			Returns:
				An array of entries from bigtree_settings.
		*/

		static function getSystemSettings($sort = "name ASC") {
			return static::$DB->fetchAll("SELECT * FROM bigtree_settings 
										  WHERE id NOT LIKE 'bigtree-internal-%' AND system != '' ORDER BY $sort");
		}

		/*
			Function: getTag
				Returns a tag for the given id.

			Parameters:
				id - The id of the tag.

			Returns:
				A bigtree_tags entry.
		*/

		static function getTag($id) {
			$tag = new BigTree\Tag($id);
			return $tag->Array;
		}

		/*
			Function: getTemplates
				Returns a list of templates.

			Parameters:
				sort - Sort order, defaults to positioned.

			Returns:
				An array of template entries.
		*/

		static function getTemplates($sort = "position DESC, name ASC") {
			return BigTree\Template::all($sort,true);
		}

		/*
			Function: getUnreadMessageCount
				Returns the number of unread messages for the logged in user.

			Returns:
				The number of unread messages.
		*/

		function getUnreadMessageCount() {
			return static::$DB->fetchSingle("SELECT COUNT(*) FROM bigtree_messages 
											 WHERE recipients LIKE '%|".$this->ID."|%' AND read_by NOT LIKE '%|".$this->ID."|%'");
		}

		/*
			Function: getUser
				Gets a user's decoded information.

			Parameters:
				id - The id of the user to return.

			Returns:
				A user entry from bigtree_users with permissions and alerts decoded.
		*/

		static function getUser($id) {
			$user = new BigTree\User($id);
			return $user->Array;
		}

		/*
			Function: getUserByEmail
				Gets a user entry for a given email.

			Parameters:
				email - The email to find.

			Returns:
				A user entry from bigtree_users.
		*/

		static function getUserByEmail($email) {
			$user = BigTree\User::getByEmail($email);
			
			if ($user) {
				return $user->Array;
			}

			return false;
		}

		/*
			Function: getUserByHash
				Gets a user entry for a change password hash.

			Parameters:
				hash - The hash to find.

			Returns:
				A user entry from bigtree_users.
		*/

		static function getUserByHash($hash) {
			$user = BigTree\User::getByHash($hash);

			if ($user) {
				return $user->Array;
			}

			return false;
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

		static function getUsers($sort = "name ASC") {
			return BigTree\User::all($sort);
		}

		/*
			Function: growl
				Sets up a growl session for the next page reload.

			Parameters:
				title - The section message for the growl.
				message - The description of what happened.
				type - The icon to draw.
		*/

		static function growl($title,$message,$type = "success") {
			$_SESSION["bigtree_admin"]["growl"] = array("message" => $message, "title" => $title, "type" => $type);
		}

		/*
			Function: htmlClean
				Removes things that shouldn't be in the <body> of an HTML document from a string.

			Parameters:
				html - A string of HTML

			Returns:
				A clean string of HTML for echoing in <body>
		*/

		static function htmlClean($html) {
			return str_replace("<br></br>","<br>",strip_tags($html,"<a><abbr><address><area><article><aside><audio><b><base><bdo><blockquote><body><br><button><canvas><caption><cite><code><col><colgroup><command><datalist><dd><del><details><dfn><div><dl><dt><em><emded><fieldset><figcaption><figure><footer><form><h1><h2><h3><h4><h5><h6><header><hgroup><hr><i><iframe><img><input><ins><keygen><kbd><label><legend><li><link><map><mark><menu><meter><nav><noscript><object><ol><optgroup><option><output><p><param><pre><progress><q><rp><rt><ruby><s><samp><script><section><select><small><source><span><strong><style><sub><summary><sup><table><tbody><td><textarea><tfoot><th><thead><time><title><tr><ul><var><video><wbr>"));
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

			static::$DB->update("bigtree_404s",$id,array("ignored" => "on"));

			$this->track("bigtree_404s",$id,"ignored");
		}

		/*
			Function: initSecurity
				Sets up security environment variables and runs white/blacklists for IP checks.
		*/

		function initSecurity() {
			global $bigtree;
			$ip = ip2long($_SERVER["REMOTE_ADDR"]);
			$bigtree["security-policy"] = $policy = BigTreeCMS::getSetting("bigtree-internal-security-policy");

			// Check banned IPs list for the user's IP
			if (!empty($policy["banned_ips"])) {
				$banned = explode("\n",$policy["banned_ips"]);
				foreach ($banned as $address) {
					if (ip2long(trim($address)) == $ip) {
						$bigtree["layout"] = "login";
						$this->stop(file_get_contents(BigTree::path("admin/pages/ip-restriction.php")));
					}
				}
			}

			// Check allowed IP ranges list for user's IP
			if (!empty($policy["allowed_ips"])) {
				$allowed = false;
				// Go through the list and see if our IP address is allowed
				$list = explode("\n",$policy["allowed_ips"]);
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

		function installExtension($manifest,$upgrade = false) {
			$extension = BigTree\Extension::createFromManifest($manifest,$upgrade);
			return $extension->Manifest;
		}

		/*
			Function: iplExists
				Determines whether an internal page link still exists or not.

			Parameters:
				ipl - An internal page link

			Returns:
				True if it is still a valid link, otherwise false.
		*/

		static function iplExists($ipl) {
			return BigTree\Link::iplExists($ipl);
		}

		/*
			Function: irlExists
				Determines whether an internal resource link still exists or not.

			Parameters:
				irl - An internal resource link

			Returns:
				True if it is still a valid link, otherwise false.
		*/

		static function irlExists($irl) {
			return BigTree\Link::irlExists($irl);
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
			global $admin,$bigtree,$cms,$db;

			$lock = static::$DB->fetch("SELECT * FROM bigtree_locks WHERE `table` = ? AND item_id = ?", $table, $id);

			if ($lock && $lock["user"] != $this->ID && strtotime($lock["last_accessed"]) > (time()-300) && !$force) {
				$locked_by = static::getUser($lock["user"]);
				$last_accessed = $lock["last_accessed"];
				
				include BigTree::path($include);
				
				if ($in_admin) {
					$this->stop();
				}
				
				return false;
			}

			// We're taking over the lock
			if ($lock) {
				static::$DB->update("bigtree_locks",$lock["id"],array(
					"user" => $this->ID
				));

				return $lock["id"];
			
			// No lock, we're creating a new one
			} else {
				return static::$DB->insert("bigtree_locks",array(
					"table" => $table,
					"item_id" => $id,
					"user" => $this->ID
				));
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
				false if login failed, true if successful
		*/

		function login($email,$password,$stay_logged_in = false) {
			return $this->Auth->login($email,$password,$stay_logged_in);
		}

		/*
			Function: logout
				Logs out of the CMS.
				Destroys the user's session and unsets the login cookies, then sends the user back to the login page.
		*/

		static function logout() {
			$this->Auth->logout();
		}

		/*
			Function: makeIPL
				Creates an internal page link out of a URL.

			Parameters:
				url - A URL

			Returns:
				An internal page link (if possible) or just the same URL (if it's not internal).
		*/

		static function makeIPL($url) {
			// See if this is a file
			$local_path = str_replace(WWW_ROOT,SITE_ROOT,$url);
			if ((substr($local_path,0,1) == "/" || substr($local_path,0,2) == "\\\\") && file_exists($local_path)) {
				return BigTreeCMS::replaceHardRoots($url);
			}

			$command = explode("/",rtrim(str_replace(WWW_ROOT,"",$url),"/"));
			// Check for resource link
			if ($command[0] == "files" && $command[1] == "resources") {
				$resource = static::getResourceByFile($url);
				if ($resource) {
					static::$IRLsCreated[] = $resource["id"];
					return "irl://".$resource["id"]."//".$resource["prefix"];
				}
			}
			// Check for page link
			list($navid,$commands) = static::getPageIDForPath($command);
			if (!$navid) {
				return BigTreeCMS::replaceHardRoots($url);
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
			static::$DB->update("bigtree_messages",$message["id"],array("read_by" => $read_by));
			
			return true;
		}

		/*
			Function: matchResourceMD5
				Checks if the given file is a MD5 match for any existing resources.
				If a match is found, the resource is "copied" into the given folder (unless it already exists in that folder).

			Parameters:
				file - Uploaded file to run MD5 hash on
				new_folder - Folder the given file is being uploaded into

			Returns:
				true if a match was found. If the file was already in the given folder, the date is simply updated.
		*/

		static function matchResourceMD5($file,$new_folder) {
			return BigTree\Resource::md5Check($file,$new_folder);
		}

		/*
			Function: pageChangeExists
				Returns whether pending changes exist for a given page.

			Parameters:
				page - The page id.

			Returns:
				true or false
		*/

		static function pageChangeExists($page) {
			return static::$DB->exists("bigtree_pending_changes",array("table" => "bigtree_pages", "item_id" => $page));
		}

		/*
			Function: pingSearchEngines
				Sends the latest sitemap.xml out to search engine ping services if enabled in settings.
		*/

		static function pingSearchEngines() {
			$setting = static::getSetting("ping-search-engines");
			if ($setting["value"] == "on") {
				// Google
				BigTree::cURL("http://www.google.com/webmasters/tools/ping?sitemap=".urlencode(WWW_ROOT."sitemap.xml"));
				// Ask
				BigTree::cURL("http://submissions.ask.com/ping?sitemap=".urlencode(WWW_ROOT."sitemap.xml"));
				// Yahoo
				BigTree::cURL("http://search.yahooapis.com/SiteExplorerService/V1/ping?sitemap=".urlencode(WWW_ROOT."sitemap.xml"));
				// Bing
				BigTree::cURL("http://www.bing.com/webmaster/ping.aspx?siteMap=".urlencode(WWW_ROOT."sitemap.xml"));
			}
		}

		/*
			Function: processCrops
				Processes a list of cropped images.

			Parameters:
				crop_key - A cache key pointing to the location of crop data.
		*/

		static function processCrops($crop_key) {
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

				$pinfo = pathinfo($image_src);

				// Create the crop and put it in a temporary location
				$temp_crop = SITE_ROOT."files/".uniqid("temp-").".".$pinfo["extension"];
				BigTree::createCrop($image_src,$temp_crop,$x,$y,$target_width,$target_height,$width,$height,$crop["retina"],$crop["grayscale"]);
				
				// Make thumbnails for the crop
				if (is_array($thumbs)) {
					foreach ($thumbs as $thumb) {
						if (is_array($thumb) && ($thumb["height"] || $thumb["width"])) {
							// We're going to figure out what size the thumbs will be so we can re-crop the original image so we don't lose image quality.
							list($type,$w,$h,$result_width,$result_height) = BigTree::getThumbnailSizes($temp_crop,$thumb["width"],$thumb["height"]);

							$temp_thumb = SITE_ROOT."files/".uniqid("temp-").".".$pinfo["extension"];
							BigTree::createCrop($image_src,$temp_thumb,$x,$y,$result_width,$result_height,$width,$height,$crop["retina"],$thumb["grayscale"]);
							$storage->replace($temp_thumb,$thumb["prefix"].$crop["name"],$crop["directory"]);
						}
					}
				}

				// Make center crops of the crop
				if (is_array($center_crops)) {
					foreach ($center_crops as $center_crop) {
						if (is_array($center_crop) && $center_crop["height"] && $center_crop["width"]) {
							$temp_center_crop = SITE_ROOT."files/".uniqid("temp-").".".$pinfo["extension"];
							BigTree::centerCrop($temp_crop,$temp_center_crop,$center_crop["width"],$center_crop["height"],$crop["retina"],$center_crop["grayscale"]);
							$storage->replace($temp_center_crop,$center_crop["prefix"].$crop["name"],$crop["directory"]);
						}
					}
				}

				// Move crop into its resting place
				$storage->replace($temp_crop,$crop["prefix"].$crop["name"],$crop["directory"]);
			}

			// Remove all the temporary images
			foreach ($crops as $crop) {
				BigTree::deleteFile($crop["image"]);
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

		static function processField($field) {
			$field = new BigTree\Field($field);
			return $field->process();
		}

		/*
			Function: processImageUpload
				Processes image upload data for form fields.
				If you're emulating field information, the following keys are of interest in the field array:
				"file_input" - a keyed array that needs at least "name" and "tmp_name" keys that contain the desired name of the file and the source file location, respectively.
				"options" - a keyed array of options for the field, keys of interest for photo processing are:
					"min_height" - Minimum Height required for the image
					"min_width" - Minimum Width required for the image
					"retina" - Whether to try to create a 2x size image when thumbnailing / cropping (if the source file / crop is large enough)
					"thumbs" - An array of thumbnail arrays, each of which has "prefix", "width", "height", and "grayscale" keys (prefix is prepended to the file name when creating the thumbnail, grayscale will make the thumbnail grayscale)
					"crops" - An array of crop arrays, each of which has "prefix", "width", "height" and "grayscale" keys (prefix is prepended to the file name when creating the crop, grayscale will make the thumbnail grayscale)). Crops can also have their own "thumbs" key that creates thumbnails of each crop (format mirrors that of "thumbs")

			Parameters:
				field - Field information (normally set to $field when running a field type's process file)
		*/

		static function processImageUpload($field) {
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

			// We're going to tell BigTreeStorage to handle forcing images into JPEGs instead of writing the code 20x
			$storage = new BigTreeStorage;
			$storage->AutoJPEG = $bigtree["config"]["image_force_jpeg"];

			// Let's check the minimum requirements for the image first before we store it anywhere.
			$image_info = @getimagesize($temp_name);
			$iwidth = $image_info[0];
			$iheight = $image_info[1];
			$itype = $image_info[2];
			$channels = $image_info["channels"];

			// See if we're using image presets
			if ($field["options"]["preset"]) {
				$media_settings = BigTreeCMS::getSetting("bigtree-internal-media-settings");
				$preset = $media_settings["presets"][$field["options"]["preset"]];
				// If the preset still exists, copy its properties over to our options
				if ($preset) {
					foreach ($preset as $key => $val) {
						$field["options"][$key] = $val;
					}
				}
			}

			// If the minimum height or width is not meant, do NOT let the image through.  Erase the change or update from the database.
			if ((isset($field["options"]["min_height"]) && $iheight < $field["options"]["min_height"]) || (isset($field["options"]["min_width"]) && $iwidth < $field["options"]["min_width"])) {
				$error = "Image uploaded (".htmlspecialchars($name).") did not meet the minimum size of ";
				if ($field["options"]["min_height"] && $field["options"]["min_width"]) {
					$error .= $field["options"]["min_width"]."x".$field["options"]["min_height"]." pixels.";
				} elseif ($field["options"]["min_height"]) {
					$error .= $field["options"]["min_height"]." pixels tall.";
				} elseif ($field["options"]["min_width"]) {
					$error .= $field["options"]["min_width"]." pixels wide.";
				}
				$bigtree["errors"][] = array("field" => $field["title"], "error" => $error);
				$failed = true;
			}

			// If it's not a valid image, throw it out!
			if ($itype != IMAGETYPE_GIF && $itype != IMAGETYPE_JPEG && $itype != IMAGETYPE_PNG) {
				$bigtree["errors"][] = array("field" => $field["title"], "error" =>  "An invalid file was uploaded. Valid file types: JPG, GIF, PNG.");
				$failed = true;
			}

			// See if it's CMYK
			if ($channels == 4) {
				$bigtree["errors"][] = array("field" => $field["title"], "error" =>  "A CMYK encoded file was uploaded. Please upload an RBG image.");
				$failed = true;
			}

			// See if we have enough memory for all our crops and thumbnails
			if (!$failed && ((is_array($field["options"]["crops"]) && count($field["options"]["crops"])) || (is_array($field["options"]["thumbs"]) && count($field["options"]["thumbs"])))) {
				if (is_array($field["options"]["crops"])) {
					foreach ($field["options"]["crops"] as $crop) {
						if (!$failed && is_array($crop) && array_filter($crop)) {
							if ($field["options"]["retina"]) {
								$crop["width"] *= 2;
								$crop["height"] *= 2;
							}
							// We don't want to add multiple errors so we check if we've already failed
							if (!BigTree::imageManipulationMemoryAvailable($temp_name,$crop["width"],$crop["height"])) {
								$bigtree["errors"][] = array("field" => $field["title"], "error" => "Image uploaded is too large for the server to manipulate. Please upload a smaller version of this image.");
								$failed = true;
							}
						}
					}
				}
				if (is_array($field["options"]["thumbs"])) {
					foreach ($field["options"]["thumbs"] as $thumb) {
						// We don't want to add multiple errors and we also don't want to waste effort getting thumbnail sizes if we already failed.
						if (!$failed && is_array($thumb) && array_filter($thumb)) {
							if ($field["options"]["retina"]) {
								$thumb["width"] *= 2;
								$thumb["height"] *= 2;
							}
							$sizes = BigTree::getThumbnailSizes($temp_name,$thumb["width"],$thumb["height"]);
							if (!BigTree::imageManipulationMemoryAvailable($temp_name,$sizes[3],$sizes[4])) {
								$bigtree["errors"][] = array("field" => $field["title"], "error" => "Image uploaded is too large for the server to manipulate. Please upload a smaller version of this image.");
								$failed = true;
							}
						}
					}
				}
				if (is_array($field["options"]["center_crops"])) {
					foreach ($field["options"]["center_crops"] as $crop) {
						// We don't want to add multiple errors and we also don't want to waste effort getting thumbnail sizes if we already failed.
						if (!$failed && is_array($crop) && array_filter($crop)) {
							list($w,$h) = getimagesize($temp_name);
							if (!BigTree::imageManipulationMemoryAvailable($temp_name,$w,$h)) {
								$bigtree["errors"][] = array("field" => $field["title"], "error" => "Image uploaded is too large for the server to manipulate. Please upload a smaller version of this image.");
								$failed = true;
							}
						}
					}
				}
			}

			if (!$failed) {
				// Make a temporary copy to be used for thumbnails and crops.
				$itype_exts = array(IMAGETYPE_PNG => ".png", IMAGETYPE_JPEG => ".jpg", IMAGETYPE_GIF => ".gif");

				// Make a first copy
				$first_copy = SITE_ROOT."files/".uniqid("temp-").$itype_exts[$itype];
				BigTree::moveFile($temp_name,$first_copy);

				// Do EXIF Image Rotation
				if ($itype == IMAGETYPE_JPEG && function_exists("exif_read_data")) {
					$exif = @exif_read_data($first_copy);
					$o = $exif['Orientation'];
					if ($o == 3 || $o == 6 || $o == 8) {
						$source = imagecreatefromjpeg($first_copy);

						if ($o == 3) {
							$source = imagerotate($source,180,0);
						} elseif ($o == 6) {
							$source = imagerotate($source,270,0);
						} else {
							$source = imagerotate($source,90,0);
						}

						// We're going to create a PNG so that we don't lose quality when we resave
						imagepng($source,$first_copy);
						rename($first_copy,substr($first_copy,0,-3)."png");
						$first_copy = substr($first_copy,0,-3)."png";

						// Force JPEG since we made the first copy a PNG
						$storage->AutoJPEG = true;

						// Clean up memory
						imagedestroy($source);

						// Get new width/height/type
						list($iwidth,$iheight,$itype,$iattr) = getimagesize($first_copy);
					}
				}

				// Create a temporary copy that we will use later for crops and thumbnails
				$temp_copy = SITE_ROOT."files/".uniqid("temp-").$itype_exts[$itype];
				BigTree::copyFile($first_copy,$temp_copy);

				// Gather up an array of file prefixes
				$prefixes = array();
				if (is_array($field["options"]["thumbs"])) {
					foreach ($field["options"]["thumbs"] as $thumb) {
						if (!empty($thumb["prefix"])) {
							$prefixes[] = $thumb["prefix"];
						}
					}
				}
				if (is_array($field["options"]["center_crops"])) {
					foreach ($field["options"]["center_crops"] as $crop) {
						if (!empty($crop["prefix"])) {
							$prefixes[] = $crop["prefix"];
						}
					}
				}
				if (is_array($field["options"]["crops"])) {
					foreach ($field["options"]["crops"] as $crop) {
						if (is_array($crop)) {
							if (!empty($crop["prefix"])) {
								$prefixes[] = $crop["prefix"];
							}
							if (is_array($crop["thumbs"])) {
								foreach ($crop["thumbs"] as $thumb) {
									if (!empty($thumb["prefix"])) {
										$prefixes[] = $thumb["prefix"];
									}
								}
							}
							if (is_array($crop["center_crops"])) {
								foreach ($crop["center_crops"] as $center_crop) {
									if (!empty($center_crop["prefix"])) {
										$prefixes[] = $center_crop["prefix"];
									}
								}
							}
						}
					}
				}

				// Upload the original to the proper place.
				$field["output"] = $storage->store($first_copy,$name,$field["options"]["directory"],true,$prefixes);

 				// If the upload service didn't return a value, we failed to upload it for one reason or another.
 				if (!$field["output"]) {
 					if ($storage->DisabledFileError) {
						$bigtree["errors"][] = array("field" => $field["title"], "error" => "Could not upload file. The file extension is not allowed.");
					} else {
						$bigtree["errors"][] = array("field" => $field["title"], "error" => "Could not upload file. The destination is not writable.");
					}
					unlink($temp_copy);
					unlink($first_copy);

				    // Failed, we keep the current value
					return false;
				// If we did upload it successfully, check on thumbs and crops.
				} else {
					// Get path info on the file.
					$pinfo = BigTree::pathInfo($field["output"]);

					// Handle Crops
					if (is_array($field["options"]["crops"])) {
						foreach ($field["options"]["crops"] as $crop) {
							if (is_array($crop)) {
								// Make sure the crops have a width/height and it's numeric
								if ($crop["width"] && $crop["height"] && is_numeric($crop["width"]) && is_numeric($crop["height"])) {
									$cwidth = $crop["width"];
									$cheight = $crop["height"];
		
									// Check to make sure each dimension is greater then or equal to, but not both equal to the crop.
									if (($iheight >= $cheight && $iwidth > $cwidth) || ($iwidth >= $cwidth && $iheight > $cheight)) {
										// Make a square if for some reason someone only entered one dimension for a crop.
										if (!$cwidth) {
											$cwidth = $cheight;
										} elseif (!$cheight) {
											$cheight = $cwidth;
										}
										$bigtree["crops"][] = array(
											"image" => $temp_copy,
											"directory" => $field["options"]["directory"],
											"retina" => $field["options"]["retina"],
											"name" => $pinfo["basename"],
											"width" => $cwidth,
											"height" => $cheight,
											"prefix" => $crop["prefix"],
											"thumbs" => $crop["thumbs"],
											"center_crops" => $crop["center_crops"],
											"grayscale" => $crop["grayscale"]
										);
									// If it's the same dimensions, let's see if they're looking for a prefix for whatever reason...
									} elseif ($iheight == $cheight && $iwidth == $cwidth) {
										// See if we want thumbnails
										if (is_array($crop["thumbs"])) {
											foreach ($crop["thumbs"] as $thumb) {
												// Make sure the thumbnail has a width or height and it's numeric
												if (($thumb["width"] && is_numeric($thumb["width"])) || ($thumb["height"] && is_numeric($thumb["height"]))) {
													// Create a temporary thumbnail of the image on the server before moving it to it's destination.
													$temp_thumb = SITE_ROOT."files/".uniqid("temp-").$itype_exts[$itype];
													BigTree::createThumbnail($temp_copy,$temp_thumb,$thumb["width"],$thumb["height"],$field["options"]["retina"],$thumb["grayscale"]);
													// We use replace here instead of upload because we want to be 100% sure that this file name doesn't change.
													$storage->replace($temp_thumb,$thumb["prefix"].$pinfo["basename"],$field["options"]["directory"]);
												}
											}
										}
	
										// See if we want center crops
										if (is_array($crop["center_crops"])) {
											foreach ($crop["center_crops"] as $center_crop) {
												// Make sure the crop has a width and height and it's numeric
												if ($center_crop["width"] && is_numeric($center_crop["width"]) && $center_crop["height"] && is_numeric($center_crop["height"])) {
													// Create a temporary crop of the image on the server before moving it to it's destination.
													$temp_crop = SITE_ROOT."files/".uniqid("temp-").$itype_exts[$itype];
													BigTree::centerCrop($temp_copy,$temp_crop,$center_crop["width"],$center_crop["height"],$field["options"]["retina"],$center_crop["grayscale"]);
													// We use replace here instead of upload because we want to be 100% sure that this file name doesn't change.
													$storage->replace($temp_crop,$center_crop["prefix"].$pinfo["basename"],$field["options"]["directory"]);
												}
											}
										}
										
										if ($crop["prefix"]) {
											$storage->store($temp_copy,$crop["prefix"].$pinfo["basename"],$field["options"]["directory"],false);
										}
									}
								}
							}
						}
					}

					// Handle thumbnailing
					if (is_array($field["options"]["thumbs"])) {
						foreach ($field["options"]["thumbs"] as $thumb) {
							// Make sure the thumbnail has a width or height and it's numeric
							if (($thumb["width"] && is_numeric($thumb["width"])) || ($thumb["height"] && is_numeric($thumb["height"]))) {
								$temp_thumb = SITE_ROOT."files/".uniqid("temp-").$itype_exts[$itype];
								BigTree::createThumbnail($temp_copy,$temp_thumb,$thumb["width"],$thumb["height"],$field["options"]["retina"],$thumb["grayscale"]);
								// We use replace here instead of upload because we want to be 100% sure that this file name doesn't change.
								$storage->replace($temp_thumb,$thumb["prefix"].$pinfo["basename"],$field["options"]["directory"]);
							}
						}
					}

					// Handle center crops
					if (is_array($field["options"]["center_crops"])) {
						foreach ($field["options"]["center_crops"] as $crop) {
							// Make sure the crop has a width and height and it's numeric
							if ($crop["width"] && is_numeric($crop["width"]) && $crop["height"] && is_numeric($crop["height"])) {
								$temp_crop = SITE_ROOT."files/".uniqid("temp-").$itype_exts[$itype];
								BigTree::centerCrop($temp_copy,$temp_crop,$crop["width"],$crop["height"],$field["options"]["retina"],$crop["grayscale"]);
								// We use replace here instead of upload because we want to be 100% sure that this file name doesn't change.
								$storage->replace($temp_crop,$crop["prefix"].$pinfo["basename"],$field["options"]["directory"]);
							}
						}
					}

					// If we don't have any crops, get rid of the temporary image we made.
					if (!count($bigtree["crops"])) {
						unlink($temp_copy);
					}
				}
			// We failed, keep the current value.
			} else {
				return false;
			}

			return $field["output"];
		}

		/*
			Function: refreshLock
				Refreshes a lock.

			Parameters:
				table - The table for the lock.
				id - The id of the item.
		*/

		function refreshLock($table,$id) {
			// Update the access time
			static::$DB->update("bigtree_locks",array("table" => $table,"item_id" => $id, "user" => $this->ID), array("last_accessed" => "NOW()"));
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
			global $admin,$bigtree,$cms,$db;
			
			// Admins are automatically publishers
			if ($this->Level > 0) {
				return "p";
			}

			// Not set or empty, no access
			if (!isset($this->Permissions[$module]) || $this->Permissions[$module] == "") {
				define("BIGTREE_ACCESS_DENIED",true);
				$this->stop(file_get_contents(BigTree::path("admin/pages/_denied.php")));
			}

			// Return level defined
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
			global $admin,$bigtree,$cms,$db;

			// If we aren't logged in or the logged in level is less than required, denied.
			if (!isset($this->Level) || $this->Level < $level) {
				define("BIGTREE_ACCESS_DENIED",true);
				$this->stop(file_get_contents(BigTree::path("admin/pages/_denied.php")));
			}

			return true;
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
			global $admin,$bigtree,$cms,$db;

			// Admins are publishers
			if ($this->Level > 0) {
				return true;
			}

			// Require explicit publisher access
			if ($this->Permissions[$module] != "p") {
				define("BIGTREE_ACCESS_DENIED",true);
				$this->stop(file_get_contents(BigTree::path("admin/pages/_denied.php")));
			}

			return true;
		}

		/*
			Function: runCron
				Runs cron jobs
		*/

		function runCron() {
			global $bigtree;

			// Track when we last sent a daily digest
			if (!$this->settingExists("bigtree-internal-cron-daily-digest-last-sent")) {
				$this->createSetting(array(
					"id" => "bigtree-internal-cron-daily-digest-last-sent",
					"system" => "on"
				));
			}
		
			$last_sent_daily_digest = BigTreeCMS::getSetting("bigtree-internal-cron-daily-digest-last-sent");
		
			// If we last sent the daily digest > ~24 hours ago, send it again. Also refresh analytics.
			if ($last_sent_daily_digest < strtotime("-23 hours 59 minutes")) {
				$this->updateSettingValue("bigtree-internal-cron-daily-digest-last-sent",time());
		
				// Send daily digest
				$this->emailDailyDigest();
		
				// Cache Google Analytics Information
				$analytics = new BigTreeGoogleAnalyticsAPI;
				if ($analytics->API && $analytics->Profile) {
					$analytics->cacheInformation();
				}

				// Ping bigtreecms.org with current version stats
				if (!$bigtree["config"]["disable_ping"]) {
					BigTree::cURL("https://www.bigtreecms.org/ajax/ping/?www_root=".urlencode(WWW_ROOT)."&version=".urlencode(BIGTREE_VERSION));
				}
			}
		
			// Run any extension cron jobs
			$extension_settings = BigTreeCMS::getSetting("bigtree-internal-extension-settings");
			$cron_settings = $extension_settings["cron"];
			foreach (BigTreeAdmin::$CronPlugins as $extension => $plugins) {
				foreach ($plugins as $id => $details) {
					$id = $extension."*".$id;
					if (empty($cron_settings[$id]["disabled"])) {
						call_user_func($details["function"]);
					}
				}
			}
		
			// Let the CMS know we're running cron properly
			if (!$this->settingExists("bigtree-internal-cron-last-run")) {
				$this->createSetting(array(
					"id" => "bigtree-internal-cron-last-run",
					"system" => "on"
				));
			}
			
			// Tell the admin we've ran cron recently.
			$this->updateSettingValue("bigtree-internal-cron-last-run",time());
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
			// Get the current page.
			$current = static::$DB->fetch("SELECT * FROM bigtree_pages WHERE id = ?", $page);
			
			// Make a copy
			$id = static::$DB->insert("bigtree_page_revisions",array(
				"page" => $page,
				"title" => $current["title"],
				"meta_keywords" => $current["meta_keywords"],
				"meta_description" => $current["meta_description"],
				"template" => $current["template"],
				"external" => $current["external"],
				"new_window" => $current["new_window"],
				"resources" => $current["resources"],
				"author" => $current["last_edited_by"],
				"updated_at" => $current["updated_at"],
				"saved" => "on",
				"saved_description" => $description
			));

			$this->track("bigtree_page_revisions",$id,"created");
			return $id;
		}

		/*
			Function: search404s
				Searches 404s, returns results.

			Parameters:
				type - The type of results (301, 404, or ignored).
				query - The search query.
				page - The page to return.

			Returns:
				An array of entries from bigtree_404s.
		*/

		static function search404s($type,$query = "",$page = 1) {
			$items = array();

			if ($query) {
				$query = static::$DB->escape($query);
				if ($type == "301") {
					$where = "ignored = '' AND (broken_url LIKE '%$query%' OR redirect_url LIKE '%$query%') AND redirect_url != ''";
				} elseif ($type == "ignored") {
					$where = "ignored != '' AND (broken_url LIKE '%$query%' OR redirect_url LIKE '%$query%')";
				} else {
					$where = "ignored = '' AND broken_url LIKE '%$query%' AND redirect_url = ''";
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
			$result_count = static::$DB->fetchSingle("SELECT COUNT(*) AS `count` FROM bigtree_404s WHERE $where");
			$pages = ceil($result_count / 20);
			// Return 1 page even if there are 0
			$pages = $pages ? $pages : 1;

			// Get the results
			$results = static::$DB->fetchAll("SELECT * FROM bigtree_404s WHERE $where 
											  ORDER BY requests DESC LIMIT ".(($page - 1) * 20).",20");
			foreach ($results as &$result) {
				$result["redirect_url"] = BigTreeCMS::replaceInternalPageLinks($result["redirect_url"]);
			}

			return $results;
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

		static function searchAuditTrail($user = false,$table = false,$entry = false,$start = false,$end = false) {
			$users = $items = $where = $parameters = array();
			$query = "SELECT * FROM bigtree_audit_trail";

			if ($user) {
				$where[] = "user = ?";
				$parameters[] = $user;
			}
			if ($table) {
				$where[] = "`table` = ?";
				$parameters[] = $table;
			}
			if ($entry) {
				$where[] = "entry = ?";
				$parameters[] = $entry;
			}
			if ($start) {
				$where[] = "`date` >= '".date("Y-m-d H:i:s",strtotime($start))."'";
			}
			if ($end) {
				$where[] = "`date` <= '".date("Y-m-d H:i:s",strtotime($end))."'";
			}
			if (count($where)) {
				$query .= " WHERE ".implode(" AND ",$where);
			}

			$entries = static::$DB->fetchAll($query." ORDER BY `date` DESC");
			foreach ($entries as &$entry) {
				// Check the user cache
				if (!$users[$entry["user"]]) {
					$user = static::getUser($entry["user"]);
					$users[$entry["user"]] = array(
						"id" => $user["id"],
						"name" => $user["name"],
						"email" => $user["email"],
						"level" => $user["level"]
					);
				}

				$entry["user"] = $users[$entry["user"]];
			}

			return $entries;
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

		static function searchPages($query,$fields = array("nav_title"),$max = 10) {
			// Since we're in JSON we have to do stupid things to the /s for URL searches.
			$query = str_replace('/','\\\/',$query);

			$results = array();
			$terms = explode(" ",$query);
			$where_parts = array("archived != 'on'");

			foreach ($terms as $term) {
				$term = static::$DB->escape($term);
				
				$or_parts = array();
				foreach ($fields as $field) {
					$or_parts[] = "`$field` LIKE '%$term%'";
				}

				$where_parts[] = "(".implode(" OR ",$or_parts).")";
			}

			return static::$DB->fetchAll("SELECT * FROM bigtree_pages 
										  WHERE ".implode(" AND ",$where_parts)." 
										  ORDER BY nav_title LIMIT $max");
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
			return BigTree\Resource::search($query,$sort);
		}

		/*
			Function: searchTags
				Finds existing tags that are similar.

			Parameters:
				tag - A tag to find similar tags for.

			Returns:
				An array of up to 8 similar tags.
		*/

		static function searchTags($tag) {
			return BigTree\Tag::similar($tag,8,true);
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

			// Try to convert the short URL into a full one
			if (strpos($url,"//") === false) {
				$url = WWW_ROOT.ltrim($url,"/");
			}
			$url = htmlspecialchars($this->autoIPL($url));

			// Don't use static roots if they're the same as www just in case they are different when moving environments
			if (WWW_ROOT === STATIC_ROOT) {
				$url = str_replace("{staticroot}","{wwwroot}",$url);
			}

			static::$DB->update("bigtree_404s",$id,array("redirect_url" => $url));
			$this->track("bigtree_404s",$id,"updated");
		}

		/*
			Function: setCalloutPosition
				Sets the position of a callout.

			Parameters:
				id - The id of the callout.
				position - The position to set.
		*/

		static function setCalloutPosition($id,$position) {
			$callout = new BigTree\Callout($id);
			$callout->Position = $position;
			$callout->save();
		}

		/*
			Function: setModuleActionPosition
				Sets the position of a module action.

			Parameters:
				id - The id of the module action.
				position - The position to set.
		*/

		static function setModuleActionPosition($id,$position) {
			$action = new BigTree\ModuleAction($id);
			$action->Position = $position;
			$action->save();
		}

		/*
			Function: setModuleGroupPosition
				Sets the position of a module group.

			Parameters:
				id - The id of the module group.
				position - The position to set.
		*/

		static function setModuleGroupPosition($id,$position) {
			$group = new BigTree\ModuleGroup($id);
			$group->Position = $position;
			$group->save();
		}

		/*
			Function: setModulePosition
				Sets the position of a module.

			Parameters:
				id - The id of the module.
				position - The position to set.
		*/

		static function setModulePosition($id,$position) {
			$module = new BigTree\Module($id);
			$module->Position = $position;
			$module->save();
		}

		/*
			Function: setPagePosition
				Sets the position of a page.

			Parameters:
				id - The id of the page.
				position - The position to set.
		*/

		static function setPagePosition($id,$position) {
			static::$DB->update("bigtree_pages",$id,array("position" => $position));
		}

		/*
			Function: setPasswordHashForUser
				Creates a change password hash for a user.

			Parameters:
				user - A user entry.

			Returns:
				A change password hash.
		*/

		static function setPasswordHashForUser($user) {
			$user = new BigTree\User($user);
			return $user->setPasswordHash();
		}

		/*
			Function: setTemplatePosition
				Sets the position of a template.

			Parameters:
				id - The id of the template.
				position - The position to set.
		*/

		static function setTemplatePosition($id,$position) {
			$template = new BigTree\Template($id);
			$template->Position = $position;
			$template->save();
		}

		/*
			Function: settingExists
				Determines whether a setting exists for a given id.

			Parameters:
				id - The setting id to check for.

			Returns:
				1 if the setting exists, otherwise 0.
		*/

		static function settingExists($id) {
			return BigTree\Setting::exists($id);
		}

		/*
			Function: stop
				Stops processing of the Admin area and shows a message in the default layout.

			Parameters:
				message - Content to show (error, permission denied, etc)
				file - A file to load (optional, replaces message but $message will be available in the file)
		*/

		function stop($message = "",$file = "") {
			global $admin,$bigtree,$cms,$db;

			if ($file) {
				include $file;
			} else {
				echo $message;
			}

			$bigtree["content"] = ob_get_clean();
			include BigTree::path("admin/layouts/".$bigtree["layout"].".php");
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
			if ($page[0] == "p") {
				// It's still pending...
				$pending = true;
				$existing_page = array();
				$existing_pending_change = substr($page,1);
			} else {
				// It's an existing page
				$pending = false;
				$existing_page = BigTreeCMS::getPage($page);
				$existing_pending_change = static::$DB->fetchSingle("SELECT id FROM bigtree_pending_changes 
																	 WHERE `table` = 'bigtree_pages' AND item_id = ?", $page);
			}

			// Save tags separately
			$tags = BigTree::json($changes["_tags"],true);
			unset($changes["_tags"]);

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
					$diff = $changes;
				// Otherwise, we need to check what's changed.
				} else {

					// We don't want to indiscriminately put post data in as changes, so we ensure it matches a column in the bigtree_pages table
					$diff = array();
					foreach ($changes as $key => $val) {
						if (array_key_exists($key,$existing_page) && $existing_page[$key] != $val) {
							$diff[$key] = $val;
						}
					}
				}

				// Update existing draft and track
				static::$DB->update("bigtree_pending_changes",$existing_pending_change,array(
					"changes" => $diff,
					"tags_changes" => $tags,
					"user" => $this->ID
				));

				$this->track("bigtree_pages",$page,"updated-draft");
				return $existing_pending_change;

			// We're submitting a change to a presently published page with no pending changes.
			} else {
				$diff = array();
				foreach ($changes as $key => $val) {
					if (array_key_exists($key,$existing_page) && $val != $existing_page[$key]) {
						$diff[$key] = $val;
					}
				}

				// Create draft and track
				$this->track("bigtree_pages",$page,"saved-draft");

				return static::$DB->insert("bigtree_pending_changes",array(
					"user" => $this->ID,
					"table" => "bigtree_pages",
					"item_id" => $page,
					"changes" => $diff,
					"tags_changes" => $tags,
					"title" => "Page Change Pending"
				));
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

		static function track($table,$entry,$type) {
			BigTree\AuditTrail::track($table,$entry,$type);
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
			$page = is_array($page) ? $page["id"] : $page;
			$access_level = $this->getPageAccessLevel($page);

			if ($access_level == "p" && $this->canModifyChildren(BigTreeCMS::getPage($page))) {
				static::$DB->update("bigtree_pages",$page,array("archived" => ""));
				$this->unarchivePageChildren($page);

				$this->track("bigtree_pages",$page,"unarchived");
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
			$child_ids = static::$DB->fetchAllSingle("SELECT id FROM bigtree_pages WHERE parent = ? AND archived_inherited = 'on'", $id);
			foreach ($child_ids as $child_id) {
				$this->track("bigtree_pages",$child_id,"unarchived-inherited");
				$this->unarchivePageChildren($child_id);
			}

			// Unarchive this level
			static::$DB->query("UPDATE bigtree_pages
								SET archived = '', archived_inherited = '' 
								WHERE parent = ? AND archived_inherited = 'on'", $id);
		}

		/*
			Function: ungrowl
				Destroys the growl session.
		*/

		static function ungrowl() {
			unset($_SESSION["bigtree_admin"]["flash"]);
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

		static function urlExists($url) {
			return BigTree\Link::urlExists($url);
		}

		/*
			Function: unCache
				Removes the cached copy of a given page.

			Parameters:
				page - Either a page id or a page entry.
		*/

		static function unCache($page) {
			$get = array();
			if (is_array($page)) {
				if (!$page["path"]) {
					$get["bigtree_htaccess_url"] = "";
				} else {
					$get["bigtree_htaccess_url"] = $page["path"]."/";
				}
			} else {
				if ($page == 0) {
					$get["bigtree_htaccess_url"] = "";
				} else {
					$get["bigtree_htaccess_url"] = str_replace(WWW_ROOT,"",BigTreeCMS::getLink($page));
				}
			}
			BigTree::deleteFile(md5(json_encode($get)).".page");
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

			static::$DB->update("bigtree_404s",$id,array("ignored" => ""));
			$this->track("bigtree_404s",$id,"unignored");
		}

		/*
			Function: unlock
				Removes a lock from a table entry.

			Parameters:
				table - The table the entry is in.
				id - The id of the entry.
		*/

		static function unlock($table,$id) {
			static::$DB->delete("bigtree_locks",array("table" => $table, "item_id" => $id));
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

		function updateCallout($id,$name,$description,$level,$resources,$display_field,$display_default) {
			return BigTree\Callout::update($id,$name,$description,$level,$resources,$display_field,$display_default);
		}

		/*
			Function: updateCalloutGroup
				Updates a callout group's name and callout list.

			Parameters:
				id - The id of the callout group to update.
				name - The name.
				callouts - An array of callout IDs to assign to the group.
		*/

		function updateCalloutGroup($id,$name,$callouts) {
			$group = new BigTree\CalloutGroup($id);
			$group->update($name,$callouts);
		}

		/*
			Function: updateChildPagePaths
				Updates the paths for pages who are descendants of a given page to reflect the page's new route.
				Also sets route history if the page has changed paths.

			Parameters:
				page - The page id.
		*/

		static function updateChildPagePaths($page) {
			$child_pages = static::$DB->fetchAll("SELECT id, path FROM bigtree_pages WHERE parent = ?", $page);
			foreach ($child_pages as $child) {
				$new_path = static::getFullNavigationPath($child["id"]);

				if ($child["path"] != $new_path) {
					// Remove any overlaps
					static::$DB->query("DELETE FROM bigtree_route_history WHERE old_route = ? OR old_route = ?", $new_path, $child["path"]);
					
					// Add a new redirect
					static::$DB->insert("bigtree_route_history",array(
						"old_route" => $child["path"],
						"new_route" => $new_path
					));
					
					// Update the primary path
					static::$DB->update("bigtree_pages",$child["id"],array("path" => $new_path));

					// Update all this page's children as well
					static::updateChildPagePaths($child["id"]);
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
			$options = is_array($options) ? $options : json_decode($options,true);
			foreach ($options as &$option) {
				$option = BigTreeCMS::replaceHardRoots($option);
			}

			static::$DB->update("bigtree_feeds",$id,array(
				"name" => BigTree::safeEncode($name),
				"description" => BigTree::safeEncode($description),
				"table" => $table,
				"type" => $type,
				"fields" => $fields,
				"options" => $options
			));

			$this->track("bigtree_feeds",$id,"updated");
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

		function updateFieldType($id,$name,$use_cases,$self_draw) {
			$field_type = new BigTree\FieldType($id);
			$field_type->update($name,$use_cases,$self_draw);
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
				developer_only - Sets a module to be accessible/visible to only developers.
		*/

		function updateModule($id,$name,$group,$class,$permissions,$icon,$developer_only = false) {
			$module = new BigTree\Module($id);
			$module->update($name,$group,$class,$permissions,$icon,$developer_only);
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
				interface - Related module interface.
				level - The required access level.
				position - The position in navigation.
		*/

		function updateModuleAction($id,$name,$route,$in_nav,$icon,$interface,$level,$position) {
			$action = new BigTree\ModuleAction($id);
			$action->update($name,$route,$in_nav,$icon,$interface,$level,$position);
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

		function updateModuleEmbedForm($id,$title,$table,$fields,$hooks = array(),$default_position = "",$default_pending = "",$css = "",$redirect_url = "",$thank_you_message = "") {
			$clean_fields = array();
			foreach ($fields as $key => $field) {
				$field["options"] = json_decode($field["options"],true);
				$field["column"] = $key;
				$clean_fields[] = $field;
			}

			// Get existing form to preserve its hash
			$interface_settings = json_decode(static::$DB->fetchSingle("SELECT settings FROM bigtree_module_interfaces WHERE id = ?", $id),true);

			$this->updateModuleInterface($id,$title,$table,array(
				"fields" => $clean_fields,
				"default_position" => $default_position,
				"default_pending" => $default_pending ? "on" : "",
				"css" => BigTree::safeEncode($this->makeIPL($css)),
				"hash" => $interface_settings["hash"],
				"redirect_url" => $redirect_url ? BigTree::safeEncode($this->makeIPL($redirect_url)) : "",
				"thank_you_message" => $thank_you_message,
				"hooks" => is_string($hooks) ? json_decode($hooks,true) : $hooks
			));
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
		*/

		function updateModuleForm($id,$title,$table,$fields,$hooks = array(),$default_position = "",$return_view = false,$return_url = "",$tagging = "") {
			$form = new BigTree\ModuleForm($id);
			$form->update($title,$table,$fields,$hooks,$default_position,$return_view,$return_url,$tagging);
		}

		/*
			Function: updateModuleGroup
				Updates a module group's name.

			Parameters:
				id - The id of the module group to update.
				name - The name of the module group.
		*/

		function updateModuleGroup($id,$name) {
			$group = new BigTree\ModuleGroup($id);
			$group->update($name);
		}

		/*
			Function: updateModuleInterface
				Updates a module interface.

			Parameters:
				id - The ID of the interface to update
				title - The interface title (for admin purposes)
				table - The related table
				settings - An array of settings
		*/

		function updateModuleInterface($id,$title,$table,$settings = array()) {
			static::$DB->update("bigtree_module_interfaces",$id,array(
				"title" => BigTree::safeEncode($title),
				"table" => $table,
				"settings" => $settings
			));

			$this->track("bigtree_module_interfaces",$id,"updated");
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

		function updateModuleReport($id,$title,$table,$type,$filters,$fields = "",$parser = "",$view = "") {
			$this->updateModuleInterface($id,$title,$table,array(
				"type" => $type,
				"filters" => $filters,
				"fields" => $fields,
				"parser" => $parser,
				"view" => $view ? $view : null
			));

			// Update related module action names
			static::$DB->update("bigtree_module_actions",array("interface" => $id),array("name" => $title));
		}

		/*
			Function: updateModuleView
				Updates a module view and the associated module action's title.

			Parameters:
				id - The view id.
				title - View title.
				description - Description.
				table - Data table.
				type - View type.
				options - View options array.
				fields - Field array.
				actions - Actions array.
				related_form - Form ID to handle edits.
				preview_url - Optional preview URL.
		*/

		function updateModuleView($id,$title,$description,$table,$type,$options,$fields,$actions,$related_form,$preview_url = "") {
			$view = new BigTree\ModuleView($id);
			$view->update($title,$description,$table,$type,$options,$fields,$actions,$related_form,$preview_url);
		}

		/*
			Function: updateModuleViewColumnNumericStatus
				Updates a module view's columns to designate whether they are numeric or not based on parsers, column type, and related forms.

			Parameters:
				id - The view id to perform column analysis on.
		*/

		function updateModuleViewColumnNumericStatus($id) {
			$view = new BigTree\ModuleView($id);
			$view->refreshNumericColumns();
		}

		/*
			Function: updateModuleViewFields
				Updates the fields for a module view.

			Parameters:
				id - The view id.
				fields - A fields array.
		*/

		function updateModuleViewFields($id,$fields) {
			$view = new ModuleView($id);
			$view->Fields = $fields;

			// Automatically saves
			$view->refreshNumericColumns();
		}

		/*
			Function: updatePage
				Updates a page.
				Does not check permissions.

			Parameters:
				page - The page id to update.
				data - The page data to update with.
		*/

		function updatePage($page,$data) {

			// Save the existing copy as a draft, remove drafts for this page that are one month old or older.
			$current = static::$DB->fetch("SELECT * FROM bigtree_pages WHERE id = ?", $page);
			
			// Save a copy
			static::$DB->insert("bigtree_page_revisions",array(
				"page" => $page,
				"title" => $current["title"],
				"meta_keywords" => $current["meta_keywords"],
				"meta_description" => $current["meta_description"],
				"template" => $current["template"],
				"external" => $current["external"],
				"new_window" => $current["new_window"],
				"resources" => $current["resources"],
				"author" => $current["last_edited_by"],
				"updated_at" => $current["updated_at"]
			));

			// Count the page revisions, if we have more than 10, delete any that are more than a month old
			$revision_count = static::$DB->fetchSingle("SELECT COUNT(*) FROM bigtree_page_revisions WHERE page = ? AND saved = ''", $page);
			if ($revision_count > 10) {
				static::$DB->query("DELETE FROM bigtree_page_revisions WHERE page = ? AND updated_at < '".date("Y-m-d",strtotime("-1 month"))."' AND saved = '' ORDER BY updated_at ASC LIMIT ".($revision_count - 10), $page);
			}

			// Figure out if we currently have a template that the user isn't allowed to use. If they do, we're not letting them change it.
			if ($current["template"]) {
				$template_level = static::$DB->fetchSingle("SELECT level FROM bigtree_templates WHERE id = ?", $current["template"]);
				if ($template_level > $this->Level) {
					$data["template"] = $current["template"];
				}
			}

			// Remove this page from the cache
			static::unCache($page);

			// Set local variables in a clean fashion that prevents _SESSION exploitation.  Also, don't let them somehow overwrite $page and $current.
			$trunk = $in_nav = $external = $route = $publish_at = $expire_at = $nav_title = $title = $template = $new_window = $meta_keywords = $meta_description = $seo_invisible = "";
			$parent = $max_age = 0;
			$resources = array();

			foreach ($data as $key => $val) {
				if (substr($key,0,1) != "_" && $key != "current" && $key != "page") {
					$kkey = $val;
				}
			}

			// Set the trunk flag back to the current value if the user isn't a developer
			$trunk = ($this->Level < 2) ? $current["trunk"] : $trunk;

			// If this is top level nav and the user isn't a developer, use what the current state is.
			$in_nav = (!$current["parent"] && $this->Level < 2) ? $current["in_nav"] : $in_nav;

			// Make an ipl:// or {wwwroot}'d version of the URL
			$external = $external ? static::makeIPL($external) : "";

			// If somehow we didn't provide a parent page (like, say, the user didn't have the right to change it) then pull the one from before.  Actually, this might be exploitable… look into it later.
			if (!isset($data["parent"])) {
				$parent = $current["parent"];
			}

			if ($page == 0) {
				// Home page doesn't get a route - fixes sitemap bug
				$route = "";
			} else {
				// Create a route if we don't have one, otherwise, make sure the one they provided doesn't suck.
				if (!$route) {
					$route = BigTreeCMS::urlify($data["nav_title"]);
				} else {
					$route = BigTreeCMS::urlify($route);
				}

				// Get a unique route
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

				// Make sure route isn't longer than 250
				$route = substr($route,0,250);

				// Existing pages.
				while (static::$DB->fetchSingle("SELECT COUNT(*) FROM bigtree_pages 
												 WHERE `route` = ? AND parent = ? AND id != ?", $route, $parent, $page)) {
					$route = $original_route."-".$x;
					$x++;
				}				
			}

			// We have no idea how this affects the nav, just wipe it all.
			if ($current["nav_title"] != $nav_title || $current["route"] != $route || $current["in_nav"] != $in_nav || $current["parent"] != $parent) {
				static::clearCache();
			}

			// Make sure we set the publish date to NULL if it wasn't provided or we'll have a page that got published at 0000-00-00
			if ($publish_at && $publish_at != "NULL") {
				$publish_at = date("Y-m-d",strtotime($publish_at));
			} else {
				$publish_at = null;
			}

			// If we set an expiration date, make it the proper MySQL format.
			if ($expire_at && $expire_at != "NULL") {
				$expire_at = date("Y-m-d",strtotime($expire_at));
			} else {
				$expire_at = null;
			}

			// Set the full path, saves DB access time on the front end.
			if ($parent > 0) {
				$path = static::getFullNavigationPath($parent)."/".$route;
			} else {
				$path = $route;
			}

			// Update the database
			static::$DB->update("bigtree_pages",$page,array(
				"trunk" => $trunk,
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
				"meta_keywords" => BigTree::safeEncode($meta_keywords),
				"meta_description" => BigTree::safeEncode($meta_description),
				"seo_invisible" => $seo_invisible ? "on" : "",
				"last_edited_by" => $this->ID,
				"publish_at" => $publish_at,
				"expire_at" => $expire_at,
				"max_age" => $max_age
			));

			// Remove any pending drafts
			static::$DB->delete("bigtree_pending_changes",array("table" => "bigtree_pages","item_id" => $page));

			// Remove old paths from the redirect list
			static::$DB->query("DELETE FROM bigtree_route_history WHERE old_route = ? OR old_route = ?", $path, $current["path"]);

			// Create an automatic redirect from the old path to the new one.
			if ($current["path"] != $path) {
				static::$DB->insert("bigtree_route_history",array(
					"old_route" => $current["path"],
					"new_route" => $path
				));

				// Update all child page routes, ping those engines, clean those caches
				static::updateChildPagePaths($page);
				static::pingSearchEngines();
				static::clearCache();
			}

			// Handle tags
			static::$DB->delete("bigtree_tags_rel",array("table" => "bigtree_pages","entry" => $page));
			if (is_array($data["_tags"])) {
				foreach ($data["_tags"] as $tag) {
					static::$DB->insert("bigtree_tags_rel",array(
						"table" => "bigtree_pages",
						"entry" => $page,
						"tag" => $tag
					));
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
			if ($this->Level < 1) {
				$this->stop("You are not allowed to move pages.");
			}

			// Get the existing path so we can create a route history
			$current = static::$DB->fetch("SELECT in_nav, path FROM bigtree_pages WHERE id = ?", $page);

			// If the current user isn't a developer and is moving the page to top level, set it to not be visible
			$in_nav = $current["in_nav"] ? "on" : "";
			if ($this->Level < 2 && $parent == 0) {
				$in_nav = "";
			}

			// Update the page parent first so that the navigation path call returns the new path
			static::$DB->update("bigtree_pages",$page,array("in_nav" => $in_nav, "parent" => $parent));
			$path = $this->getFullNavigationPath($page);

			// Set the route history
			static::$DB->query("DELETE FROM bigtree_route_history WHERE old_route = ? OR old_route = ?", $path, $current["path"]);
			static::$DB->insert("bigtree_route_history",array(
				"old_route" => $current["path"],
				"new_route" => $path
			));

			// Update the page with its new path.
			static::$DB->update("bigtree_pages",$page,array("path" => $path));

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

		function updatePageRevision($id,$description) {
			// Get the version, check if the user has access to the page the version refers to.
			$revision = $this->getPageRevision($id);
			$access_level = $this->getPageAccessLevel($revision["page"]);
			if ($access_level != "p") {
				$this->stop("You must be a publisher to manage revisions.");
			}

			// Save the version's description and saved status
			static::$DB->update("bigtree_page_revisions",$id,array(
				"saved" => "on",
				"saved_description" => BigTree::safeEncode($description)
			));
			$this->track("bigtree_page_revisions",$id,"updated");
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
			static::$DB->update("bigtree_pending_changes",$id,array(
				"changes" => $changes,
				"mtm_changes" => $mtm_changes,
				"tags_changes" => $tags_changes,
				"user" => $this->ID
			));
			$this->track("bigtree_pending_changes",$id,"updated");
		}

		/*
			Function: updateProfile
				Updates the logged-in user's name, company, digest setting, and (optionally) password.

			Parameters:
				name - Name
				company - Company
				daily_digest - Whether to receive the daily digest (truthy value) or not (falsey value)
				password - Password (leave empty or false to not update)
		*/

		function updateProfile($name,$company = "",$daily_digest = "",$password = false) {
			global $bigtree;

			// Allow for pre-4.3 style parameters
			if (is_array($name)) {
				$data = $name;
				$name = "";

				foreach ($data as $key => $val) {
					if (substr($key,0,1) != "_") {
						$$key = $val;
					}
				}
			}

			$update_values = array(
				"name" => BigTree::safeEncode($name),
				"company" => BigTree::safeEncode($company),
				"daily_digest" => $daily_digest ? "on" : "",
			);

			if ($password !== "" && $password !== false) {
				$phpass = new PasswordHash($bigtree["config"]["password_depth"], TRUE);
				$update_values["password"] = $phpass->HashPassword($password);
			}

			static::$DB->update("bigtree_users",$this->ID,$update_values);
		}

		/*
			Function: updateResource
				Updates a resource.

			Parameters:
				id - The id of the resource.
				attributes - A key/value array of fields to update.
		*/

		function updateResource($id,$attributes) {
			$resource = new BigTree\Resource($id);
			foreach ($attributes as $key => $val) {
				// Camel case attributes
				$key = str_replace(" ","",ucwords(str_replace("_"," ",$key)));
				$resource->$key = $val;
			}
			$resource->save();
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

		function updateSetting($old_id,$data) {
			foreach ($data as $key => $val) {
				if (substr($key,0,1) != "_") {
					$$key = $val;
				}
			}

			$setting = new BigTree\Setting($old_id,false);
			return $setting->update($id,$type,$options,$name,$description,$locked,$encrypted,$system);
		}

		/*
			Function: updateSettingValue
				Updates the value of a setting.

			Parameters:
				id - The id of the setting to update.
				value - A value to set (can be a string or array).
		*/

		static function updateSettingValue($id,$value) {
			$setting = new BigTree\Setting($id);
			$setting->Value = $value;
			$setting->save();
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
		*/

		function updateTemplate($id,$name,$level,$module,$resources) {
			$template = new BigTree\Template($id);
			$template->update($name,$level,$module,$resources);
		}

		/*
			Function: updateUser
				Updates a user.

			Parameters:
				id - The user's ID
				email - Email Address
				password - Password
				name - Name
				company - Company
				level - User Level (0 for regular, 1 for admin, 2 for developer)
				permission - Array of permissions data
				alerts - Array of alerts data
				daily_digest - Whether the user wishes to receive the daily digest email

			Returns:
				True if successful.  False if the logged in user doesn't have permission to change the user or there was an email collision.
		*/

		function updateUser($id,$email,$password = "",$name = "",$company = "",$level = 0,$permissions = array(),$alerts = array(),$daily_digest = "") {
			global $bigtree;

			// Allow for pre-4.3 syntax
			if (is_array($email)) {
				$data = $email;
				foreach ($data as $key => $val) {
					if (substr($key,0,1) != "_") {
						$$key = $val;
					}
				}
			}

			$user = new BigTree\User($id);
			return $user->update($id,$email,$password,$name,$company,$level,$permissions,$alerts,$daily_digest);
		}

		/*
			Function: updateUserPassword
				Updates a user's password.

			Parameters:
				id - The user's id.
				password - The new password.
		*/

		static function updateUserPassword($id,$password) {
			$user = new BigTree\User($id);
			$user->Password = $password;
			$user->save();
		}

		/*
			Function: validatePassword
				Validates a password against the security policy.

			Parameters:
				password - Password to validate.

			Returns:
				true if it passes all password criteria.
		*/

		static function validatePassword($password) {
			return BigTree\User::validatePassword($password);
		}

		/*
			Function: versionToDecimal
				Returns a decimal number of a BigTree version for numeric comparisons.

			Parameters:
				version - BigTree version number (i.e. 4.2.0)

			Returns:
				A number
		*/

		static function versionToDecimal($version) {
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
