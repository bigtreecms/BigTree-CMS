<?php
	/*
		Class: BigTreeAdmin
			The main class used by the admin for manipulating and retrieving data.
	*/

	use BigTree\Auth;
	use BigTree\SQL;

	class BigTreeAdminBase {

		// Static variables
		public static $ActionClasses = array("add", "delete", "list", "edit", "refresh", "gear", "truck", "token", "export", "redirect", "help", "error", "ignored", "world", "server", "clock", "network", "car", "key", "folder", "calendar", "search", "setup", "page", "computer", "picture", "news", "events", "blog", "form", "category", "map", "done", "warning", "user", "question", "sports", "credit_card", "cart", "cash_register", "lock_key", "bar_graph", "comments", "email", "weather", "pin", "planet", "mug", "atom", "shovel", "cone", "lifesaver", "target", "ribbon", "dice", "ticket", "pallet", "lightning", "camera", "video", "twitter", "facebook", "trail", "crop", "cloud", "phone", "music", "house", "featured", "heart", "link", "flag", "bug", "games", "coffee", "airplane", "bank", "gift", "badge", "award", "radio");
		public static $DB;
		public static $IconClasses = array("gear", "truck", "token", "export", "redirect", "help", "error", "ignored", "world", "server", "clock", "network", "car", "key", "folder", "calendar", "search", "setup", "page", "computer", "picture", "news", "events", "blog", "form", "category", "map", "user", "question", "sports", "credit_card", "cart", "cash_register", "lock_key", "bar_graph", "comments", "email", "weather", "pin", "planet", "mug", "atom", "shovel", "cone", "lifesaver", "target", "ribbon", "dice", "ticket", "pallet", "camera", "video", "twitter", "facebook");
		public static $PerPage = 15;
		public static $ReservedColumns = array(
			"id",
			"position",
			"archived",
			"approved"
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

		public $Auth;
		public $ID;
		public $Level;
		public $Name;
		public $Permissions;
		public $User;
		
		/*
			Constructor:
				Initializes the user's permissions.
		*/

		function __construct() {
			// Handle authentication
			$this->Auth = new Auth;

			// Admin environment
			$this->ID = Auth::$ID;
			$this->User = Auth::$Email;
			$this->Level = Auth::$Level;
			$this->Name = Auth::$Name;
			$this->Permissions = Auth::$Permissions;

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

			// Check for Per Page value
			$per_page = intval(BigTree\Setting::value("bigtree-internal-per-page"));
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

		static function allocateResources($module, $entry) {
			BigTree\Resource::allocate($module, $entry);
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
			$page = new BigTree\Page($page, false);

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
			$page = new BigTree\Page($page, false);
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
			return SQL::backup($file);
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

		function canAccessGroup($module, $group) {
			$module = new BigTree\Module($module);

			return BigTree\Auth::user()->getGroupAccessLevel($module, $group);
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

		function canModifyChildren($page) {
			$page = new BigTree\Page($page, false);

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

		static function changePassword($hash, $password) {
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

			return true;
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

		function checkAccess($module, $action = false) {
			if ($action) {
				$object = new BigTree\ModuleAction($action);
			} else {
				$object = new BigTree\Module($module);
			}

			return BigTree\Auth::user()->canAccess($object);
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

		static function checkHTML($relative_path, $html, $external = false) {
			return BigTree\Link::integrity($relative_path, $html, $external);
		}

		/*
			Function: clearCache
				Removes all files in the cache directory.
		*/

		static function clearCache() {
			BigTree\Router::clearCache();
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
				site_key - The site key for a multi-site environment (defaults to null)
		*/

		function create301($from, $to, $site_key = null) {
			BigTree\Redirect::create($from, $to, $site_key);
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

		function createCallout($id, $name, $description, $level, $resources, $display_field, $display_default) {
			$callout = BigTree\Callout::create($id, $name, $description, $level, $resources, $display_field, $display_default);

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

		function createCalloutGroup($name, $callouts) {
			$group = BigTree\CalloutGroup::create($name, $callouts);

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
				settings - The feed type settings.
				fields - The fields.

			Returns:
				The route to the new feed.
		*/

		function createFeed($name, $description, $table, $type, $settings, $fields) {
			if (is_string($settings)) {
				$settings = array_filter((array) json_decode($settings, true));
			}
			
			$feed = BigTree\Feed::create($name, $description, $table, $type, $settings, $fields);

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

		function createFieldType($id, $name, $use_cases, $self_draw) {
			$field_type = BigTree\FieldType::create($id, $name, $use_cases, $self_draw ? true : false);

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

		function createMessage($subject, $message, $recipients, $in_response_to = 0) {
			BigTree\Message::create($this->ID, $subject, $message, $recipients, $in_response_to);
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

		function createModule($name, $group, $class, $table, $permissions, $icon, $route = false, $developer_only = false) {
			$module = BigTree\Module::create($name, $group, $class, $table, $permissions, $icon, $route, $developer_only);

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

		function createModuleAction($module, $name, $route, $in_nav, $icon, $interface, $level = 0, $position = 0) {
			$action = BigTree\ModuleAction::create($module, $name, $route, $in_nav ? true : false, $icon, $interface, $level, $position);

			return $action->Route;
		}

		/*
			Function: createModuleEmbedForm
				This function is disabled in BigTree 5.0+
		*/

		function createModuleEmbedForm($module, $title, $table, $fields, $hooks = array(), $default_position = "", $default_pending = "", $css = "", $redirect_url = "", $thank_you_message = "") {
			trigger_error("BigTree 5.0 does not support embeddable forms.", E_USER_ERROR);
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

		function createModuleForm($module, $title, $table, $fields, $hooks = array(), $default_position = "", $return_view = false, $return_url = "", $tagging = "") {
			$form = BigTree\ModuleForm::create($module, $title, $table, $fields, $hooks, $default_position, $return_view ?: null, $return_url, $tagging ? true : false);

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

		function createModuleReport($module, $title, $table, $type, $filters, $fields = "", $parser = "", $view = "") {
			$interface = BigTree\ModuleInterface::create("report", $module, $title, $table, array(
				"type" => $type,
				"filters" => $filters,
				"fields" => $fields,
				"parser" => $parser,
				"view" => $view ?: null
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
				settings - View settings array.
				fields - Field array.
				actions - Actions array.
				related_form - Form ID to handle edits.
				preview_url - Optional preview URL.

			Returns:
				The id for view.
		*/

		function createModuleView($module, $title, $description, $table, $type, $settings, $fields, $actions, $related_form = "", $preview_url = "") {
			$view = BigTree\ModuleView::create($module, $title, $description, $table, $type, $settings, $fields, $actions, $related_form ?: null, $preview_url);

			return $view->ID;
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

		function createPage($data, $publishing_change = false) {
			// Defaults
			$parent = 0;
			$title = $nav_title = $meta_description = $external = $template = $in_nav = $route = "";
			$seo_invisible = $publish_at = $expire_at = $trunk = $new_window = $max_age = false;
			$resources = array();

			// Loop through the posted data, make sure no session hijacking is done.
			foreach ($data as $key => $val) {
				if (substr($key, 0, 1) != "_") {
					$$key = $val;
				}
			}

			// Reset trunk if user isn't developer
			if ($this->Level < 2) {
				$trunk = "";
			}

			$page = BigTree\Page::create($trunk, $parent, $in_nav ? true : false, $nav_title, $title, $route, $meta_description, $seo_invisible ? true : false, $template, $external, $new_window ? true : false, $resources, $publish_at, $expire_at, $max_age ? intval($max_age) : null, $data["_tags"], $publishing_change ?: null);

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

		function createPendingChange($table, $item_id, $changes, $mtm_changes = array(), $tags_changes = array(), $module = "") {
			$change = BigTree\PendingChange::create($table, $item_id, $changes, $mtm_changes, $tags_changes, $module);

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
				$data["trunk"] = SQL::escape($data["trunk"]);
			}

			$change = BigTree\PendingChange::createPage($data["trunk"], $data["parent"], $data["in_nav"] ? true : false, $data["nav_title"], $data["title"], $data["route"], $data["meta_description"], $data["seo_invisible"] ? true : false, $data["template"], $data["external"], $data["new_window"] ? true : false, $data["resources"], $data["publish_at"], $data["expire_at"], $data["max_age"] ? intval($data["max_age"]) : null, $data["_tags"]);

			return $change->ID;
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
			$resource = BigTree\Resource::create($folder, $file, $name, $type, $crops, $thumbs, $video_data, $metadata);

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

		function createResourceFolder($parent, $name) {
			// Backwards compatibility as ResourceFolder doesn't check permissions
			$permission = $this->getResourceFolderPermission($parent);
			if ($permission != "p") {
				return false;
			}

			$folder = BigTree\ResourceFolder::create($parent ?: null, $name);

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
			$id = $name = $extension = $description = $type = $settings = $locked = $encrypted = $system = "";

			// Loop through and create our expected parameters.
			foreach ($data as $key => $val) {
				if (substr($key, 0, 1) != "_") {
					$$key = $val;
				}
			}

			$setting = BigTree\Setting::create($id, $name, $description, $type, $settings, $extension, $system ? true : false, $encrypted ? true : false, $locked ? true : false);

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

		function createTemplate($id, $name, $routed, $level, $module, $resources) {
			$template = BigTree\Template::create($id, $name, $routed, $level, $module, $resources);

			return $template ? true : false;
		}

		/*
			Function: createUser
				Creates a user (and checks access levels to ensure permissions are met).
				Supports pre-4.3 syntax by passing an array as the first parameter.

			Parameters:
				data - An array of user data. ("email", "password", "name", "company", "level", "permissions", "alerts", "timezone")

			Returns:
				id of the newly created user or false if a user already exists with the provided email.
		*/

		function createUser($data) {
			// Set defaults
			$email = $password = $name = $company = $level = $daily_digest = $timezone = "";
			$permissions = $alerts = array();

			// Loop through and create our expected parameters.
			foreach ($data as $key => $val) {
				if (substr($key, 0, 1) != "_") {
					$$key = $val;
				}
			}

			$user = BigTree\User::create($email, $password, $name, $company, $level, $permissions, $alerts, $daily_digest ? true : false, $timezone);

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
				This function is disabled in BigTree 5.0+
		*/

		function deleteModuleEmbedForm($id) {
			trigger_error("BigTree 5.0 does not support embeddable forms.", E_USER_ERROR);
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
				$page = new BigTree\Page($page, false);
				if ($page->UserAccessLevel == "p" && $page->UserCanModifyChildren) {
					$page->delete();

					return true;
				}
			} else {
				$pending_change = new BigTree\PendingChange(substr($page, 1));
				$page = new BigTree\Page($page, false);
				if ($page->UserAccessLevel == "p" && $page->UserCanModifyChildren) {
					$pending_change->delete();

					return true;
				}
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

		function deletePageChildren($id) {
			$page = new BigTree\Page($id, false);
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
			$page = new BigTree\Page($id, false);

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

			return true;
		}

		/*
			Function: deletePendingChange
				Deletes a pending change.

			Parameters:
				id - The id of the change.
		*/

		function deletePendingChange($id) {
			SQL::delete("bigtree_pending_changes", $id);
			$this->track("bigtree_pending_changes", $id, "deleted");
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
			$api = new BigTree\GoogleAnalytics\API;
			$api->disconnect();
			static::growl("Analytics", "Disconnected");
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

		static function doesModuleActionExist($module, $route) {
			return BigTree\ModuleAction::existsForRoute($module, $route);
		}

		/*
			Function: drawArrayLevel
				An internal function used for drawing callout and matrix resource data.
		*/

		static function drawArrayLevel($keys, $level, $field = false) {
			if ($field === false) {
				global $field;
			}

			$field = new BigTree\Field($field);
			$field->drawArrayLevel($keys, $level);
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
			Function: emailDailyDigest
				Sends out a daily digest email to all who have subscribed.
		*/

		function emailDailyDigest() {
			BigTree\DailyDigest::send();
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
			global $bigtree;

			$user = BigTree\User::getByEmail($email);
			if (!$user) {
				return false;
			}

			$user->initPasswordReset();

			$login_root = ($bigtree["config"]["force_secure_login"] ? str_replace("http://", "https://", ADMIN_ROOT) : ADMIN_ROOT)."login/";
			BigTree\Router::redirect($login_root."forgot-success/");

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

		static function get404Total($type, $site_key = null) {
			if (!is_null($site_key)) {
				if ($type == "404") {
					return SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_404s WHERE ignored = '' AND redirect_url = '' AND site_key = ?", $site_key);
				} elseif ($type == "301") {
					return SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_404s WHERE ignored = '' AND redirect_url != '' AND site_key = ?", $site_key);
				} elseif ($type == "ignored") {
					return SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_404s WHERE ignored = 'on' AND site_key = ?", $site_key);
				}
			} else {
				if ($type == "404") {
					return SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_404s WHERE ignored = '' AND redirect_url = ''");
				} elseif ($type == "301") {
					return SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_404s WHERE ignored = '' AND redirect_url != ''");
				} elseif ($type == "ignored") {
					return SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_404s WHERE ignored = 'on'");
				}
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
			$groups = BigTree\Auth::user()->getAccessibleModuleGroups($module);

			if (is_null($groups)) {
				return true;
			} else {
				return $groups;
			}
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

		function getAccessLevel($module, $item = array(), $table = "", $user = false) {
			$module = new BigTree\Module($module);
			
			return BigTree\Auth::user($user)->getAccessLevel($module, $item, $table);
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

		static function getActionClass($action, $item) {
			return BigTree\ModuleView::generateActionClass($action, $item);
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
			$page = new BigTree\Page($parent, false);
			$children = $page->getArchivedChildren(true);

			// We expect "title" to be the navigation title
			foreach ($children as $key => $child) {
				$children[$key]["title"] = $child["nav_title"];
			}

			return $children;
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
			$list = BigTree\Template::allByRouted("", $sort, true);
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
		function getCachedAccessLevel($module, $item = array(), $table = "") {
			$module = new BigTree\Module($module);

			return BigTree\Auth::user()->getCachedAccessLevel($module, $item, $table);
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
			$array = $callout->Array;
			$array["resources"] = $array["fields"];

			return $array;
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
			return BigTree\CalloutGroup::all("name ASC", true);
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
			return BigTree\Callout::all($sort, true);
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
			return BigTree\Callout::allAllowed($sort, true);
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

		function getCalloutsInGroups($groups, $auth = true) {
			return BigTree\Callout::allInGroups($groups, $auth, true);
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
			return BigTree\Page::getAlertsForUser($user);
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
			return BigTree\Extension::allByType("extension", $sort, true);
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
			return BigTree\Feed::all($sort, true);
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
			return BigTree\FieldType::all($sort, true);
		}

		/*
			Function: getFullNavigationPath
				Calculates the full navigation path for a given page ID.

			Parameters:
				id - The page ID to calculate the navigation path for.

			Returns:
				The navigation path (normally found in the "path" column in bigtree_pages).
		*/

		static function getFullNavigationPath($id) {
			$page = new BigTree\Page($id, false);

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
			$page = new BigTree\Page($parent, false);
			$children = $page->getHiddenChildren(true);

			// We expect "title" to be the navigation title
			foreach ($children as $key => $child) {
				$children[$key]["title"] = $child["nav_title"];
			}

			return $children;
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

			if ($message->Sender != $this->ID && !in_array($this->ID, $message->Recipients)) {
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
			return BigTree\Message::allByUser($user, true);
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

		static function getModuleActionByRoute($module, $route) {
			$response = BigTree\ModuleAction::lookup($module, $route);

			return $response ? array("action" => $response["action"]->Array, "commands" => $response["commands"]) : false;
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
			return BigTree\ModuleAction::allByModule($module, "position DESC, id ASC", true);
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
			$module = BigTree\Module::getByRoute($route);
			if (!$module) {
				return false;
			}

			$module = $module->Array;
			$module["gbp"] = $module["group_based_permissions"];

			return $module;
		}

		/*
			Function: getModuleEmbedForms
				This function is disabled in BigTree 5.0+
		*/

		static function getModuleEmbedForms($sort = "title ASC", $module = false) {
			trigger_error("BigTree 5.0 does not support embeddable forms.", E_USER_ERROR);
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

		static function getModuleForms($sort = "title ASC", $module = false) {
			$interfaces = BigTree\ModuleInterface::allByModuleAndType($module ?: null, "form", $sort, true);

			// Return previous table format
			$forms = array();
			foreach ($interfaces as $interface) {
				$settings = json_decode($interface["settings"], true);
				$forms[] = array(
					"id" => $interface["id"],
					"module" => $interface["module"],
					"title" => $interface["title"],
					"table" => $interface["table"],
					"fields" => BigTree\Utils::arrayValue($settings["fields"]),
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
			$group = BigTree\ModuleGroup::getByRoute($route);

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
			$raw_groups = BigTree\ModuleGroup::all($sort, true);
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
			$module = new BigTree\Module($module);
			$nav = $module->Navigation;

			foreach ($nav as &$item) {
				$item = $item->Array;
			}

			return $nav;
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

		static function getModuleReports($sort = "title ASC", $module = false) {
			$interfaces = BigTree\ModuleInterface::allByModuleAndType($module ?: null, "report", $sort, true);

			// Support the old table format
			$reports = array();
			foreach ($interfaces as $interface) {
				$settings = json_decode($interface["settings"], true);
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

		function getModules($sort = "id ASC", $auth = true) {
			if (!$auth) {
				return BigTree\Module::all($sort, true);
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

		function getModulesByGroup($group, $sort = "position DESC, id ASC", $auth = true) {
			$group = is_array($group) ? $group["id"] : $group;

			return BigTree\Module::allByGroup($group, $sort, true, $auth);
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

		static function getModuleViews($sort = "title ASC", $module = false) {
			$interfaces = BigTree\ModuleInterface::allByModuleAndType($module ?: null, "view", $sort, true);

			// Support the old table format
			$views = array();
			foreach ($interfaces as $interface) {
				$settings = json_decode($interface["settings"], true);
				$views[] = array(
					"id" => $interface["id"],
					"module" => $interface["module"],
					"title" => $interface["title"],
					"table" => $interface["table"],
					"type" => $settings["type"],
					"fields" => $settings["fields"],
					"settings" => $settings["settings"],
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

		static function getNaturalNavigationByParent($parent) {
			$page = new BigTree\Page($parent, false);
			$children = $page->getVisibleChildren(true);

			// We expect "title" to be the navigation title
			foreach ($children as $key => $child) {
				$children[$key]["title"] = $child["nav_title"];
			}

			return $children;
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
			$extension = new BigTree\Extension($id);

			return $extension->Array;
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
			return BigTree\Extension::allByType("package", $sort, true);
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
			$page = new BigTree\Page($page);

			return BigTree\Auth::user()->getAccessLevel($page);
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

		static function getPageAccessLevelByUser($page, $user) {
			$page = new BigTree\Page($page, false);

			return BigTree\Auth::user($user)->getAccessLevel($page);
		}

		/*
			Function: getPageAdminLinks
				Gets a list of pages that link back to the admin.

			Returns:
				An array of pages that link to the admin.
		*/

		static function getPageAdminLinks() {
			return BigTree\Page::auditAdminLinks(true);
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
			$page = new BigTree\Page($page, false);
			$change = $page->PendingChange;

			return $change->Array;
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

		static function getPageChildren($page, $sort = "nav_title ASC") {
			$page = new BigTree\Page($page, false);

			return $page->getChildren($sort, true);
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
			return BigTree\Page::getLineage($page);
		}

		/*
			Function: getPageIds
				Returns all the IDs in bigtree_pages for pages that aren't archived.

			Returns:
				An array of page ids.
		*/

		static function getPageIds() {
			return BigTree\Page::allIDs();
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

		static function getPageIDForPath($path, $previewing = false) {
			return BigTree\Router::routeToPage($path, $previewing);
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
			$page = new BigTree\PageRevision($id);
			$array = $page->Array;

			// Previously the resources were still JSON, so replicate that
			$array["resources"] = json_encode($array["resources"]);

			return $array;
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
			return BigTree\PageRevision::listForPage($page, "updated_at DESC");
		}

		/*
			Function: getPages
				Returns all pages from the database.

			Returns:
				Array of unmodified entries from bigtree_pages.
		*/

		static function getPages() {
			return BigTree\Page::all("id ASC", true);
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

		static function getPageSEORating($page, $content) {
			$page = new BigTree\Page($page);
			$page->Resources = $content;

			return $page->SEORating;
		}

		/*
			Function: getPendingChange
				Returns a pending change from the bigtree_pending_changes table.

			Parameters:
				id - The id of the change.

			Returns:
				A entry from the table with the "changes" column decoded.
		*/

		static function getPendingChange($id) {
			$change = new BigTree\PendingChange($id);

			return $change->Array;
		}

		// For backwards compatibility
		static function getChange($id) {
			return static::getPendingChange($id);
		}

		/*
			Function: getPublishableChanges
				Returns a list of changes that the given user has access to publish.

			Parameters:
				user - A user entry or user ID

			Returns:
				An array of changes sorted by most recent.
		*/

		static function getPublishableChanges($user) {
			$user = new BigTree\User($user);
			$changes = BigTree\PendingChange::allPublishableByUser($user);

			// Add things this call used to expect
			foreach ($changes as $key => $change) {
				$change = $change->Array;
				$change["mod"] = $change["module"]->Array;
				$change["module"] = $change["module"]->ID;
				$change["user"] = $change["user"]->Array;

				$changes[$key] = $change;
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

		function getPendingChanges($user = false) {
			if (is_array($user)) {
				$user = $user["id"];
			} elseif (!$user) {
				$user = $this->ID;
			}

			return BigTree\PendingChange::allByUser($user, "date DESC", true);
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

		static function getPendingNavigationByParent($parent, $in_nav = true) {
			$page = new BigTree\Page($parent, false);

			return $page->getPendingChildren($in_nav);
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

			return $folder->getContents($sort);
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
			if (!BigTree\Resource::exists($id)) {
				return false;
			}
			
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

		static function getResourceFolderBreadcrumb($folder) {
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
			return BigTree\ResourceFolder::allByParent($id, "name ASC", true);
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
			$folder = new BigTree\ResourceFolder($folder);

			return $folder->UserAccessLevel;
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
			$list = BigTree\Template::allByRouted("on", $sort, true);
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

		static function getSetting($id, $decode = true) {
			$setting = new BigTree\Setting($id, $decode);

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
			$settings = BigTree\Setting::all($sort, true);

			// Only draw settings the admin can use
			$filtered_settings = array();
			foreach ($settings as $setting) {
				if (!$setting["system"] && ($this->Level > 1 || !$setting["locked"])) {
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
			return BigTree\Setting::allSystem($sort, true);
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
			return BigTree\Template::all($sort, true);
		}

		/*
			Function: getUnreadMessageCount
				Returns the number of unread messages for the logged in user.

			Returns:
				The number of unread messages.
		*/

		function getUnreadMessageCount() {
			return BigTree\Message::getUserUnreadCount();
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
			return BigTree\User::all($sort, true);
		}

		/*
			Function: growl
				Sets up a growl session for the next page reload.

			Parameters:
				title - The section message for the growl.
				message - The description of what happened.
				type - The icon to draw.
		*/

		static function growl($title, $message, $type = "success") {
			BigTree\Utils::growl($title, $message, $type);
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
			
			$redirect = new BigTree\Redirect($id);
			$redirect->Ignored = true;
			$redirect->save();
		}

		/*
			Function: initSecurity
				Sets up security environment variables and runs white/blacklists for IP checks.
		*/

		function initSecurity() {
			$this->Auth->initSecurity();
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

		function installExtension($manifest, $upgrade = false) {
			$extension = BigTree\Extension::installFromManifest($manifest, $upgrade);

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

			Returns:
				Your lock id.
		*/

		function lockCheck($table, $id, $include, $force = false) {
			BigTree\Lock::enforce($table, $id, $include, $force);
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

		function login($email, $password, $stay_logged_in = false) {
			return $this->Auth->login($email, $password, $stay_logged_in);
		}

		/*
			Function: logout
				Logs out of the CMS.
				Destroys the user's session and unsets the login cookies, then sends the user back to the login page.
		*/

		function logout() {
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
			return BigTree\Link::iplEncode($url);
		}

		/*
			Function: markMessageRead
				Marks a message as read by the currently logged in user.

			Parameters:
				id - The message id.
		*/

		function markMessageRead($id) {
			$message = new BigTree\Message($id);
			$message->markRead();
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

		static function matchResourceMD5($file, $new_folder) {
			return BigTree\Resource::md5Check($file, $new_folder);
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
			$tag = new BigTree\Tag($tag);
			
			if (empty($tag->ID)) {
				return false;
			}
			
			foreach ($merge_tags as $merge_id) {
				$tag->merge($merge_id);
			}
			
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

		static function pageChangeExists($page) {
			$page = new BigTree\Page($page, false);

			return $page->ChangeExists;
		}

		/*
			Function: pingSearchEngines
				Sends the latest sitemap.xml out to search engine ping services if enabled in settings.
		*/

		static function pingSearchEngines() {
			BigTree\Sitemap::pingSearchEngines();
		}

		/*
			Function: processCrops
				Processes a list of cropped images.

			Parameters:
				crop_key - A cache key pointing to the location of crop data.
		*/

		static function processCrops($crop_key) {
			BigTree\Image::processCrops($crop_key);
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
				"settings" - a keyed array of settings for the field, keys of interest for photo processing are:
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

		static function processImageUpload($field, $replace = false, $force_local_replace = false) {
			$field = new BigTree\Field($field);
			
			return $field->processImageUpload($replace, $force_local_replace);
		}

		/*
			Function: refreshLock
				Refreshes a lock.

			Parameters:
				table - The table for the lock.
				id - The id of the item.
		*/

		function refreshLock($table, $id) {
			BigTree\Lock::refresh($table, $id);
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
			$module = new BigTree\Module($module);
			BigTree\Auth::user()->requireAccess($module);
		}

		/*
			Function: requireLevel
				Requires the logged in user to have a certain access level to continue.
				Throws a permission denied page and stops page execution if the user doesn't have access.

			Parameters:
				level - An access level (0 being normal user, 1 being administrator, 2 being developer)
		*/

		function requireLevel($level) {
			BigTree\Auth::user()->requireLevel($level);
		}

		/*
			Function: requirePublisher
				Checks the logged in user's access to a given module to make sure they are a publisher.
				Throws a permission denied page and stops page execution if the user doesn't have access.

			Parameters:
				module - The id of the module to check access to.
		*/

		function requirePublisher($module) {
			$module = new BigTree\Module($module);
			BigTree\Auth::user()->requirePublisher($module);
		}

		/*
			Function: runCron
				Runs cron jobs
		*/

		function runCron() {
			BigTree\Cron::run();
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

		function saveCurrentPageRevision($page, $description) {
			$page = new BigTree\Page($page);

			if ($page->UserAccessLevel != "p") {
				$this->stop("You must be a publisher to manage revisions.");
			}

			$revision = BigTree\PageRevision::create($page, $description);

			return $revision->ID;
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

		static function search404s($type, $query = "", $page = 1, $site_key = null) {
			return BigTree\Redirect::search($type, $query, $page, $site_key, true);
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

		static function searchAuditTrail($user = false, $table = false, $entry = false, $start = false, $end = false) {
			return BigTree\AuditTrail::search($user ?: null, $table ?: null, $entry ?: null, $start ?: null, $end ?: null);
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

		static function searchPages($query, $fields = array("nav_title"), $max = 10) {
			return BigTree\Page::search($query, $fields, $max, true);
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
			return BigTree\Resource::search($query, $sort);
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

		static function searchTags($tag, $full_row = false) {
			if ($full_row) {
				$tags = BigTree\Tag::allSimilar($tag, 8);
				$tags_array = [];
				
				foreach ($tags as $tag) {
					$tag_array["tag"] = $tag_array["name"];
					$tags_array[] = $tag_array;
				}

				return $tags_array;
			} else {
				return BigTree\Tag::allSimilar($tag, 8, true);
			}
		}

		/*
			Function: set404Redirect
				Sets the redirect address for a 404.
				Checks permissions.

			Parameters:
				id - The id of the 404.
				url - The redirect URL.
		*/

		function set404Redirect($id, $url) {
			$this->requireLevel(1);

			$redirect = new BigTree\Redirect($id);
			$redirect->RedirectURL = $url;
			$redirect->save();
		}

		/*
			Function: setCalloutPosition
				Sets the position of a callout.

			Parameters:
				id - The id of the callout.
				position - The position to set.
		*/

		static function setCalloutPosition($id, $position) {
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

		static function setModuleActionPosition($id, $position) {
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

		static function setModuleGroupPosition($id, $position) {
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

		static function setModulePosition($id, $position) {
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

		static function setPagePosition($id, $position) {
			$page = new BigTree\Page($id, false);
			$page->updatePosition($position);
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

		static function setTemplatePosition($id, $position) {
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

		function stop($message = "", $file = "") {
			$this->Auth->stop($message, $file);
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

		function submitPageChange($page, $changes) {
			// Unset the trunk flag if the user isn't a developer
			if ($this->Level < 2) {
				unset($changes["trunk"]);
			}

			BigTree\Page::createChangeRequest($page, $changes);
		}

		/*
			Function: track
				Logs a user's actions to the audit trail table.

			Parameters:
				table - The table affected by the user.
				entry - The primary key of the entry affected by the user.
				type - The action taken by the user (delete, edit, create, etc.)
		*/

		static function track($table, $entry, $type) {
			BigTree\AuditTrail::track($table, $entry, $type);
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
			$page = new BigTree\Page($page, false);

			if ($page->UserAccessLevel == "p" && $page->UserCanModifyChildren) {
				$page->unarchive();

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
			$page = new BigTree\Page($id, false);
			$page->unarchiveChildren();
		}

		/*
			Function: ungrowl
				Destroys the growl session.
		*/

		static function ungrowl() {
			BigTree\Utils::ungrowl();
		}

		/*
			Function: urlExists
				Attempts to connect to a URL using cURL.

			Parameters:
				url - The URL to connect to.

			Returns:
				true if it can connect, false if connection failed.
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
			$page = new BigTree\Page($page, false);
			$page->uncache();
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

			$redirect = new BigTree\Redirect($id);
			$redirect->Ignored = false;
			$redirect->save();
		}

		/*
			Function: unlock
				Removes a lock from a table entry.

			Parameters:
				table - The table the entry is in.
				id - The id of the entry.
		*/

		static function unlock($table, $id) {
			BigTree\Lock::remove($table, $id);
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

		function updateCallout($id, $name, $description, $level, $resources, $display_field, $display_default) {
			$callout = new BigTree\Callout($id);
			$callout->update($name, $description, $level, $resources, $display_field, $display_default);
		}

		/*
			Function: updateCalloutGroup
				Updates a callout group's name and callout list.

			Parameters:
				id - The id of the callout group to update.
				name - The name.
				callouts - An array of callout IDs to assign to the group.
		*/

		function updateCalloutGroup($id, $name, $callouts) {
			$group = new BigTree\CalloutGroup($id);
			$group->update($name, $callouts);
		}

		/*
			Function: updateChildPagePaths
				Updates the paths for pages who are descendants of a given page to reflect the page's new route.
				Also sets route history if the page has changed paths.

			Parameters:
				page - The page id.
		*/

		static function updateChildPagePaths($page) {
			$page = new BigTree\Page($page, false);
			$page->updateChildrenPaths();
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

		function updateFeed($id, $name, $description, $table, $type, $settings, $fields) {
			if (is_string($settings)) {
				$settings = array_filter((array) json_decode($settings, true));
			}
			
			$feed = new BigTree\Feed($id);
			$feed->update($name, $description, $table, $type, $settings, $fields);
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

		function updateFieldType($id, $name, $use_cases, $self_draw) {
			$field_type = new BigTree\FieldType($id);
			$field_type->update($name, $use_cases, $self_draw ? true : false);
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

		function updateModule($id, $name, $group, $class, $permissions, $icon, $developer_only = false) {
			$module = new BigTree\Module($id);
			$module->update($name, $group, $class, $permissions, $icon, $developer_only);
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

		function updateModuleAction($id, $name, $route, $in_nav, $icon, $interface, $level, $position) {
			$action = new BigTree\ModuleAction($id);
			$action->update($name, $route, $in_nav ? true : false, $icon, $interface ?: null, $level, $position);
		}

		/*
			Function: updateModuleEmbedForm
				This function is disabled in BigTree 5.0+
		*/

		function updateModuleEmbedForm($id, $title, $table, $fields, $hooks = array(), $default_position = "", $default_pending = "", $css = "", $redirect_url = "", $thank_you_message = "") {
			trigger_error("BigTree 5.0 does not support embeddable forms.", E_USER_ERROR);
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

		function updateModuleForm($id, $title, $table, $fields, $hooks = array(), $default_position = "", $return_view = false, $return_url = "", $tagging = "") {
			$form = new BigTree\ModuleForm($id);
			$form->update($title, $table, $fields, $hooks, $default_position, $return_view, $return_url, $tagging);
		}

		/*
			Function: updateModuleGroup
				Updates a module group's name.

			Parameters:
				id - The id of the module group to update.
				name - The name of the module group.
		*/

		function updateModuleGroup($id, $name) {
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

		function updateModuleInterface($id, $title, $table, $settings = array()) {
			$interface = new BigTree\ModuleInterface($id);
			$interface->Title = $title;
			$interface->Table = $table;
			$interface->Settings = $settings;
			$interface->save();
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

		function updateModuleReport($id, $title, $table, $type, $filters, $fields = "", $parser = "", $view = "") {
			$report = new BigTree\ModuleReport($id);
			$report->update($title, $table, $type, $filters, $fields ?: null, $parser, $view ?: null);
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
				settings - View settings array.
				fields - Field array.
				actions - Actions array.
				related_form - Form ID to handle edits.
				preview_url - Optional preview URL.
		*/

		function updateModuleView($id, $title, $description, $table, $type, $settings, $fields, $actions, $related_form, $preview_url = "") {
			$view = new BigTree\ModuleView($id);
			$view->update($title, $description, $table, $type, $settings, $fields, $actions, $related_form, $preview_url);
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

		function updateModuleViewFields($id, $fields) {
			$view = new BigTree\ModuleView($id);
			$view->Fields = $fields;

			// Automatically saves
			$view->refreshNumericColumns();
		}

		/*
			Function: updatePage
				Updates a page.
				Checks some (but not all) permissions.

			Parameters:
				page - The page id to update.
				data - The page data to update with.
		*/

		function updatePage($page, $data) {
			// Set local variables in a clean fashion that prevents _SESSION exploitation.  Also, don't let them somehow overwrite $page and $current.
			$trunk = $in_nav = $external = $route = $publish_at = $expire_at = $nav_title = $title = $template = $new_window = $meta_description = $seo_invisible = "";
			$parent = $max_age = 0;
			$resources = array();

			foreach ($data as $key => $val) {
				if (substr($key, 0, 1) != "_" && $key != "current" && $key != "page") {
					$$key = $val;
				}
			}

			$page = new BigTree\Page($page);

			// Figure out if we currently have a template that the user isn't allowed to use. If they do, we're not letting them change it.
			if ($page->Template) {
				$template_level = SQL::fetchSingle("SELECT level FROM bigtree_templates WHERE id = ?", $page->Template);
				if ($template_level > $this->Level) {
					$template = $page->Template;
				}
			}

			// Set the trunk flag back to the current value if the user isn't a developer
			$trunk = ($this->Level < 2) ? $page->Trunk : $trunk;

			// If this is top level nav and the user isn't a developer, use what the current state is.
			$in_nav = (!$page->Parent && $this->Level < 2) ? $page->InNav : $in_nav;

			// If somehow we didn't provide a parent page (like, say, the user didn't have the right to change it) then pull the one from before.  Actually, this might be exploitable… look into it later.
			if (!isset($data["parent"])) {
				$parent = $page->Parent;
			}

			$page->update($trunk, $parent, $in_nav, $nav_title, $title, $route, $meta_description, $seo_invisible, $template, $external, $new_window, $resources, $publish_at, $expire_at, $max_age, $data["_tags"]);
		}

		/*
			Function: updatePageParent
				Changes a page's parent.
				Checks permissions.

			Parameters:
				page - The page to update.
				parent - The parent to switch to.
		*/

		function updatePageParent($page, $parent) {
			if ($this->Level < 1) {
				$this->stop("You are not allowed to move pages.");
			}

			$page = new BigTree\Page($page, false);

			// Reset back to not in nav if a non-developer is moving to top level
			if ($this->Level < 2 && $parent == 0) {
				SQL::update("bigtree_pages", $page->ID, array("in_nav" => ""));
			}

			$page->updateParent($parent);
		}

		/*
			Function: updatePageRevision
				Updates a page revision to save it as a favorite.
				Checks permissions.

			Parameters:
				id - The page revision id.
				description - Saved description.
		*/

		function updatePageRevision($id, $description) {
			// Get the version, check if the user has access to the page the version refers to.
			$revision = new BigTree\PageRevision($id);
			$origin_page = new BigTree\Page($revision->Page);

			if ($origin_page->UserAccessLevel != "p") {
				$this->stop("You must be a publisher to manage revisions.");
			}

			$revision->update($description);
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

		function updatePendingChange($id, $changes, $mtm_changes = array(), $tags_changes = array()) {
			$change = new BigTree\PendingChange($id);
			$change->update($changes, $mtm_changes, $tags_changes);
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

		function updateProfile($name, $company = "", $daily_digest = "", $password = false) {
			BigTree\User::updateProfile($name, $company, $daily_digest ? true : false, $password);
		}

		/*
			Function: updateResource
				Updates a resource.

			Parameters:
				id - The id of the resource.
				attributes - A key/value array of fields to update.
		*/

		function updateResource($id, $attributes) {
			$resource = new BigTree\Resource($id);
			foreach ($attributes as $key => $val) {
				// Camel case attributes
				$key = str_replace(" ", "", ucwords(str_replace("_", " ", $key)));
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

		function updateSetting($old_id, $data) {
			$id = $type = $name = $description = $locked = $encrypted = $system = "";
			$settings = array();

			foreach ($data as $key => $val) {
				if (substr($key, 0, 1) != "_") {
					$$key = $val;
				}
			}

			$setting = new BigTree\Setting($old_id, false);

			return $setting->update($id, $type, $settings, $name, $description, $locked ? true : false, $encrypted ? true : false, $system ? true : false);
		}

		/*
			Function: updateSettingValue
				Updates the value of a setting.

			Parameters:
				id - The id of the setting to update.
				value - A value to set (can be a string or array).
		*/

		static function updateSettingValue($id, $value) {
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

		function updateTemplate($id, $name, $level, $module, $resources) {
			$template = new BigTree\Template($id);
			$template->update($name, $level, $module, $resources);
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
				daily_digest - Whether the user wishes to receive the daily digest email,
				timezone - The user's timezone

			Returns:
				True if successful.  False if the logged in user doesn't have permission to change the user or there was an email collision.
		*/

		function updateUser($id, $email, $password = "", $name = "", $company = "", $level = 0, $permissions = array(), $alerts = array(), $daily_digest = "", $timezone = "") {
			// Allow for pre-4.3 syntax
			if (is_array($email)) {
				$data = $email;
				foreach ($data as $key => $val) {
					if (substr($key, 0, 1) != "_") {
						$$key = $val;
					}
				}
			}

			$user = new BigTree\User($id);

			return $user->update($email, $password, $name, $company, $level, $permissions, $alerts, $daily_digest ? true : false, $timezone);
		}

		/*
			Function: updateUserPassword
				Updates a user's password.

			Parameters:
				id - The user's id.
				password - The new password.
		*/

		static function updateUserPassword($id, $password) {
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
			return BigTree\Text::versionToDecimal($version);
		}
	}
