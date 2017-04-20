<?php
	/*
		Class: BigTree\PendingChange
			Provides an interface for handling BigTree pending changes.
	*/
	
	namespace BigTree;
	
	/**
	 * @property-read string $Date
	 * @property-read int $ID
	 */
	
	class PendingChange extends BaseObject {
		
		public static $Table = "bigtree_pending_changes";
		
		protected $Date;
		protected $ID;
		
		public $Changes;
		public $ItemID;
		public $ManyToManyChanges;
		public $Module;
		public $PendingPageParent;
		public $PublishHook;
		public $TagsChanges;
		public $Title;
		public $User;
		
		/*
			Constructor:
				Builds a PendingChange object referencing an existing database entry.

			Parameters:
				change - Either an ID (to pull a record) or an array (to use the array as the record)
		*/
		
		function __construct($change = null) {
			if ($change !== null) {
				// Passing in just an ID
				if (!is_array($change)) {
					$change = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $change);
				}
				
				// Bad data set
				if (!is_array($change)) {
					trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
				} else {
					$this->Date = $change["date"];
					$this->ID = $change["id"];
					
					$this->Changes = (array) @json_decode($change["changes"], true);
					$this->ItemID = ($change["item_id"] !== null) ? $change["item_id"] : null;
					$this->ManyToManyChanges = (array) @json_decode($change["mtm_changes"], true);
					$this->Module = $change["module"];
					$this->PendingPageParent = $change["pending_page_parent"];
					$this->PublishHook = $change["publish_hook"];
					$this->Table = $change["table"];
					$this->TagsChanges = (array) @json_decode($change["tags_changes"], true);
					$this->Title = $change["title"];
					$this->User = $change["user"];
				}
			}
		}
		
		/*
			Function: allPublishableByUser
				Returns an array of changes that the given user has access to publish.

			Parameters:
				user - A User object

			Returns:
				An array of PendingChange objects sorted by most recent.
		*/
		
		static function allPublishableByUser(User $user): array {
			$publishable_changes = [];
			$module_cache = [];
			
			// Setup the default search array to just be pages
			$search = ["`module` = ''"];
			
			// Add each module the user has publisher permissions to
			if (is_array($user->Permissions["module"])) {
				foreach ($user->Permissions["module"] as $module => $permission) {
					if ($permission == "p") {
						$search[] = "`module` = '$module'";
					}
				}
			}
			
			// Add module group based permissions as well
			if (isset($user->Permissions["module_gbp"]) && is_array($user->Permissions["module_gbp"])) {
				foreach ($user->Permissions["module_gbp"] as $module => $groups) {
					foreach ($groups as $group => $permission) {
						if ($permission == "p") {
							$search[] = "`module` = '$module'";
						}
					}
				}
			}
			
			$changes = SQL::fetchAll("SELECT * FROM bigtree_pending_changes WHERE ".implode(" OR ", $search)." ORDER BY date DESC");
			
			foreach ($changes as $change) {
				$ok = false;
				
				// Append a p if this isn't a change but rather a pending item
				if (!$change["item_id"]) {
					$id = "p".$change["id"];
				} else {
					$id = $change["item_id"];
				}
				
				// If they're an admin, they've got it.
				if ($user->Level > 0) {
					$ok = true;
					// Check permissions on a page if it's a page.
				} elseif ($change["table"] == "bigtree_pages") {
					
					// If this page isn't published we'll grab the parent permission
					if ($change["item_id"]) {
						$page = new Page($change["item_id"]);
					} else {
						$page = new Page($change["pending_page_parent"]);
					}
					
					$access_level = $page->getUserAccessLevel($user);
					
					// If we're a publisher, this is ours!
					if ($access_level == "p") {
						$ok = true;
					}
				} else {
					// Check our list of modules.
					if ($user->Permissions["module"][$change["module"]] == "p") {
						$ok = true;
					} else {
						// Cache the modules so we don't make a ton of duplicate objects and waste memory
						if (!$module_cache[$change["module"]]) {
							$module_cache[$change["module"]] = new Module($change["module"]);
						}
						
						$module = $module_cache[$change["module"]];
						
						// Check our group based permissions
						$form = new ModuleForm(["table" => $change["table"]]);
						$item = $form->getPendingEntry($id);
						$access_level = Auth::user($user)->getAccessLevel($module, $item["item"], $change["table"]);
						
						if ($access_level == "p") {
							$ok = true;
						}
					}
				}
				
				// We're a publisher, get the info about the change and put it in the change list.
				if ($ok) {
					$pending_change = new PendingChange($change);
					$pending_change->User = new User($change["user"]);
					$pending_change->Module = $change["module"] ? new Module($change["module"]) : null;
					
					$publishable_changes[] = $pending_change;
				}
			}
			
			return $publishable_changes;
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
				publish_hook - An optional publishing hook.
				embedded_form - If this is a submission from an embeddable form, set to true.

			Returns:
				A PendingChange object.
		*/
		
		static function create(string $table, string $item_id, array $changes, array $mtm_changes = [],
							   array $tags_changes = [], int $module = 0, ?string $publish_hook = null,
							   bool $embedded_form = false): PendingChange {
			// Clean up data for JSON storage
			foreach ($changes as $key => $val) {
				if ($val === "NULL") {
					$changes[$key] = "";
				}
			}
			
			$changes = Link::encode($changes);
			
			// If this is an existing entry's changes, only keep what's different
			if ($item_id !== false) {
				$original = SQL::fetch("SELECT * FROM `$table` WHERE id = ?", $item_id);
				
				foreach ($changes as $key => $value) {
					if ($original[$key] === $value) {
						unset($changes[$key]);
					}
				}
			}
			
			// Get the user creating the change
			if ($embedded_form) {
				$user = null;
			} else {
				$user = Auth::user()->ID;
			}
			
			$id = SQL::insert("bigtree_pending_changes", [
				"user" => $user,
				"date" => "NOW()",
				"table" => $table,
				"item_id" => ($item_id !== false ? $item_id : null),
				"changes" => $changes,
				"mtm_changes" => $mtm_changes,
				"tags_changes" => $tags_changes,
				"module" => $module,
				"publish_hook" => $publish_hook ?: null
			]);
			
			ModuleView::cacheForAll($id, $table, true);
			AuditTrail::track($table, "p".$id, "created-pending");
			
			return new PendingChange($id);
		}
		
		/*
			Function: createPage
				Creates a pending page entry.

			Parameters:
				trunk - Trunk status (true or false)
				parent - Parent page ID
				in_nav - In navigation (true or false)
				nav_title - Navigation title
				title - Page title
				route - Page route (leave null to auto generate)
				meta_description - Page meta description
				seo_invisible - Pass "X-Robots-Tag: noindex" header (true or false)
				template - Page template ID
				external - External link (or empty)
				new_window - Open in new window from nav (true or false)
				resources - Array of page data
				publish_at - Publish time (or null for immediate publishing)
				expire_at - Expiration time (or null for no expiration)
				max_age - Content age (in days) allowed before alerts are sent (null for no max)
				tags - An array of tags to apply to the page (optional)

			Returns:
				A PendingChange object.
		*/
		
		static function createPage(?bool $trunk, ?int $parent, ?bool $in_nav, ?string $nav_title, ?string $title,
								   ?string $route, ?string $meta_description, ?bool $seo_invisible, ?string $template,
								   ?string $external, ?bool $new_window, ?array $fields, ?string $publish_at,
								   ?string $expire_at, ?int $max_age, ?array $tags = []): PendingChange {
			// Get the user creating the change
			$user = Auth::user()->ID;
			
			$changes = [
				"trunk" => $trunk ? "on" : "",
				"parent" => $parent,
				"in_nav" => $in_nav ? "on" : "",
				"nav_title" => Text::htmlEncode($nav_title),
				"title" => Text::htmlEncode($title),
				"route" => Text::htmlEncode($route),
				"meta_description" => Text::htmlEncode($meta_description),
				"seo_invisible" => $seo_invisible ? "on" : "",
				"template" => $template,
				"external" => $external ? Link::encode($external) : "",
				"new_window" => $new_window ? "on" : "",
				"resources" => $fields,
				"publish_at" => $publish_at ? date("Y-m-d H:i:s", strtotime($publish_at)) : null,
				"expire_at" => $expire_at ? date("Y-m-d H:i:s", strtotime($expire_at)) : null,
				"max_age" => $max_age ? intval($max_age) : ""
			];
			
			$id = SQL::insert("bigtree_pending_changes", [
				"user" => $user,
				"date" => "NOW()",
				"table" => "bigtree_pages",
				"changes" => $changes,
				"tags_changes" => $tags,
				"pending_page_parent" => intval($parent)
			]);
			
			AuditTrail::track("bigtree_pages", "p".$id, "created-pending");
			
			return new PendingChange($id);
		}
		
		/*
			Function: existsForEntry
				Checks to see if a pending change exists for a given entry ID and table.

			Parameters:
				table - The table the item is from.
				id - The ID of the item.

			Returns:
				true or false
		*/
		
		static function existsForEntry(string $table, string $id): bool {
			$change_count = SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_pending_changes 
											  WHERE `table` = ? AND item_id = ?", $table, $id);
			
			return $change_count ? true : false;
		}
		
		/*
			Function: getEditLink
				Returns a link to where the pending change can be edited.

			Returns:
				A string containing a link to the admin.
		*/
		
		function getEditLink(): string {
			global $bigtree;
			
			// Pages are easy
			if ($this->Table == "bigtree_pages") {
				if ($this->ItemID) {
					return $bigtree["config"]["admin_root"]."pages/edit/".$this->ItemID."/";
				} else {
					return $bigtree["config"]["admin_root"]."pages/edit/p".$this->ID."/";
				}
			}
			
			// Find a form that uses this table (it's our best guess here)
			$form_id = SQL::fetchSingle("SELECT id FROM bigtree_module_interfaces 
										 WHERE `type` = 'form' AND `table` = ?", $this->Table);
			if (!$form_id) {
				return false;
			}
			
			// Get the module route
			$module_route = SQL::fetchSingle("SELECT route FROM bigtree_modules WHERE `id` = ?", $this->Module);
			
			// We set in_nav to empty because edit links aren't in nav (and add links are) so we can predict where the edit action will be this way
			$action_route = SQL::fetchSingle("SELECT route FROM bigtree_module_actions 
											  WHERE `interface` = ? AND `in_nav` = ''", $form_id);
			
			// Got an action
			if ($action_route) {
				return $bigtree["config"]["admin_root"].$module_route."/".$action_route."/".($this->ItemID ?: "p".$this->ID)."/";
			}
			
			// Couldn't find a link
			return false;
		}
		
		/*
			Function: save
				Saves the object properties back to the database.
		*/
		
		function save(): ?bool {
			if (empty($this->ID)) {
				$new = static::create($this->Table, $this->ItemID, $this->Changes, $this->ManyToManyChanges, $this->TagsChanges, $this->Module, $this->PublishHook);
				$this->inherit($new);
			} else {
				// Get the user creating the change
				$user = Auth::user()->ID;
				
				// If this is an existing entry's changes, only keep what's different
				if ($this->ItemID !== false) {
					$original = SQL::fetch("SELECT * FROM `".$this->Table."` WHERE id = ?", $this->ItemID);
					
					foreach ($this->Changes as $key => $value) {
						if ($original[$key] === $value) {
							unset($this->Changes[$key]);
						}
					}
				}
				
				SQL::update("bigtree_pending_changes", $this->ID, [
					"changes" => $this->Changes,
					"item_id" => $this->ItemID ?: null,
					"mtm_changes" => $this->ManyToManyChanges,
					"module" => $this->Module ?: "",
					"pending_page_parent" => $this->PendingPageParent,
					"publish_hook" => $this->PublishHook ?: null,
					"tags_changes" => $this->TagsChanges,
					"title" => Text::htmlEncode($this->Title),
					"user" => $user ?: $this->User
				]);
				
				AuditTrail::track("bigtree_pending_changes", $this->ID, "updated");
			}
			
			return true;
		}
		
		/*
			Function: update
				Updates the pending change.

			Parameters:
				changes - The changes to the fields in the entry.
				mtm_changes - Many to Many changes.
				tags_changes - Tags changes.
		*/
		
		function update(array $changes, array $mtm_changes = [], array $tags_changes = []): void {
			$this->Changes = $changes;
			$this->ManyToManyChanges = $mtm_changes;
			$this->TagsChanges = $tags_changes;
			
			$this->save();
		}
		
	}
	