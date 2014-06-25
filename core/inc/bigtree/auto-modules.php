<?
	/*
		Class: BigTreeAutoModule
			Handles functions for auto module forms / views created in Developer.
	*/

	class BigTreeAutoModule {
		
		/*
			Function: cacheNewItem
				Caches a new database entry by investigating associated views.
			
			Parameters:
				id - The id of the new item.
				table - The table the new item is in.
				pending - Whether this is actually a pending entry or not.
				recache - Whether to delete previous cached entries with this id (for use by recacheItem)
			
			See Also:
				<recacheItem>
		*/
		
		static function cacheNewItem($id,$table,$pending = false,$recache = false) {
			if (!$pending) {
				$item = sqlfetch(sqlquery("SELECT `$table`.*,bigtree_pending_changes.changes AS bigtree_changes FROM `$table` LEFT JOIN bigtree_pending_changes ON (bigtree_pending_changes.item_id = `$table`.id AND bigtree_pending_changes.table = '$table') WHERE `$table`.id = '$id'"));
				$original_item = $item;
				if ($item["bigtree_changes"]) {
					$changes = json_decode($item["bigtree_changes"],true);
					foreach ($changes as $key => $change) {
						$item[$key] = $change;
					}
				}
			} else {
				$f = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE id = '$id'"));
				$item = json_decode($f["changes"],true);
				$item["bigtree_pending"] = true;
				$item["bigtree_pending_owner"] = $f["user"];
				$item["id"] = "p".$f["id"];
				$original_item = $item;
			}
			
			$q = sqlquery("SELECT * FROM bigtree_module_views WHERE `table` = '$table'");
			while ($view = sqlfetch($q)) {
				if ($recache) {
					sqlquery("DELETE FROM bigtree_module_view_cache WHERE `view` = '".$view["id"]."' AND id = '".$item["id"]."'");
				}
				
				$view["fields"] = json_decode($view["fields"],true);
				$view["actions"] = json_decode($view["actions"],true);
				$view["options"] = json_decode($view["options"],true);
				
				// In case this view has never been cached, run the whole view, otherwise just this one.
				if (!self::cacheViewData($view)) {
				
					// Find out what module we're using so we can get the gbp_field
					$action = sqlfetch(sqlquery("SELECT module FROM bigtree_module_actions WHERE view = '".$view["id"]."'"));
					$module = sqlfetch(sqlquery("SELECT gbp FROM bigtree_modules WHERE id = '".$action["module"]."'"));
					$view["gbp"] = json_decode($module["gbp"],true);
					
					$form = self::getRelatedFormForView($view);
					
					$parsers = array();
					$poplists = array();
					
					foreach ($view["fields"] as $key => $field) {
						$value = $item[$key];
						if ($field["parser"]) {
							$parsers[$key] = $field["parser"];
						} elseif ($form["fields"][$key]["type"] == "list" && $form["fields"][$key]["list_type"] == "db") {
							$poplists[$key] = array("description" => $form["fields"][$key]["pop-description"], "table" => $form["fields"][$key]["pop-table"]);
						}
					}
					
					self::cacheRecord($item,$view,$parsers,$poplists,$original_item);
				}
			}
		}
		
		/*
			Function: cacheRecord
				Caches a single item in a view to the bigtree_module_view_cache table.
			
			Parameters:
				item - The database record to cache.
				view - The related view entry.
				parsers - An array of manual parsers set in the view.
				poplists - An array of populated lists that relate to the item.
				original_item - The item without pending changes applied for GBP.
		*/
		
		static function cacheRecord($item,$view,$parsers,$poplists,$original_item) {
			// If we have a filter function, ask it first if we should cache it
			if (isset($view["options"]["filter"]) && $view["options"]["filter"]) {
				if (!call_user_func($view["options"]["filter"],$item)) {
					return false;
				}
			}

			global $cms;
			
			// Setup the fields and VALUES to INSERT INTO the cache table.
			$status = "l";
			$pending_owner = 0;
			if ($item["bigtree_changes"]) {
				$status = "c";
			} elseif (isset($item["bigtree_pending"])) {
				$status = "p";
				$pending_owner = $item["bigtree_pending_owner"];
			}
			$fields = array("view","id","status","position","approved","archived","featured","pending_owner");

			// No more notices.
			$approved = isset($item["approved"]) ? $item["approved"] : "";
			$featured = isset($item["featured"]) ? $item["featured"] : "";
			$archived = isset($item["archived"]) ? $item["archived"] : "";
			$position = isset($item["position"]) ? $item["position"] : 0;

			$vals = array("'".$view["id"]."'","'".$item["id"]."'","'$status'","'$position'","'$approved'","'$archived'","'$featured'","'".$pending_owner."'");

			// Figure out which column we're going to use to sort the view.
			if ($view["options"]["sort"]) {
				$sort_field = BigTree::nextSQLColumnDefinition(ltrim($view["options"]["sort"],"`"));
			} else {
				$sort_field = false;
			}
			
			// Let's see if we have a grouping field.  If we do, let's get all that info and cache it as well.
			if (isset($view["options"]["group_field"]) && $view["options"]["group_field"]) {
				$value = $item[$view["options"]["group_field"]];

				// Check for a parser
				if (isset($view["options"]["group_parser"]) && $view["options"]["group_parser"]) {
					$value = BigTree::runParser($item,$value,$view["options"]["group_parser"]);
				}

				$fields[] = "group_field";
				$vals[] = "'".sqlescape($value)."'";
				
				if (is_numeric($value) && $view["options"]["other_table"]) {
					$f = sqlfetch(sqlquery("SELECT * FROM `".$view["options"]["other_table"]."` WHERE id = '$value'"));
					if ($view["options"]["ot_sort_field"]) {
						$fields[] = "group_sort_field";
						$vals[] = "'".sqlescape($f[$view["options"]["ot_sort_field"]])."'";
					}
				}
			}

			// Check for a nesting column
			if (isset($view["options"]["nesting_column"]) && $view["options"]["nesting_column"]) {
				$fields[] = "group_field";
				$vals[] = "'".sqlescape($item[$view["options"]["nesting_column"]])."'";
			}
			
			// Group based permissions data
			if (isset($view["gbp"]["enabled"]) && $view["gbp"]["table"] == $view["table"]) {
				$fields[] = "gbp_field";
				$vals[] = "'".sqlescape($item[$view["gbp"]["group_field"]])."'";
				$fields[] = "published_gbp_field";
				$vals[] = "'".sqlescape($original_item[$view["gbp"]["group_field"]])."'";
			}

			// Run parsers
			foreach ($parsers as $key => $parser) {
				$item[$key] = BigTree::runParser($item,$item[$key],$parser);
			}
			
			// Run pop lists
			foreach ($poplists as $key => $pop) {
				$f = sqlfetch(sqlquery("SELECT `".$pop["description"]."` FROM `".$pop["table"]."` WHERE id = '".$item[$key]."'"));
				if (is_array($f)) {
					$item[$key] = current($f);
				}
			}

			// Insert into the view cache			
			if ($view["type"] == "images" || $view["type"] == "images-grouped") {
				$fields[] = "column1";
				$vals[] = "'".$item[$view["options"]["image"]]."'";
			} else {
				$x = 1;
				foreach ($view["fields"] as $field => $options) {
					$item[$field] = $cms->replaceInternalPageLinks($item[$field]);
					$fields[] = "column$x";
					if (isset($parsers[$field]) && $parsers[$field]) {
						$vals[] = "'".sqlescape(BigTree::safeEncode($item[$field]))."'";					
					} else {
						$vals[] = "'".sqlescape(BigTree::safeEncode(strip_tags($item[$field])))."'";
					}
					$x++;
				}
			}
			if ($sort_field) {
				$fields[] = "`sort_field`";
				$vals[] = "'".sqlescape($item[$sort_field])."'";
			}
			
			sqlquery("INSERT INTO bigtree_module_view_cache (".implode(",",$fields).") VALUES (".implode(",",$vals).")");
		}
		
		/*
			Function: cacheViewData
				Grabs all the data from a view and does parsing on it based on automatic assumptions and manual parsers.
			
			Parameters:
				view - The view entry to cache data for.
		*/
		
		static function cacheViewData($view) {
			// See if we already have cached data.
			if (sqlrows(sqlquery("SELECT id FROM bigtree_module_view_cache WHERE view = '".$view["id"]."'"))) {
				return false;
			}
			
			// Find out what module we're using so we can get the gbp_field
			$action = sqlfetch(sqlquery("SELECT module FROM bigtree_module_actions WHERE view = '".$view["id"]."'"));
			$module = sqlfetch(sqlquery("SELECT gbp FROM bigtree_modules WHERE id = '".$action["module"]."'"));
			$view["gbp"] = json_decode($module["gbp"],true);
			
			// Setup information on our parsers and populated lists.
			$form = self::getRelatedFormForView($view);
			$view["fields"] = is_array($view["fields"]) ? $view["fields"] : array();
			$parsers = array();
			$poplists = array();
			
			foreach ($view["fields"] as $key => $field) {
				// Get the form field
				$ff = $form["fields"][$key];
				
				if ($field["parser"]) {
					$parsers[$key] = $field["parser"];
				} elseif ($ff["type"] == "list" && $ff["list_type"] == "db") {
					$poplists[$key] = array("description" => $ff["pop-description"], "table" => $ff["pop-table"]);
				}
			}
			
			// See if we need to modify the cache table to add more fields.
			$field_count = count($view["fields"]);
			$table_description = BigTree::describeTable("bigtree_module_view_cache");
			$cc = count($table_description["columns"]) - 13;
			while ($field_count > $cc) {
				$cc++;
				sqlquery("ALTER TABLE bigtree_module_view_cache ADD COLUMN column$cc TEXT NOT NULL AFTER column".($cc-1));
			}
			
			// Cache all records that are published (and include their pending changes)
			$q = sqlquery("SELECT `".$view["table"]."`.*,bigtree_pending_changes.changes AS bigtree_changes FROM `".$view["table"]."` LEFT JOIN bigtree_pending_changes ON (bigtree_pending_changes.item_id = `".$view["table"]."`.id AND bigtree_pending_changes.table = '".$view["table"]."')");
			while ($item = sqlfetch($q)) {
				$original_item = $item;
				if ($item["bigtree_changes"]) {
					$changes = json_decode($item["bigtree_changes"],true);
					foreach ($changes as $key => $change) {
						$item[$key] = $change;
					}
				}	

				self::cacheRecord($item,$view,$parsers,$poplists,$original_item);
			}

			$q = sqlquery("SELECT * FROM bigtree_pending_changes WHERE `table` = '".$view["table"]."' AND item_id IS NULL");
			while ($f = sqlfetch($q)) {
				$item = json_decode($f["changes"],true);
				$item["bigtree_pending"] = true;
				$item["bigtree_pending_owner"] = $f["user"];
				$item["id"] = "p".$f["id"];
				
				self::cacheRecord($item,$view,$parsers,$poplists,$item);
			}
			
			return true;
		}
		
		/*
			Function: changeExists
				Checks to see if a change exists for a given item in the bigtree_pending_changes table.

			Parameters:
				table - The table the item is from.
				id - The ID of the item.

			Returns:
				true or false
		*/

		static function changeExists($table,$id) {
			$f = sqlfetch(sqlquery("SELECT id FROM bigtree_pending_changes WHERE `table` = '".sqlescape($table)."' AND item_id = '".sqlescape($id)."'"));
			if ($f) {
				return true;
			}
			return false;
		}

		/*
			Function: clearCache
				Clears the cache of a view or all views with a given table.
			
			Parameters:
				view - The view id or view entry to clear the cache for or a table to find all views for (and clear their caches).
		*/
		
		static function clearCache($view) {
			if (is_array($view)) {
				sqlquery("DELETE FROM bigtree_module_view_cache WHERE view = '".sqlescape($view["id"])."'");		
			} elseif (is_numeric($view)) {
				sqlquery("DELETE FROM bigtree_module_view_cache WHERE view = '$view'");
			} else {
				$q = sqlquery("SELECT id FROM bigtree_module_views WHERE `table` = '".sqlescape($view)."'");
				while ($f = sqlfetch($q)) {
					sqlquery("DELETE FROM bigtree_module_view_cache WHERE view = '".$f["id"]."'");
				}
			}
		}
		
		/*
			Function: createItem
				Creates an entry in the database for an auto module form.
		
			Parameters:
				table - The table to put the data in.
				data - An array of form data to enter into the table. This function determines what data in the array applies to a column in the database and discards the rest.
				many_to_many - Many to many relationship entries.
				tags - Tags for the entry.
			
			Returns:
				The id of the new entry in the database.
		*/

		static function createItem($table,$data,$many_to_many = array(),$tags = array()) {
			global $admin,$module;
			
			$table_description = BigTree::describeTable($table);
			$query_fields = array();
			$query_vals = array();
			foreach ($data as $key => $val) {
				if (array_key_exists($key,$table_description["columns"])) {
					$query_fields[] = "`".$key."`";
					if ($val === "NULL" || $val == "NOW()") {
						$query_vals[] = $val;
					} else {
						if (is_array($val)) {
							$val = json_encode(BigTree::translateArray($val));
						}
						$query_vals[] = "'".sqlescape($val)."'";
					}
				}
			}
			sqlquery("INSERT INTO `$table` (".implode(",",$query_fields).") VALUES (".implode(",",$query_vals).")");
			$id = sqlid();

			// Handle many to many
			foreach ($many_to_many as $mtm) {
				$table_description = BigTree::describeTable($mtm["table"]);
				if (is_array($mtm["data"])) {
					$x = count($mtm["data"]);
					foreach ($mtm["data"] as $position => $item) {
						if (isset($table_description["columns"]["position"])) {
							sqlquery("INSERT INTO `".$mtm["table"]."` (`".$mtm["my-id"]."`,`".$mtm["other-id"]."`,`position`) VALUES ('$id','$item','$x')");
						} else {
							sqlquery("INSERT INTO `".$mtm["table"]."` (`".$mtm["my-id"]."`,`".$mtm["other-id"]."`) VALUES ('$id','$item')");
						}
						$x--;
					}
				}
			}

			// Handle the tags
			sqlquery("DELETE FROM bigtree_tags_rel WHERE `table` = '".sqlescape($table)."' AND entry = '$id'");
			if (is_array($tags)) {
				foreach ($tags as $tag) {
					sqlquery("DELETE FROM bigtree_tags_rel WHERE `table` = '".sqlescape($table)."' AND entry = $id AND tag = $tag");
					sqlquery("INSERT INTO bigtree_tags_rel (`table`,`entry`,`tag`) VALUES ('".sqlescape($table)."',$id,$tag)");
				}
			}
			
			self::cacheNewItem($id,$table);
			
			if ($admin) {
				$admin->track($table,$id,"created");
			}

			return $id;
		}
		
		/*
			Function: createPendingItem
				Creates an entry in the bigtree_pending_changes table for an auto module form.
		
			Parameters:
				module - The module for the entry.
				table - The table to put the data in.
				data - An array of form data to enter into the table. This function determines what data in the array applies to a column in the database and discards the rest.
				many_to_many - Many to many relationship entries.
				tags - Tags for the entry.
			
			Returns:
				The id of the new entry in the bigtree_pending_changes table.
		*/

		static function createPendingItem($module,$table,$data,$many_to_many = array(),$tags = array()) {
			global $admin;

			foreach ($data as $key => $val) {
				if ($val === "NULL") {
					$data[$key] = "";
				}
				if (is_array($val)) {
					$data[$key] = BigTree::translateArray($val);
				}
			}

			$data = sqlescape(json_encode($data));
			$many_data = sqlescape(json_encode($many_to_many));
			$tags_data = sqlescape(json_encode($tags));
			sqlquery("INSERT INTO bigtree_pending_changes (`user`,`date`,`table`,`changes`,`mtm_changes`,`tags_changes`,`module`,`type`) VALUES (".$admin->ID.",NOW(),'$table','$data','$many_data','$tags_data','$module','NEW')");
			
			$id = sqlid();

			self::cacheNewItem($id,$table,true);
			
			if ($admin) {
				$admin->track($table,"p$id","created-pending");
			}
			
			return $id;
		}
		
		/*
			Function: deleteItem
				Deletes an item from the given table and removes any pending changes, then uncaches it from its views.
			
			Parameters:
				table - The table to delete an entry from.
				id - The id of the entry.
		*/

		static function deleteItem($table,$id) {
			global $admin;
			
			$id = sqlescape($id);
			sqlquery("DELETE FROM `$table` WHERE id = '$id'");
			sqlquery("DELETE FROM bigtree_pending_changes WHERE `table` = '$table' AND item_id = '$id'");
			self::uncacheItem($id,$table);
			
			if ($admin) {
				$admin->track($table,$id,"deleted");
			}
		}
		
		/*
			Function: deleteItem
				Deletes a pending item from bigtree_pending_changes and uncaches it.
			
			Parameters:
				table - The table the entry would have been in (should it have ever been published).
				id - The id of the pending entry.
		*/
		
		static function deletePendingItem($table,$id) {
			global $admin;
			
			$id = sqlescape($id);
			sqlquery("DELETE FROM bigtree_pending_changes WHERE `table` = '$table' AND id = '$id'");
			self::uncacheItem("p$id",$table);

			if ($admin) {
				$admin->track($table,"p$id","deleted-pending");
			}
		}

		/*
			Function: getDependantViews
				Returns all views that have a dependance on a given table.

			Parameters:
				table - Table name

			Returns:
				An array of rows from bigtree_module_views.
		*/

		static function getDependantViews($table) {
			$views = array();
			$q = sqlquery("SELECT * FROM bigtree_module_views WHERE options LIKE '%".sqlescape($table)."%'");
			while ($f = sqlfetch($q)) {
				$views[] = $f;
			}
			return $views;
		}

		/*
			Function: getEditAction
				Returns a module action for the given module and form IDs.

			Parameters:
				module - Module ID
				form - Form ID

			Returns:
				A bigtree_module_actions entry.
		*/

		static function getEditAction($module,$form) {
			return sqlfetch(sqlquery("SELECT * FROM bigtree_module_actions WHERE form = '".sqlescape($form)."' AND module = '".sqlescape($module)."' AND route LIKE 'edit%'"));
		}

		/*
			Function: getEmbedForm
				Returns a module embeddable form.
			
			Parameters:
				id - The id of the form.
				decode_ipl - Whether we want to decode internal page link on the css file (defaults to true)
			
			Returns:
				A module form entry with fields decoded.
		*/

		static function getEmbedForm($id,$decode_ipl = true) {
			global $cms;

			if (is_array($id)) {
				$id = $id["id"];
			}

			$form = sqlfetch(sqlquery("SELECT * FROM bigtree_module_embeds WHERE id = '".sqlescape($id)."'"));
			$form["fields"] = json_decode($form["fields"],true);
			if ($decode_ipl) {
				$form["css"] = $cms->getInternalPageLink($form["css"]);
			}

			return $form;
		}

		/*
			Function: getEmbedFormByHash
				Returns a module embeddable form.
			
			Parameters:
				hash - The hash of the form.
			
			Returns:
				A module form entry with fields decoded.
		*/

		static function getEmbedFormByHash($hash) {
			global $cms;

			$form = sqlfetch(sqlquery("SELECT * FROM bigtree_module_embeds WHERE hash = '".sqlescape($hash)."'"));
			$form["fields"] = json_decode($form["fields"],true);
			$form["css"] = $cms->getInternalPageLink($form["css"]);

			return $form;
		}
		
		/*
			Function: getFilterQuery
				Returns a query string that is used for searching views based on group permissions.
			
			Parameters:
				view - The view to create a filter for.
			
			Returns:
				A set of MySQL statements that filter out information the user cannot access.
		*/
		
		static function getFilterQuery($view) {
			global $admin;
			$module = $admin->getModule(self::getModuleForView($view));
			if (isset($module["gbp"]["enabled"]) && $module["gbp"]["enabled"] && $module["gbp"]["table"] == $view["table"]) {
				$groups = $admin->getAccessGroups($module["id"]);
				if (is_array($groups)) {
					$gfl = array();
					foreach ($groups as $g) {
						$gfl[] = "`gbp_field` = '".sqlescape($g)."'";
					}
					return " AND (".implode(" OR ",$gfl).")";
				}
			}
			return "";
		}
		
		/*
			Function: getForm
				Returns a module form.
			
			Parameters:
				id - The id of the form.
				decode_ipl - Whether we want to decode internal page link on the return url (defaults to true)
			
			Returns:
				A module form entry with fields decoded.
		*/

		static function getForm($id,$decode_ipl = true) {
			global $cms;

			if (is_array($id)) {
				$id = $id["id"];
			}

			$form = sqlfetch(sqlquery("SELECT * FROM bigtree_module_forms WHERE id = '".sqlescape($id)."'"));
			$form["fields"] = json_decode($form["fields"],true);
			if ($decode_ipl) {
				$form["return_url"] = $cms->getInternalPageLink($form["return_url"]);
			}

			return $form;
		}
		
		/*
			Function: getGroupsForView
				Returns all groups in the view cache for a view.
			
			Parameters:
				view - The view entry.
			
			Returns:
				An array of groups.
		*/
		
		static function getGroupsForView($view) {
			$groups = array();
			$query = "SELECT DISTINCT(group_field) FROM bigtree_module_view_cache WHERE view = '".$view["id"]."'";
			if (isset($view["options"]["ot_sort_field"]) && $view["options"]["ot_sort_field"]) {
				// We're going to determine whether the group sort field is numeric or not first.
				$is_numeric = true;
				$q = sqlquery("SELECT DISTINCT(group_sort_field) FROM bigtree_module_view_cache WHERE view = '".$view["id"]."'");
				while ($f = sqlfetch($q)) {
					if (!is_numeric($f["group_sort_field"])) {
						$is_numeric = false;
					}
				}
				// If all of the groups are numeric we'll cast the sorting field as decimal so it's not interpretted as a string.
				if ($is_numeric) {
					$query .= " ORDER BY CAST(group_sort_field AS DECIMAL) ".$view["options"]["ot_sort_direction"];
				} else {
					$query .= " ORDER BY group_sort_field ".$view["options"]["ot_sort_direction"];
				}
			} else {
				$query .= " ORDER BY group_field";
			}
			$q = sqlquery($query);
			
			// If there's another table, we're going to query it separately.
			if ($view["options"]["other_table"] && !$view["options"]["group_parser"]) {
				$otq = array();
				while ($f = sqlfetch($q)) {
					$otq[] = "id = '".$f["group_field"]."'";
					// We need to instatiate all of these as empty first in case the database relationship doesn't exist.
					$groups[$f["group_field"]] = "";
				}
				if (count($otq)) {
					if ($view["options"]["ot_sort_field"]) {
						$otsf = $view["options"]["ot_sort_field"];
						if ($view["options"]["ot_sort_direction"]) {
							$otsd = $view["options"]["ot_sort_direction"];
						} else {
							$otsd = "ASC";
						}
					} else {
						$otsf = "id";
						$otsd = "ASC";
					}
					$q = sqlquery("SELECT id,`".$view["options"]["title_field"]."` AS `title` FROM `".$view["options"]["other_table"]."` WHERE ".implode(" OR ",$otq)." ORDER BY $otsf $otsd");
					while ($f = sqlfetch($q)) {
						$groups[$f["id"]] = $f["title"];
					}
				}
			} else {
				while ($f = sqlfetch($q)) {
					$groups[$f["group_field"]] = $f["group_field"];			
				}
			}
			
			return $groups;
		}

		/*
			Function: getItem
				Returns an entry from a table with all its related information.
				If a pending ID is passed in (prefixed with a p) getPendingItem is called instead.

			Parameters:
				table - The table to pull the entry from.
				id - The id of the entry.

			Returns:
				An array with the following key/value pairs:
				"item" - The entry from the table with pending changes already applied.
				"tags" - A list of tags for the entry.
				
				Returns false if the entry could not be found.
		*/

		static function getItem($table,$id) {
			global $cms;

			// The entry is pending if there's a "p" prefix on the id
			if (substr($id,0,1) == "p") {
				return self::getPendingItem($table,$id);
			}
			// Otherwise it's a live entry
			$item = sqlfetch(sqlquery("SELECT * FROM `$table` WHERE id = '$id'"));
			if (!$item) {
				return false;
			}
			$tags = self::getTagsForEntry($table,$id);

			// Process the internal page links, turn json_encoded arrays into arrays.
			foreach ($item as $key => $val) {
				if (is_array(json_decode($val,true))) {
					$item[$key] = BigTree::untranslateArray(json_decode($val,true));
				} else {
					$item[$key] = $cms->replaceInternalPageLinks($val);
				}
			}
			return array("item" => $item, "tags" => $tags);
		}
		
		/*
			Function: getModuleForForm
				Returns the associated module id for the given form.
			
			Parameters:
				form - Either a form entry or form id.
			
			Returns:
				The id of the module the form is a member of.
		*/
		
		static function getModuleForForm($form) {
			if (is_array($form)) {
				$form = $form["id"];
			}
			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_module_actions WHERE form = '$form'"));
			return $f["module"];
		}
		
		/*
			Function: getModuleForView
				Returns the associated module id for the given view.
			
			Parameters:
				view - Either a view entry or view id.
			
			Returns:
				The id of the module the view is a member of.
		*/

		static function getModuleForView($view) {
			if (is_array($view)) {
				$view = $view["id"];
			}
			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_module_actions WHERE view = '$view'"));
			return $f["module"];
		}
		
		/*
			Function: getPendingItem
				Gets an entry from a table with all its related information and pending changes applied.
			
			Parameters:
				table - The table to pull the entry from.
				id - The id of the entry.
			
			Returns:
				An array with the following key/value pairs:
				"item" - The entry from the table with pending changes already applied.
				"mtm" - A list of many to many pending changes.
				"tags" - A list of tags for the entry.
				"status" - Whether the item is pending ("pending"), published ("published"), or has changes ("updated") awaiting publish.
				
				Returns false if the entry could not be found.
		*/

		static function getPendingItem($table,$id) {
			global $cms,$module;
			$status = "published";
			$many_to_many = array();
			$resources = array();
			$owner = false;
			// The entry is pending if there's a "p" prefix on the id
			if (substr($id,0,1) == "p") {
				$change = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE id = '".substr($id,1)."'"));
				if (!$change) {
					return false;
				}
				
				$item = json_decode($change["changes"],true);
				$many_to_many = json_decode($change["mtm_changes"],true);
				$temp_tags = json_decode($change["tags_changes"],true);
				$tags = array();
				if (!empty($temp_tags)) {
					foreach ($temp_tags as $tid) {
						$tags[] = sqlfetch(sqlquery("SELECT * FROM bigtree_tags WHERE id = '$tid'"));
					}
				}
				$status = "pending";
				$owner = $change["user"];
			// Otherwise it's a live entry
			} else {
				$item = sqlfetch(sqlquery("SELECT * FROM `$table` WHERE id = '$id'"));
				if (!$item) {
					return false;
				}
				
				// Apply changes that are pending
				$change = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE `table` = '$table' AND `item_id` = '$id'"));
				if ($change) {
					$status = "updated";
					$changes = json_decode($change["changes"],true);
					foreach ($changes as $key => $val) {
						$item[$key] = $val;
					}
					$many_to_many = json_decode($change["mtm_changes"],true);
					$temp_tags = json_decode($change["tags_changes"],true);
					$tags = array();
					if (is_array($temp_tags)) {
						foreach ($temp_tags as $tid) {
							$tags[] = sqlfetch(sqlquery("SELECT * FROM bigtree_tags WHERE id = '$tid'"));
						}
					}
				// If there's no pending changes, just pull the tags
				} else {
					$tags = self::getTagsForEntry($table,$id);
				}
			}
			
			// Process the internal page links, turn json_encoded arrays into arrays.
			foreach ($item as $key => $val) {
				if (is_array($val)) {
					$item[$key] = BigTree::untranslateArray($val);
				} elseif (is_array(json_decode($val,true))) {
					$item[$key] = BigTree::untranslateArray(json_decode($val,true));
				} else {
					$item[$key] = $cms->replaceInternalPageLinks($val);
				}
			}
			return array("item" => $item, "mtm" => $many_to_many, "tags" => $tags, "status" => $status, "owner" => $owner);
		}

		/*
			Function: getRelatedFormForReport
				Returns the form for the same table as the given report.
			
			Parameters:
				report - A report entry.
			
			Returns:
				A form entry with fields decoded.
		*/

		static function getRelatedFormForReport($report) {
			$f = sqlfetch(sqlquery("SELECT id FROM bigtree_module_forms WHERE `table` = '".sqlescape($report["table"])."'"));
			return self::getForm($f["id"]);
		}
		
		/*
			Function: getRelatedFormForView
				Returns the form for the same table as the given view.
			
			Parameters:
				view - A view entry.
			
			Returns:
				A form entry with fields decoded.
		*/

		static function getRelatedFormForView($view) {
			if ($view["related_form"]) {
				return self::getForm($view["related_form"]);
			}
			$f = sqlfetch(sqlquery("SELECT id FROM bigtree_module_forms WHERE `table` = '".sqlescape($view["table"])."'"));
			return self::getForm($f["id"]);
		}
		
		/*
			Function: getRelatedViewForForm
				Returns the view for the same table as the given form.
			
			Parameters:
				form - A form entry.
			
			Returns:
				A view entry.
		*/

		static function getRelatedViewForForm($form) {
			// Try to find a view that's relating back to this form first
			$f = sqlfetch(sqlquery("SELECT id FROM bigtree_module_views WHERE `related_form` = '".$form["id"]."'"));
			// Fall back to any view that uses the same table
			if (!$f) {
				$f = sqlfetch(sqlquery("SELECT id FROM bigtree_module_views WHERE `table` = '".sqlescape($form["table"])."'"));
			}
			return self::getView($f["id"]);
		}

		/*
			Function: getRelatedViewForReport
				Returns the view for the same table as the given report.
			
			Parameters:
				report - A report entry.
			
			Returns:
				A view entry.
		*/

		static function getRelatedViewForReport($report) {
			$f = sqlfetch(sqlquery("SELECT id FROM bigtree_module_views WHERE `table` = '".sqlescape($report["table"])."'"));
			return self::getView($f["id"]);
		}

		/*
			Function: getReport
				Returns a report with the filters and fields decoded.

			Parameters:
				id - The ID of the report

			Returns:
				An array of report information.
		*/

		static function getReport($id) {
			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_module_reports WHERE id = '".sqlescape($id)."'"));
			$f["fields"] = json_decode($f["fields"],true);
			$f["filters"] = json_decode($f["filters"],true);
			return $f;
		}

		/*
			Function: getReportResults
				Returns rows from the table that match the filters provided.

			Parameters:
				report - A bigtree_module_reports entry.
				view - A bigtree_module_views entry.
				form - A bigtree_module_forms entry.
				filters - The submitted filters to run.
				sort_field - The field to sort by.
				sort_direction - The direction to sort by.

			Returns:
				An array of entries from the report's table.
		*/

		static function getReportResults($report,$view,$form,$filters,$sort_field = "id",$sort_direction = "DESC") {
			$where = $items = $parsers = $poplists = array();
			// Figure out if we have db populated lists and parsers
			if ($report["type"] == "view") {
				foreach ($view["fields"] as $key => $field) {
					if ($field["parser"]) {
						$parsers[$key] = $field["parser"];
					}
				}
			}
			foreach ($form["fields"] as $key => $field) {
				if ($field["type"] == "list" && $field["list_type"] == "db") {
					$poplists[$key] = array("description" => $form["fields"][$key]["pop-description"], "table" => $form["fields"][$key]["pop-table"]);
				}
			}

			$query = "SELECT * FROM `".$report["table"]."`";
			foreach ($report["filters"] as $id => $filter) {
				if ($filters[$id]) {
					// Search field
					if ($filter["type"] == "search") {
						$where[] = "`$id` LIKE '%".sqlescape($filters[$id])."%'";
					// Dropdown
					} elseif ($filter["type"] == "dropdown") {
						$where[] = "`$id` = '".sqlescape($filters[$id])."'";
					// Yes / No / Both
					} elseif ($filter["type"] == "boolean") {
						if ($filters[$id] == "Yes") {
							$where[] = "(`$id` = 'on' OR `$id` = '1' OR `$id` != '')";
						} elseif ($filters[$id] == "No") {
							$where[] = "(`$id` = '' OR `$id` = '0' OR `$id` IS NULL)";
						}
					// Date Range
					} elseif ($filter["type"] == "date-range") {
						if ($filter[$id]["start"]) {
							$where[] = "`$id` >= '".sqlescape($filter[$id]["start"])."'";
						}
						if ($filter[$id]["end"]) {
							$where[] = "`$id` <= '".sqlescape($filter[$id]["end"])."'";
						}
					}
				}
			}

			if (count($where)) {
				$query .= " WHERE ".implode(" AND ",$where);
			}

			$q = sqlquery($query." ORDER BY $sort_field $sort_direction");
			while ($f = sqlfetch($q)) {
				$item = BigTree::untranslateArray($f);
				foreach ($item as $key => $value) {
					if ($poplists[$key]) {
						$p = sqlfetch(sqlquery("SELECT `".$poplists[$key]["description"]."` FROM `".$poplists[$key]["table"]."` WHERE id = '".sqlescape($value)."'"));
						$item[$key] = $p[$poplists[$key]["description"]];
					}
					if ($parsers[$key]) {
						$item[$key] = BigTree::runParser($item,$value,$parsers[$key]);
					}
				}
				$items[] = $item;
			}

			// If the field we sort by was a poplist or parser, we need to resort.
			if (isset($parsers[$sort_field]) || isset($poplists[$sort_field])) {
				$sort_values = array();
				foreach ($items as $item) {
					$sort_values[] = $item[$sort_field];
				}
				if ($sort_direction == "ASC") {
					array_multisort($sort_values,SORT_ASC,$items);
				} else {
					array_multisort($sort_values,SORT_DESC,$items);
				}
			}

			return $items;
		}
		
		/*
			Function: getSearchResults
				Returns results from the bigtree_module_view_cache table.
		
			Parameters:
				view - The view to pull data for.
				page - The page of data to retrieve.
				query - The query string to search against.
				sort - The column and direction to sort.
				group - The group to pull information for.
		
			Returns:
				An array containing "pages" with the number of result pages and "results" with the results for the given page.
		*/
		
		static function getSearchResults($view,$page = 1,$query = "",$sort = "id DESC",$group = false) {
			global $last_query,$admin;
			if (!$admin) {
				$admin = new BigTreeAdmin;
			}

			// We're going to read the original table so we know whether the column we're sorting by is numeric.
			$tableInfo = BigTree::describeTable($view["table"]);
			
			// Check to see if we've cached this table before.
			self::cacheViewData($view);
			
			$search_parts = explode(" ",strtolower($query));
			$view_columns = count($view["fields"]);
			
			if ($group !== false) {
				$query = "SELECT * FROM bigtree_module_view_cache WHERE view = '".$view["id"]."' AND group_field = '".sqlescape($group)."'".self::getFilterQuery($view);				
			} else {
				$query = "SELECT * FROM bigtree_module_view_cache WHERE view = '".$view["id"]."'".self::getFilterQuery($view);
			}
			
			foreach ($search_parts as $part) {
				$x = 0;
				$qp = array();
				$part = sqlescape(strtolower($part));
				while ($x < $view_columns) {
					$x++;
					$qp[] = "column$x LIKE '%$part%'";
				}
				if (count($qp)) {
					$query .= " AND (".implode(" OR ",$qp).")";
				}
			}
			
			$per_page = $view["options"]["per_page"] ? $view["options"]["per_page"] : $admin->PerPage;
			$pages = ceil(sqlrows(sqlquery($query)) / $per_page);
			$pages = ($pages > 0) ? $pages : 1;
			$results = array();
			
			// Get the correct column name for sorting
			if (strpos($sort,"`") !== false) { // New formatting
				$sort_field = BigTree::nextSQLColumnDefinition(substr($sort,1));
				$sort_pieces = explode(" ",$sort);
				$sort_direction = end($sort_pieces);
			} else { // Old formatting
				list($sort_field,$sort_direction) = explode(" ",$sort);
			}

			if ($sort_field != "id") {
				$x = 0;
				if (isset($view["fields"][$sort_field]["numeric"]) && $view["fields"][$sort_field]["numeric"]) {
					$convert_numeric = true;
				} else {
					$convert_numeric = false;
				}
				foreach ($view["fields"] as $field => $options) {
					$x++;
					if ($field == $sort_field) {
						$sort_field = "column$x";
					}
				}
				// If we didn't find a column, let's assume it's the default sort field.
				if (substr($sort_field,0,6) != "column") {
					$sort_field = "sort_field";
				}
				if ($convert_numeric) {
					$sort_field = "CONVERT(".$sort_field.",SIGNED)";
				}
			} else {
				$sort_field = "CONVERT(id,UNSIGNED)";
			}

			if (strtolower($sort) == "position desc, id asc") {
				$sort_field = "position DESC, id ASC";
				$sort_direction = "";
			}
			
			if ($page === "all") {
				$q = sqlquery($query." ORDER BY $sort_field $sort_direction");
			} else {
				$q = sqlquery($query." ORDER BY $sort_field $sort_direction LIMIT ".(($page - 1) * $per_page).",$per_page");
			}
			
			while ($f = sqlfetch($q)) {
				unset($f["hash"]);
				$results[] = $f;
			}

			return array("pages" => $pages, "results" => $results);
		}
		
		/*
			Function: getTagsForEntry
				Returns the tags for an entry.
				
			Parameters:
				table - The table the entry is in.
				id - The id of the entry.
			
			Returns:
				An array ot tags from bigtree_tags.
		*/
		
		static function getTagsForEntry($table,$id) {
			$tags = array();
			$q = sqlquery("SELECT bigtree_tags.* FROM bigtree_tags JOIN bigtree_tags_rel ON bigtree_tags_rel.tag = bigtree_tags.id WHERE bigtree_tags_rel.`table` = '".sqlescape($table)."' AND bigtree_tags_rel.entry = '$id' ORDER BY bigtree_tags.tag ASC");
			while ($f = sqlfetch($q)) {
				$tags[] = $f;
			}
			return $tags;
		}
		
		/*
			Function: getView
				Returns a view.
			
			Parameters:
				id - The id of the view.
				decode_ipl - Whether we want to decode internal page link on the preview url (defaults to true)
				
			Returns:
				A view entry with actions, options, and fields decoded.  fields also receive a width column for the view.
		*/

		static function getView($id,$decode_ipl = true) {
			global $cms;
			
			if (is_array($id)) {
				$id = $id["id"];
			}
			
			$view = sqlfetch(sqlquery("SELECT * FROM bigtree_module_views WHERE id = '$id'"));
			if (!$view) {
				return false;
			}

			// We may be in AJAX, so we need to define MODULE_ROOT if it's not available
			if (!defined("MODULE_ROOT")) {
				$module = sqlfetch(sqlquery("SELECT route FROM bigtree_modules WHERE id = '".$view["module"]."'"));
				define("MODULE_ROOT",ADMIN_ROOT.$module["route"]."/");
			}
			
			$view["actions"] = json_decode($view["actions"],true);
			$view["options"] = json_decode($view["options"],true);
			if ($decode_ipl) {
				$view["preview_url"] = $cms->replaceInternalPageLinks($view["preview_url"]);
			}

			// Get the edit link
			if (isset($view["actions"]["edit"])) {
				if ($view["related_form"]) {
					// Try for actions beginning with edit first
					$f = sqlfetch(sqlquery("SELECT * FROM bigtree_module_actions WHERE form = '".$view["related_form"]."' AND route LIKE 'edit%'"));
					if (!$f) {
						// Try any action with this form
						$f = sqlfetch(sqlquery("SELECT * FROM bigtree_module_actions WHERE form = '".$view["related_form"]."'"));
					}
					$view["edit_url"] = MODULE_ROOT.$f["route"]."/";
				} else {
					$view["edit_url"] = MODULE_ROOT."edit/";
				}
			}
			
			$actions = $view["preview_url"] ? ($view["actions"] + array("preview" => "on")) : $view["actions"];
			$fields = json_decode($view["fields"],true);
			if (count($fields)) {
				$first = current($fields);
				if (!isset($first["width"]) || !$first["width"]) {
					$awidth = count($actions) * 40;
					$available = 888 - $awidth;
					$percol = floor($available / count($fields));
				
					foreach ($fields as $key => $field) {
						$fields[$key]["width"] = $percol - 20;
					}
				}
				$view["fields"] = $fields;
			} else {
				$view["fields"] = array();
			}

			return $view;
		}
		
		/*
			Function: getViewData
				Gets a list of data for a view.
			
			Parameters:
				view - The view entry to pull data for.
				sort - The sort direction, defaults to most recent.
				type - Whether to get only active entries, pending entries, or both.
			
			Returns:
				An array of items from bigtree_module_view_cache.
		*/
		
		static function getViewData($view,$sort = "id DESC",$type = "both") {
			// Check to see if we've cached this table before.
			self::cacheViewData($view);
			
			$items = array();
			if ($type == "both") {
				$q = sqlquery("SELECT * FROM bigtree_module_view_cache WHERE view = '".$view["id"]."'".self::getFilterQuery($view)." ORDER BY $sort");
			} elseif ($type == "active") {
				$q = sqlquery("SELECT * FROM bigtree_module_view_cache WHERE status != 'p' AND view = '".$view["id"]."'".self::getFilterQuery($view)." ORDER BY $sort");	
			} elseif ($type == "pending") {
				$q = sqlquery("SELECT * FROM bigtree_module_view_cache WHERE status = 'p' AND view = '".$view["id"]."'".self::getFilterQuery($view)." ORDER BY $sort");				
			}
			
			while ($f = sqlfetch($q)) {
				$items[$f["id"]] = $f;
			}
			
			return $items;
		}
		
		/*
			Function: getViewDataForGroup
				Gets a list of data for a view in a given group.
			
			Parameters:
				view - The view entry to pull data for.
				group - The group to get data for.
				sort - The sort direction, defaults to most recent.
				type - Whether to get only active entries, pending entries, or both.
			
			Returns:
				An array of items from bigtree_module_view_cache.
		*/
		
		static function getViewDataForGroup($view,$group,$sort,$type = "both") {
			// Check to see if we've cached this table before.
			self::cacheViewData($view);
			
			$items = array();
			if ($type == "both") {
				$q = sqlquery("SELECT * FROM bigtree_module_view_cache WHERE group_field = '".sqlescape($group)."' AND view = '".$view["id"]."'".self::getFilterQuery($view)." ORDER BY $sort");
			} elseif ($type == "active") {
				$q = sqlquery("SELECT * FROM bigtree_module_view_cache WHERE group_field = '".sqlescape($group)."' AND status != 'p' AND view = '".$view["id"]."'".self::getFilterQuery($view)." ORDER BY $sort");
			} elseif ($type == "pending") {
				$q = sqlquery("SELECT * FROM bigtree_module_view_cache WHERE group_field = '".sqlescape($group)."' AND status = 'p' AND view = '".$view["id"]."'".self::getFilterQuery($view)." ORDER BY $sort");
			}
			
			while ($f = sqlfetch($q)) {
				$items[] = $f;
			}
			
			return $items;
		}
		
		/*
			Function: getViewForTableChanges
				Gets a view for a given table for showing change lists in Pending Changes.
			
			Parameters:
				table - Table name.
			
			Returns:
				A view entry with options, and fields decoded and field widths set for Pending Changes.
		*/
		
		static function getViewForTable($table) {
			global $cms;
			
			$table = sqlescape($table);
			$view = sqlfetch(sqlquery("SELECT * FROM bigtree_module_views WHERE `table` = '$table'"));
			if (!$view) {
				return false;
			}
			$view["options"] = json_decode($view["options"],true);
			$view["preview_url"] = $cms->replaceInternalPageLinks($view["preview_url"]);
			
			$fields = json_decode($view["fields"],true);
			if (is_array($fields)) {
				// Three or four actions, depending on preview availability.
				if ($view["preview_url"]) {
					$available = 578;
				} else {
					$available = 633;
				}
				$percol = floor($available / count($fields));
				foreach ($fields as $key => $field) {
					$fields[$key]["width"] = $percol - 20;
				}
				$view["fields"] = $fields;
			}

			return $view;
		}
		
		/*
			Function: parseViewData
				Parses data and returns the parsed columns (runs parsers and populated lists).
			
			Parameters:
				view - The view to parse items for.
				items - An array of entries to parse.
			
			Returns:
				An array of parsed entries.
		*/

		static function parseViewData($view,$items) {
			$form = self::getRelatedFormForView($view);
			$parsed = array();
			foreach ($items as $item) {
				if (is_array($view["fields"])) {
					foreach ($view["fields"] as $key => $field) {
						$value = $item[$key];
						// If we have a parser, run it.
						if ($field["parser"]) {
							$item[$key] = BigTree::runParser($item,$value,$field["parser"]);
						// If we know this field is a populated list, get the title they entered in the form.
						} else {
							if ($form["fields"][$key]["type"] == "list" && $form["fields"][$key]["list_type"] == "db") {
								$fdata = $form["fields"][$key];
								$f = sqlfetch(sqlquery("SELECT `".$fdata["pop-description"]."` FROM `".$fdata["pop-table"]."` WHERE `id` = '".sqlescape($value)."'"));
								$value = $f[$fdata["pop-description"]];
							}
							
							$item[$key] = strip_tags($value);
						}
					}
				}
				$parsed[] = $item;
			}
			return $parsed;
		}

		/*
			Function: publishPendingItem
				Publishes a pending item and caches it.
				
			Parameters:
				table - The table to store the entry in.
				id - The id of the pending entry.
				data - The form data to create an entry with.
				many_to_many - Many to Many information
				tags - Tag information
			
			Returns:
				The id of the new entry.
		*/
		
		static function publishPendingItem($table,$id,$data,$many_to_many = array(),$tags = array()) {
			self::deletePendingItem($table,$id);
			self::createItem($table,$data,$many_to_many,$tags);
		}
		
		/*
			Function: recacheItem
				Re-caches a database entry.
			
			Parameters:
				id - The id of the entry.
				table - The table the entry is in.
				pending - Whether the entry is pending or not.
			
			See Also:
				<cacheNewItem>
		*/
		
		static function recacheItem($id,$table,$pending = false) {
			self::cacheNewItem($id,$table,$pending,true);
		}

		/*
			Function: sanitizeData
				Processes form data into values understandable by the MySQL table.
			
			Parameters:
				table - The table to sanitize data for
				data - Array of key->value pairs
				existing_description - If the table has already been described, pass it in instead of making sanitizeData do it twice. (defaults to false)
			
			Returns:
				Array of data safe for MySQL.
		*/	
		
		static function sanitizeData($table,$data,$existing_description = false) {
			// Setup column info				
			$table_description = $existing_description ? $existing_description : BigTree::describeTable($table);
			$columns = $table_description["columns"];

			foreach ($data as $key => $val) {
				$allow_null = $columns[$key]["allow_null"];
				$type = $columns[$key]["type"];

				// Sanitize Integers
				if ($type == "tinyint" || $type == "smallint" || $type == "mediumint" || $type == "int" || $type == "bigint") {
					if ($val !== 0 && !$val && $allow_null == "YES") {
						$data[$key] = "NULL";	
					} else {
						$data[$key] = intval(str_replace(array(",","$"),"",$val));
					}
				}
				// Sanitize Floats
				if ($type == "float" || $type == "double" || $type == "decimal") {
					if ($val !== 0 && !$val && $allow_null == "YES") {
						$data[$key] = "NULL";	
					} else {
						$data[$key] = floatval(str_replace(array(",","$"),"",$val));
					}
				}
				// Sanitize Date/Times
				if ($type == "datetime" || $type == "timestamp") {
					if (substr($val,0,3) == "NOW") {
						$data[$key] = "NOW()";
					} elseif (!$val && $allow_null == "YES") {
						$data[$key] = "NULL";
					} elseif ($val == "") {
						$data[$key] = "0000-00-00 00:00:00";
					} else {
						$data[$key] = date("Y-m-d H:i:s",strtotime($val));
					}
				}
				// Sanitize Dates/Years
				if ($type == "date" || $type == "year") {
					if (substr($val,0,3) == "NOW") {
						$data[$key] = "NOW()";
					} elseif (!$val && $allow_null == "YES") {
						$data[$key] = "NULL";
					} elseif (!$val) {
						$data[$key] = "0000-00-00";
					} else {
						$data[$key] = date("Y-m-d",strtotime($val));
					}
				}
				// Sanitize Times
				if ($type == "time") {
					if (substr($val,0,3) == "NOW") {
						$data[$key] = "NOW()";
					} elseif (!$val && $allow_null == "YES") {
						$data[$key] = "NULL";
					} elseif (!$val) {
						$data[$key] = "00:00:00";
					} else {
						$data[$key] = date("H:i:s",strtotime($val));
					}
				}
			}
			return $data;
		}

		/*
			Function: submitChange
				Creates a change request for an item and caches it.
			
			Parameters:
				module - The module for the entry.
				table - The table the entry is stored in.
				id - The id of the entry.
				data - The change request data.
				many_to_many - The many to many changes.
				tags - The tag changes.
			
			Returns:
				The id of the pending change.
		*/
		
		static function submitChange($module,$table,$id,$data,$many_to_many = array(),$tags = array()) {
			global $admin;

			$original = sqlfetch(sqlquery("SELECT * FROM `$table` WHERE id = '$id'"));
			foreach ($data as $key => $val) {
				if ($val === "NULL") {
					$data[$key] = "";
				}
				if ($original && $original[$key] === $val) {
					unset($data[$key]);
				}
			}
			$changes = sqlescape(json_encode($data));
			$many_data = sqlescape(json_encode($many_to_many));
			$tags_data = sqlescape(json_encode($tags));

			// Find out if there's already a change waiting
			if (substr($id,0,1) == "p") {
				$existing = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE id = '".substr($id,1)."'"));
			} else {
				$existing = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE `table` = '$table' AND item_id = '$id'"));
			}
			if ($existing) {
				$comments = json_decode($existing["comments"],true);
				if ($existing["user"] == $admin->ID) {
					$comments[] = array(
						"user" => "BigTree",
						"date" => date("F j, Y @ g:ia"),
						"comment" => "A new revision has been made."
					);
				} else {
					$user = $admin->getUser($admin->ID);
					$comments[] = array(
						"user" => "BigTree",
						"date" => date("F j, Y @ g:ia"),
						"comment" => "A new revision has been made.  Owner switched to ".$user["name"]."."
					);
				}
				$comments = sqlescape(json_encode($comments));
				sqlquery("UPDATE bigtree_pending_changes SET comments = '$comments', changes = '$changes', mtm_changes = '$many_data', tags_changes = '$tags_data', date = NOW(), user = '".$admin->ID."', type = 'EDIT' WHERE id = '".$existing["id"]."'");
				
				// If the id has a "p" it's still pending and we need to recache over the pending one.
				if (substr($id,0,1) == "p") {
					self::recacheItem(substr($id,1),$table,true);
				} else {
					self::recacheItem($id,$table);					
				}
				
				if ($admin) {
					$admin->track($table,$id,"updated-draft");
				}
				
				return $existing["id"];
			} else {
				sqlquery("INSERT INTO bigtree_pending_changes (`user`,`date`,`table`,`item_id`,`changes`,`mtm_changes`,`tags_changes`,`module`,`type`) VALUES ('".$admin->ID."',NOW(),'$table','$id','$changes','$many_data','$tags_data','$module','EDIT')");
				self::recacheItem($id,$table);
				
				if ($admin) {
					$admin->track($table,$id,"saved-draft");
				}
				return sqlid();
			}
		}
		
		/*
			Function: uncacheItem
				Removes a database entry from the view cache.
			
			Parameters:
				id - The id of the entry.
				table - The table the entry is in.
		*/
		
		static function uncacheItem($id,$table) {
			$q = sqlquery("SELECT * FROM bigtree_module_views WHERE `table` = '$table'");
			while ($view = sqlfetch($q)) {
				sqlquery("DELETE FROM bigtree_module_view_cache WHERE `view` = '".$view["id"]."' AND id = '$id'");
			}
		}

		/*
			Function: updateItem
				Update an entry and cache it.
			
			Parameters:
				table - The table the entry is in.
				id - The id of the entry.
				data - The data to update in the entry.
				many_to_many - Many To Many information
				tags - Tag information.
		*/
		
		static function updateItem($table,$id,$data,$many_to_many = array(),$tags = array()) {
			global $admin,$module;
			$table_description = BigTree::describeTable($table);
			$query = "UPDATE `$table` SET ";
			foreach ($data as $key => $val) {
				if (array_key_exists($key,$table_description["columns"])) {
					if ($val === "NULL" || $val == "NOW()") {
						$query .= "`$key` = $val,";
					} else {
						if (is_array($val)) {
							$val = json_encode(BigTree::translateArray($val));
						}
						$query .= "`$key` = '".sqlescape($val)."',";
					}
				}
			}
			$query = rtrim($query,",")." WHERE id = '$id'";
			sqlquery($query);

			// Handle many to many
			if (!empty($many_to_many)) {
				foreach ($many_to_many as $mtm) {
					sqlquery("DELETE FROM `".$mtm["table"]."` WHERE `".$mtm["my-id"]."` = '$id'");
					$table_description = BigTree::describeTable($mtm["table"]);
					if (is_array($mtm["data"])) {
						$x = count($mtm["data"]);
						foreach ($mtm["data"] as $item) {
							if (isset($table_description["columns"]["position"])) {
								sqlquery("INSERT INTO `".$mtm["table"]."` (`".$mtm["my-id"]."`,`".$mtm["other-id"]."`,`position`) VALUES ('$id','$item','$x')");
							} else {
								sqlquery("INSERT INTO `".$mtm["table"]."` (`".$mtm["my-id"]."`,`".$mtm["other-id"]."`) VALUES ('$id','$item')");
							}
							$x--;
						}
					}
				}
			}

			// Handle the tags
			sqlquery("DELETE FROM bigtree_tags_rel WHERE `table` = '".sqlescape($table)."' AND entry = '$id'");
			if (!empty($tags)) {
				foreach ($tags as $tag) {
					sqlquery("DELETE FROM bigtree_tags_rel WHERE `table` = '".sqlescape($table)."' AND entry = $id AND tag = $tag");
					sqlquery("INSERT INTO bigtree_tags_rel (`table`,`entry`,`tag`) VALUES ('".sqlescape($table)."',$id,$tag)");
				}
			}
			
			// Clear out any pending changes.
			sqlquery("DELETE FROM bigtree_pending_changes WHERE item_id = '$id' AND `table` = '$table'");
			
			if ($table != "bigtree_pages") {
				self::recacheItem($id,$table);
			}
			
			if ($admin) {
				$admin->track($table,$id,"updated");
			}
		}

		/*
			Function: updatePendingItemField
				Update a pending item's field with a given value.
			
			Parameters:
				id - The id of the entry.
				field - The field to change.
				value - The value to set.
		*/
		
		static function updatePendingItemField($id,$field,$value) {
			$id = sqlescape($id);
			$item = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE id = '$id'"));
			$changes = json_decode($item["changes"],true);
			if (is_array($value)) {
				$value = BigTree::translateArray($value);
			}
			$changes[$field] = $value;
			$changes = sqlescape(json_encode($changes));
			sqlquery("UPDATE bigtree_pending_changes SET changes = '$changes' WHERE id = '$id'");
		}

		/*
			Function: validate
				Validates a form element based on its validation requirements.
			
			Parameters:
				data - The form's posted data for a given field.
				type - Validation requirements (required, numeric, email, link).
		
			Returns:
				True if validation passed, otherwise false.
			
			See Also:
				<errorMessage>
		*/
		
		static function validate($data,$type) {
			$parts = explode(" ",$type);
			// Not required and it's blank
			if (!in_array("required",$parts) && !$data) {
				return true;
			} else {
				// Requires numeric and it isn't
				if (in_array("numeric",$parts) && !is_numeric($data)) {
					return false;
				// Requires email and it isn't
				} elseif (in_array("email",$parts) && !filter_var($data,FILTER_VALIDATE_EMAIL)) {
					return false;
				// Requires url and it isn't
				} elseif (in_array("link",$parts) && !filter_var($data,FILTER_VALIDATE_URL)) {
					return false;
				} elseif (in_array("required",$parts) && !$data) {
					return false;
				// It exists and validates as numeric, an email, or URL
				} else {
					return true;
				}
			}
		}

		/*
			Function: validationErrorMessage
				Returns an error message for a form element that failed validation.
			
			Parameters:
				data - The form's posted data for a given field.
				type - Validation requirements (required, numeric, email, link).
		
			Returns:
				A string containing reasons the validation failed.
				
			See Also:
				<validate>
		*/
		
		static function validationErrorMessage($data,$type) {
			$parts = explode(" ",$type);
			// Not required and it's blank
			$message = "This field ";
			$mparts = array();
			
			if (!$data && in_array("required",$parts)) {
				$mparts[] = "is required";
			}
			
			// Requires numeric and it isn't
			if (in_array("numeric",$parts) && !is_numeric($data)) {
				$mparts[] = "must be numeric";
			// Requires email and it isn't
			} elseif (in_array("email",$parts) && !filter_var($data,FILTER_VALIDATE_EMAIL)) {
				$mparts[] = "must be an email address";
			// Requires url and it isn't
			} elseif (in_array("link",$parts) && !filter_var($data,FILTER_VALIDATE_URL)) {
				$mparts[] = "must be a link";
			}
			
			$message .= implode(" and ",$mparts).".";
			
			return $message;
		}
	}
?>
