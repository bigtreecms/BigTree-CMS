<?php
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
		
		public static function cacheNewItem($id,$table,$pending = false,$recache = false) {
			$id = sqlescape($id);

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

			$modules = BigTreeJSONDB::getAll("modules");

			foreach ($modules as $module) {
				if (is_array($module["views"])) {
					foreach ($module["views"] as $view) {
						if ($view["table"] == $table) {
							if ($recache) {
								sqlquery("DELETE FROM bigtree_module_view_cache WHERE `view` = '".$view["id"]."' AND id = '".$item["id"]."'");
							}
							
							$view["options"] = &$view["settings"]; // Backwards compatibility
							
							// In case this view has never been cached, run the whole view, otherwise just this one.
							if (!self::cacheViewData($view)) {
								// Find out what module we're using so we can get the gbp_field
								$view["gbp"] = $module["gbp"];
								
								$form = self::getRelatedFormForView($view);
								
								$parsers = array();
								$poplists = array();
								
								foreach ($view["fields"] as $key => $field) {
									if ($field["parser"]) {
										$parsers[$key] = $field["parser"];
									} elseif ($form["fields"][$key]["type"] == "list" && $form["fields"][$key]["settings"]["list_type"] == "db") {
										$poplists[$key] = array("description" => $form["fields"][$key]["settings"]["pop-description"], "table" => $form["fields"][$key]["settings"]["pop-table"]);
									}
								}
								
								self::cacheRecord($item,$view,$parsers,$poplists,$original_item);
							}
						}
					}
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
		
		public static function cacheRecord($item,$view,$parsers,$poplists,$original_item) {
			// If we have a filter function, ask it first if we should cache it
			if (isset($view["settings"]["filter"]) && $view["settings"]["filter"]) {
				if (!call_user_func($view["settings"]["filter"],$item)) {
					return false;
				}
			}

			// Stringify any columns that happen to be arrays (potentially from a pending record)
			foreach ($item as $key => $val) {
				if (is_array($val)) {
					$item[$key] = json_encode($val);
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
			} elseif (!empty($item["archived"]) || (isset($item["approved"]) && $item["approved"] != "on")) {
				$status = "i";
			}
			
			$fields = array("view","id","status","position","approved","archived","featured","pending_owner");

			// No more notices.
			$approved = isset($item["approved"]) ? $item["approved"] : "";
			$featured = isset($item["featured"]) ? $item["featured"] : "";
			$archived = isset($item["archived"]) ? $item["archived"] : "";
			$position = isset($item["position"]) ? $item["position"] : 0;

			$vals = array("'".$view["id"]."'","'".$item["id"]."'","'$status'","'$position'","'$approved'","'$archived'","'$featured'","'".$pending_owner."'");

			// Figure out which column we're going to use to sort the view.
			if ($view["settings"]["sort"]) {
				$sort_field = BigTree::nextSQLColumnDefinition(ltrim($view["settings"]["sort"],"`"));
			} else {
				$sort_field = false;
			}
			
			// Let's see if we have a grouping field.  If we do, let's get all that info and cache it as well.
			if (isset($view["settings"]["group_field"]) && $view["settings"]["group_field"]) {
				$value = $item[$view["settings"]["group_field"]];

				// Check for a parser
				if (isset($view["settings"]["group_parser"]) && $view["settings"]["group_parser"]) {
					$value = BigTree::runParser($item,$value,$view["settings"]["group_parser"]);
				}

				$fields[] = "group_field";
				$vals[] = "'".sqlescape($value)."'";
				
				if (is_numeric($value) && $view["settings"]["other_table"]) {
					$f = sqlfetch(sqlquery("SELECT * FROM `".$view["settings"]["other_table"]."` WHERE id = '$value'"));
					
					if ($view["settings"]["ot_sort_field"]) {
						$fields[] = "group_sort_field";
						$vals[] = "'".sqlescape($f[$view["settings"]["ot_sort_field"]])."'";
					}
				}
			}

			// Check for a nesting column
			if (isset($view["settings"]["nesting_column"]) && $view["settings"]["nesting_column"]) {
				$fields[] = "group_field";
				$vals[] = "'".sqlescape($item[$view["settings"]["nesting_column"]])."'";
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
				$vals[] = "'".$item[$view["settings"]["image"]]."'";
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
		
		public static function cacheViewData($view) {
			// See if we already have cached data.
			if (sqlrows(sqlquery("SELECT id FROM bigtree_module_view_cache WHERE view = '".$view["id"]."'"))) {
				return false;
			}
			
			// Find out what module we're using so we can get the gbp_field
			BigTreeJSONDB::get("modules", self::getModuleForView($view));
			$view["gbp"] = $module["gbp"];
			
			// Setup information on our parsers and populated lists.
			$form = self::getRelatedFormForView($view);
			$view["fields"] = is_array($view["fields"]) ? $view["fields"] : array();
			$parsers = array();
			$poplists = array();
			
			foreach ($view["fields"] as $key => $field) {
				// Get the form field
				$form_field = $form["fields"][$key];
				
				if ($field["parser"]) {
					$parsers[$key] = $field["parser"];
				} elseif ($form_field["type"] == "list" && $form_field["settings"]["list_type"] == "db") {
					$poplists[$key] = array("description" => $form_field["settings"]["pop-description"], "table" => $form_field["settings"]["pop-table"]);
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
			
			// Paginate out for high record counts to avoid out of memory errors
			$record_count = SQL::fetchSingle("SELECT COUNT(*) FROM `".$view["table"]."`");
			$total_pages = ceil($record_count / 1000);
			
			for ($page = 1; $page <= $total_pages; $page++) {
				$limit = ($page - 1) * 1000;
				
				// Cache all records that are published (and include their pending changes)
				$q = sqlquery("SELECT `".$view["table"]."`.*,bigtree_pending_changes.changes AS bigtree_changes FROM `".$view["table"]."` LEFT JOIN bigtree_pending_changes ON (bigtree_pending_changes.item_id = `".$view["table"]."`.id AND bigtree_pending_changes.table = '".$view["table"]."') ORDER BY `".$view["table"]."`.id ASC LIMIT $limit, 1000");
			
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

		public static function changeExists($table,$id) {
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
				entry - The view id or view entry to clear the cache for or a table to find all views for (and clear their caches).
		*/
		
		public static function clearCache($entry) {
			if (is_array($entry)) {
				SQL::query("DELETE FROM bigtree_module_view_cache WHERE view = ?", $entry["id"]);		
			} elseif (substr($entry, 0, 6) == "views-") {
				SQL::query("DELETE FROM bigtree_module_view_cache WHERE view = ?", $entry);
			} else {
				$modules = BigTreeJSONDB::getAll("modules");

				foreach ($modules as $module) {
					if (is_array($module["views"])) {
						foreach ($module["views"] as $view) {
							if ($view["table"] == $entry) {
								SQL::query("DELETE FROM bigtree_module_view_cache WHERE view = ?", $view["id"]);
							}
						}
					}
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
				publishing_change - A change ID that is being published (defaults to null)
				open_graph - Open Graph information.
			
			Returns:
				The id of the new entry in the database.
		*/

		public static function createItem($table, $data, $many_to_many = array(), $tags = array(), $publishing_change = null, $open_graph = array()) {	
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
			
			// Insert, if there's a failure return false instead of doing the rest
			$success = sqlquery("INSERT INTO `$table` (".implode(",",$query_fields).") VALUES (".implode(",",$query_vals).")");
			
			if (!$success) {
				return false;
			}

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
			
			if (is_array($tags) && count($tags)) {
				$tags = array_unique($tags);

				foreach ($tags as $tag) {
					$tag = intval($tag);

					sqlquery("DELETE FROM bigtree_tags_rel WHERE `table` = '".sqlescape($table)."' AND entry = $id AND tag = $tag");
					sqlquery("INSERT INTO bigtree_tags_rel (`table`,`entry`,`tag`) VALUES ('".sqlescape($table)."',$id,$tag)");
				}

				BigTreeAdmin::updateTagReferenceCounts($tags);
			}
			
			self::cacheNewItem($id,$table);

			// Handle Open Graph
			BigTreeAdmin::handleOpenGraph($table, $id, $open_graph);

			// Attribute this to the original pending change author if the data hasn't changed
			if ($publishing_change) {
				$change = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $publishing_change);

				if ($change) {
					$change_data = BigTree::untranslateArray(json_decode($change["changes"], true));
					$exact = true;

					foreach ($change_data as $key => $value) {
						if (isset($data[$key]) && $data[$key] != $value) {
							$exact = false;
						}
					}

					SQL::delete("bigtree_pending_changes", $publishing_change);
					self::uncacheItem("p".$publishing_change, $table);

					if ($exact) {
						self::track($table, $id, "created via publisher", $change["user"]);
						self::track($table, $id, "published");

						return $id;
					}
				}
			}

			self::track($table,$id,"created");

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
				publish_hook - A function to call when this change is published from the Dashboard.
				embedded_form - If this is being called from an embedded form, set the user to NULL (defaults to false)
				open_graph - Open Graph information.
			
			Returns:
				The id of the new entry in the bigtree_pending_changes table.
		*/

		public static function createPendingItem($module,$table,$data,$many_to_many = array(),$tags = array(),$publish_hook = null,$embedded_form = false,$open_graph = array()) {
			global $admin;
			
			foreach ($data as $key => $val) {
				if ($val === "NULL") {
					$data[$key] = "";
				}
				if (is_array($val)) {
					$data[$key] = BigTree::translateArray($val);
				}
			}

			$user = $embedded_form ? "NULL" : $admin->ID;
			$data = BigTree::json($data, true);
			$many_data = BigTree::json($many_to_many, true);
			$tags_data = BigTree::json(array_unique($tags) ?: [], true);
			$open_graph_data = BigTree::json($open_graph, true);
			$publish_hook = is_null($publish_hook) ? "NULL" : "'".sqlescape($publish_hook)."'";
			sqlquery("INSERT INTO bigtree_pending_changes (`user`,`date`,`table`,`changes`,`mtm_changes`,`tags_changes`,`open_graph_changes`,`module`,`type`,`publish_hook`) VALUES ($user,NOW(),'$table','$data','$many_data','$tags_data','$open_graph_data','$module','NEW',$publish_hook)");
			
			$id = sqlid();

			self::cacheNewItem($id,$table,true);
			self::track($table,"p$id","created-pending");
			
			return $id;
		}
		
		/*
			Function: deleteItem
				Deletes an item from the given table and removes any pending changes, then uncaches it from its views.
			
			Parameters:
				table - The table to delete an entry from.
				id - The id of the entry.
		*/

		public static function deleteItem($table, $id) {
			SQL::delete($table, $id);
			SQL::delete("bigtree_resource_allocation", ["table" => $table, "entry" => $id]);

			$pending_change = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE `table` = ? AND `item_id` = ?", $table, $id);

			if ($pending_change) {
				SQL::delete("bigtree_resource_allocation", ["table" => $table, "entry" => "p".$pending_change["id"]]);
				SQL::delete("bigtree_pending_changes", $pending_change["id"]);
			}

			self::uncacheItem($id, $table);
			self::track($table, $id, "deleted");
		}
		
		/*
			Function: deletePendingItem
				Deletes a pending item from bigtree_pending_changes and uncaches it.
			
			Parameters:
				table - The table the entry would have been in (should it have ever been published).
				id - The id of the pending entry.
		*/
		
		public static function deletePendingItem($table, $id) {
			$change = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $id);

			SQL::delete("bigtree_pending_changes", ["table" => $table, "id" => $id]);
			SQL::delete("bigtree_resource_allocation", ["table" => $table, "entry" => $change["item_id"] ?: "p".$change["id"]]);

			self::uncacheItem("p$id", $table);
			self::track($table,"p$id", "deleted-pending");
		}

		/*
			Function: getDependantViews
				Returns all views that have a dependance on a given table.

			Parameters:
				table - Table name

			Returns:
				An array of views from the modules database.
		*/

		public static function getDependantViews($table) {
			$dependent_views = [];
			$modules = BigTreeJSONDB::getAll("modules");

			foreach ($modules as $module) {
				if (is_array($module["views"])) {
					foreach ($module["views"] as $view) {
						if ($view["type"] == "grouped" || $view["type"] == "images-grouped") {
							if ($view["settings"]["other_table"] == $table) {
								$dependent_views[] = $view;
							}
						}
					}
				}
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

		public static function getEditAction($module, $form) {
			$context = BigTreeJSONDB::getSubset("modules", $module);
			$actions = $context->getAll("actions");

			foreach ($actions as $action) {
				if ($action["form"] == $form && substr($action["route"], 0, 4) == "edit") {
					return $action;
				}
			}

			return null;
		}

		/*
			Function: getEmbedForm
				Returns a module embeddable form.
			
			Parameters:
				id - The id of the form.
			
			Returns:
				An embeddable module form entry.
		*/

		public static function getEmbedForm($id) {
			if (is_array($id)) {
				$id = $id["id"];
			}

			$modules = BigTreeJSONDB::getAll("modules");

			foreach ($modules as $module) {
				if (is_array($module["embeddable-forms"])) {
					foreach ($module["embeddable-forms"] as $form) {
						if ($form["id"] == $id) {
							$form["module"] = $module["id"];

							return $form;
						}
					}
				}
			}

			return null;
		}

		/*
			Function: getEmbedFormByHash
				Returns a module embeddable form.
			
			Parameters:
				hash - The hash of the form.
			
			Returns:
				A module form entry with fields decoded.
		*/

		public static function getEmbedFormByHash($hash) {
			$modules = BigTreeJSONDB::getAll("modules");

			foreach ($modules as $module) {
				if (is_array($module["embeddable-forms"])) {
					foreach ($module["embeddable-forms"] as $form) {
						if ($form["hash"] == $hash) {
							return $form;
						}
					}
				}
			}

			return null;
		}
		
		/*
			Function: getFilterQuery
				Returns a query string that is used for searching views based on group permissions.
				Can only be called when logged into the admin.
			
			Parameters:
				view - The view to create a filter for.
			
			Returns:
				A set of MySQL statements that filter out information the user cannot access.
		*/
		
		public static function getFilterQuery($view) {
			global $admin;

			$module = BigTreeAdmin::getModule(self::getModuleForView($view));
			
			if (isset($module["gbp"]["enabled"]) && $module["gbp"]["enabled"] && $module["gbp"]["table"] == $view["table"]) {
				$groups = $admin->getAccessGroups($module["id"]);
				if (is_array($groups)) {
					$gfl = array();
					foreach ($groups as $g) {
						if ($view["type"] == "nested" && $module["gbp"]["group_field"] == $view["settings"]["nesting_column"]) {
							$gfl[] = "`id` = '".sqlescape($g)."' OR `gbp_field` = '".sqlescape($g)."'";
						} else {
							$gfl[] = "`gbp_field` = '".sqlescape($g)."'";
						}
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
				id - The id of the form or a form entry to generate backwards compatibility tweaks for.
			
			Returns:
				A module form entry.
		*/

		public static function getForm($id) {
			if (!is_array($id)) {
				$modules = BigTreeJSONDB::getAll("modules");
				$form = null;
	
				foreach ($modules as $module) {
					if (is_array($module["forms"])) {
						foreach ($module["forms"] as $module_form) {
							if ($module_form["id"] == $id) {
								$form = $module_form;
								$form["module"] = $module["id"];
							}
						}
					}
				}
	
				if (!$form) {
					return null;
				}
			} else {
				$form = $id;
			}

			// For backwards compatibility
			if (is_array($form["fields"])) {
				$related_fields = array();
				
				foreach ($form["fields"] as $field) {
					$related_fields[$field["column"]] = $field;
				}
				
				$form["fields"] = $related_fields;
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
		
		public static function getGroupsForView($view) {
			$groups = array();
			$query = "SELECT DISTINCT(group_field) FROM bigtree_module_view_cache WHERE view = '".$view["id"]."'";
			
			if (isset($view["settings"]["ot_sort_field"]) && $view["settings"]["ot_sort_field"]) {
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
					$query .= " ORDER BY CAST(group_sort_field AS DECIMAL) ".$view["settings"]["ot_sort_direction"];
				} else {
					$query .= " ORDER BY group_sort_field ".$view["settings"]["ot_sort_direction"];
				}
			} else {
				$query .= " ORDER BY group_field";
			}

			$q = sqlquery($query);
			
			// If there's another table, we're going to query it separately.
			if ($view["settings"]["other_table"] && !$view["settings"]["group_parser"]) {
				$otq = array();
				
				while ($f = sqlfetch($q)) {
					$otq[] = "id = '".$f["group_field"]."'";
					// We need to instatiate all of these as empty first in case the database relationship doesn't exist.
					$groups[$f["group_field"]] = "";
				}
				
				if (count($otq)) {
					if ($view["settings"]["ot_sort_field"]) {
						$otsf = $view["settings"]["ot_sort_field"];
						
						if ($view["settings"]["ot_sort_direction"]) {
							$otsd = $view["settings"]["ot_sort_direction"];
						} else {
							$otsd = "ASC";
						}
					} else {
						$otsf = "id";
						$otsd = "ASC";
					}

					$q = sqlquery("SELECT id,`".$view["settings"]["title_field"]."` AS `title` FROM `".$view["settings"]["other_table"]."` WHERE ".implode(" OR ",$otq)." ORDER BY `$otsf` $otsd");
					
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

		public static function getItem($table,$id) {
			global $cms;

			// The entry is pending if there's a "p" prefix on the id
			if (substr($id,0,1) == "p") {
				return self::getPendingItem($table,$id);
			}
			// Otherwise it's a live entry
			$item = sqlfetch(sqlquery("SELECT * FROM `$table` WHERE id = '".sqlescape($id)."'"));
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
				form_id - Either a form entry or form id.
			
			Returns:
				The id of the module the form is a member of.
		*/
		
		public static function getModuleForForm($form_id) {
			if (is_array($form_id)) {
				if (!empty($form_id["module"])) {
					return $form_id["module"];
				}

				$form_id = $form_id["id"];
			}

			$modules = BigTreeJSONDB::getAll("modules");

			foreach ($modules as $module) {
				if (is_array($module["forms"])) {
					foreach ($module["forms"] as $form) {
						if ($form["id"] == $form_id) {
							return $module["id"];
						}
					}
				}
			}

			return null;
		}
		
		/*
			Function: getModuleForView
				Returns the associated module id for the given view.
			
			Parameters:
				view - Either a view entry or view id.
			
			Returns:
				The id of the module the view is a member of.
		*/

		public static function getModuleForView($view_id) {
			if (is_array($view_id)) {
				if (!empty($view_id["module"])) {
					return $view_id["module"];
				}

				$view_id = $view_id["id"];
			}

			$modules = BigTreeJSONDB::getAll("modules");

			foreach ($modules as $module) {
				if (is_array($module["views"])) {
					foreach ($module["views"] as $view) {
						if ($view["id"] == $view_id) {
							return $module["id"];
						}
					}
				}
			}

			return null;
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

		public static function getPendingItem($table,$id) {
			$status = "published";
			$many_to_many = array();
			$owner = false;

			// The entry is pending if there's a "p" prefix on the id
			if (substr($id,0,1) == "p") {
				$change = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE id = '".sqlescape(substr($id,1))."'"));
				
				if (!$change) {
					return false;
				}
				
				$item = json_decode($change["changes"], true);
				$many_to_many = json_decode($change["mtm_changes"], true);
				$temp_tags = json_decode($change["tags_changes"], true);
				$open_graph = json_decode($change["open_graph_changes"], true);
				$tags = array();

				if (!empty($temp_tags)) {
					foreach ($temp_tags as $tag_id) {
						$tag = sqlfetch(sqlquery("SELECT * FROM bigtree_tags WHERE id = '".intval($tag_id)."'"));

						if ($tag) {
							$tags[] = $tag;
						}
					}
				}

				$status = "pending";
				$owner = $change["user"];
			// Otherwise it's a live entry
			} else {
				$id = sqlescape($id);
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
					
					$many_to_many = json_decode($change["mtm_changes"], true);
					$temp_tags = json_decode($change["tags_changes"], true);
					$open_graph = json_decode($change["open_graph_changes"], true);
					$tags = array();
					
					if (is_array($temp_tags)) {
						foreach ($temp_tags as $tag_id) {
							$tag = sqlfetch(sqlquery("SELECT * FROM bigtree_tags WHERE id = '".intval($tag_id)."'"));

							if ($tag) {
								$tags[] = $tag;
							}
						}
					}
				// If there's no pending changes, just pull the tags
				} else {
					$tags = self::getTagsForEntry($table,$id);
					$open_graph = BigTreeCMS::getOpenGraph($table, $id);
				}
			}
			
			// Process the internal page links, turn json_encoded arrays into arrays.
			foreach ($item as $key => $val) {
				if (is_array($val)) {
					$item[$key] = BigTree::untranslateArray($val);
				} elseif (is_array(json_decode($val,true))) {
					$item[$key] = BigTree::untranslateArray(json_decode($val,true));
				} else {
					$item[$key] = BigTreeCMS::replaceInternalPageLinks($val);
				}
			}

			return array("item" => $item, "mtm" => $many_to_many, "tags" => $tags, "open_graph" => $open_graph, "status" => $status, "owner" => $owner);
		}

		/*
			Function: getRelatedFormForReport
				Returns the form for the same table as the given report.
			
			Parameters:
				report - A report entry.
			
			Returns:
				A form entry with fields decoded.
		*/

		public static function getRelatedFormForReport($report) {
			$modules = BigTreeJSONDB::getAll("modules");

			foreach ($modules as $module) {
				if (is_array($module["forms"])) {
					foreach ($module["forms"] as $form) {
						if ($form["table"] == $report["table"]) {
							return static::getForm($form);
						}
					}
				}
			}
		}
		
		/*
			Function: getRelatedFormForView
				Returns the form for the same table as the given view.
			
			Parameters:
				view - A view entry.
			
			Returns:
				A form entry with fields decoded.
		*/

		public static function getRelatedFormForView($view) {
			$modules = BigTreeJSONDB::getAll("modules");
		
			if ($view["related_form"]) {
				foreach ($modules as $module) {
					if (is_array($module["forms"])) {
						foreach ($module["forms"] as $form) {
							if ($form["id"] == $view["table"]) {
								return static::getForm($form);
							}
						}
					}
				}
			}

			foreach ($modules as $module) {
				if (is_array($module["forms"])) {
					foreach ($module["forms"] as $form) {
						if ($form["table"] == $view["table"]) {
							return static::getForm($form);
						}
					}
				}
			}

			return null;
		}
		
		/*
			Function: getRelatedViewForForm
				Returns the view for the same table as the given form.
			
			Parameters:
				form - A form entry.
			
			Returns:
				A view entry.
		*/

		public static function getRelatedViewForForm($form) {
			$modules = BigTreeJSONDB::getAll("modules");
			
			// Prioritize a view that has this form as the related form
			foreach ($modules as $module) {
				if (is_array($module["views"])) {
					foreach ($module["views"] as $view) {
						if ($form["id"] == $view["related_form"]) {
							return static::getView($view);
						}
					}
				}
			}

			foreach ($modules as $module) {
				if (is_array($module["views"])) {
					foreach ($module["views"] as $view) {
						if ($form["table"] == $view["table"]) {
							return static::getView($view);
						}
					}
				}
			}

			return null;
		}

		/*
			Function: getRelatedViewForReport
				Returns the view for the same table as the given report.
			
			Parameters:
				report - A report entry.
			
			Returns:
				A view entry.
		*/

		public static function getRelatedViewForReport($report) {
			$modules = BigTreeJSONDB::getAll("modules");
			
			foreach ($modules as $module) {
				if (is_array($module["views"])) {
					foreach ($module["views"] as $view) {
						if ($report["table"] == $view["table"]) {
							return static::getView($view);
						}
					}
				}
			}
		}

		/*
			Function: getReport
				Returns a report with the filters and fields decoded.

			Parameters:
				id - The ID of the report

			Returns:
				An array of report information.
		*/

		public static function getReport($id) {
			$modules = BigTreeJSONDB::getAll("modules");
			
			foreach ($modules as $module) {
				if (is_array($module["reports"])) {
					foreach ($module["reports"] as $report) {
						if ($report["id"] == $id) {
							$report["module"] = $module["id"];
							
							return $report;
						}
					}
				}
			}

			return null;
		}

		/*
			Function: getReportResults
				Returns rows from the table that match the filters provided.

			Parameters:
				report - A module reports entry.
				view - A module views entry.
				form - A module forms entry.
				filters - The submitted filters to run.
				sort_field - The field to sort by.
				sort_direction - The direction to sort by.

			Returns:
				An array of entries from the report's table.
		*/

		public static function getReportResults($report,$view,$form,$filters,$sort_field = "id",$sort_direction = "DESC") {
			// Prevent SQL injection
			$sort_field = "`".str_replace("`","",$sort_field)."`";
			$sort_direction = ($sort_direction == "ASC") ? "ASC" : "DESC";
			$where = $items = $parsers = $poplists = array();
			
			// Figure out if we have db populated lists and parsers
			if ($report["type"] == "view") {
				foreach ($view["fields"] as $key => $field) {
					if ($field["parser"]) {
						$parsers[$key] = $field["parser"];
					}
				}
			}
			
			if (is_array($form["fields"])) {
				foreach ($form["fields"] as $key => $field) {
					if ($field["type"] == "list" && $field["settings"]["list_type"] == "db") {
						$poplists[$key] = array("description" => $form["fields"][$key]["settings"]["pop-description"], "table" => $form["fields"][$key]["settings"]["pop-table"]);
					}
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
						if ($filters[$id]["start"]) {
							$where[] = "`$id` >= '".sqlescape($filters[$id]["start"])."'";
						}
						if ($filters[$id]["end"]) {
							$where[] = "`$id` <= '".sqlescape($filters[$id]["end"])."'";
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

			// If there is a data parser we need to run it
			if (!empty($report["parser"]) && function_exists($report["parser"])) {
				$items = call_user_func($report["parser"], $items);
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
		
		public static function getSearchResults($view,$page = 1,$query = "",$sort = "id DESC",$group = false) {
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
			
			$per_page = $view["settings"]["per_page"] ? $view["settings"]["per_page"] : BigTreeAdmin::$PerPage;
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
			} else {
				$sort_direction = (strtolower($sort_direction) == "asc") ? "ASC" : "DESC";
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
				full - Whether to return a full tag array or just the tag string (defaults to full tag array)
			
			Returns:
				An array ot tags from bigtree_tags.
		*/
		
		public static function getTagsForEntry($table, $id, $full = true) {
			if ($full) {
				return SQL::fetchAll("SELECT bigtree_tags.* FROM bigtree_tags JOIN bigtree_tags_rel
									  ON bigtree_tags_rel.tag = bigtree_tags.id
									  WHERE bigtree_tags_rel.`table` = ?
									    AND bigtree_tags_rel.`entry` = ?
						  			  ORDER BY bigtree_tags.tag ASC", $table, $id);
			}
			
			return SQL::fetchAllSingle("SELECT bigtree_tags.tag FROM bigtree_tags JOIN bigtree_tags_rel
									    ON bigtree_tags_rel.tag = bigtree_tags.id
									    WHERE bigtree_tags_rel.`table` = ?
									      AND bigtree_tags_rel.`entry` = ?
						  			    ORDER BY bigtree_tags.tag ASC", $table, $id);
		}
		
		/*
			Function: getView
				Returns a view.
			
			Parameters:
				view_id - The id of the view or a view entry.
				decode_ipl - Whether we want to decode internal page link on the preview url (defaults to true)
				
			Returns:
				A view entry with actions, settings, and fields decoded.  fields also receive a width column for the view.
		*/

		public static function getView($view_id, $decode_ipl = true) {
			if (is_array($view_id)) {
				$view_id = $view_id["id"];
			}

			$modules = BigTreeJSONDB::getAll("modules");
			$view = null;

			foreach ($modules as $module) {
				if (is_array($module["views"])) {
					foreach ($module["views"] as $module_view) {
						if ($module_view["id"] == $view_id) {
							$view = $module_view;
							$view["module"] = $module["id"];
							$module_for_view = $module;

							break 2;
						}
					}
				}
			}

			if (!$view) {
				return null;
			}

			// We may be in AJAX, so we need to define MODULE_ROOT if it's not available
			if (!defined("MODULE_ROOT")) {
				$module_root = ADMIN_ROOT.$module_for_view["route"]."/";
			} else {
				$module_root = MODULE_ROOT;
			}

			$view["options"] = &$view["settings"]; // Backwards compatibility

			// Get the edit link
			if (isset($view["actions"]["edit"])) {
				if ($view["related_form"]) {
					$edit_action = null;
					$regular_action = null;

					foreach ($module_for_view["actions"] as $action) {
						if ($action["form"] == $view["related_form"] && substr($action["route"], 0, 4) == "edit") {
							$edit_action = $action;

							break;
						} elseif ($action["form"] == $view["related_form"]) {
							$regular_action = $action;
						}
					}

					if ($edit_action) {
						$view["edit_url"] = $module_root.$edit_action["route"]."/";
					} elseif ($regular_action) {
						$view["edit_url"] = $module_root.$regular_action["route"]."/";
					} else {
						$view["edit_url"] = null;
					}
				} else {
					$view["edit_url"] = $module_root."edit/";
				}
			}
			
			$actions = $view["preview_url"] ? ($view["actions"] + ["preview" => "on"]) : $view["actions"];
			$fields = $view["fields"];
			
			if (is_array($fields) && count($fields)) {
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
				$view["fields"] = [];
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
		
		public static function getViewData($view,$sort = "id DESC",$type = "both") {
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
		
		public static function getViewDataForGroup($view,$group,$sort,$type = "both") {
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
			Function: getViewForTable
				Gets a view for a given table for showing change lists in Pending Changes.
			
			Parameters:
				table - Table name.
			
			Returns:
				A view entry with settings, and fields decoded and field widths set for Pending Changes.
		*/
		
		public static function getViewForTable($table) {
			global $cms;
			
			$modules = BigTreeJSONDB::getAll("modules");
			$view = null;
			$module_for_view = null;

			foreach ($modules as $module) {
				if (is_array($module["views"])) {
					foreach ($module["views"] as $module_view) {
						if ($module_view["table"] == $table) {
							$view = $module_view;
							$module_for_view = $module;

							break 2;
						}
					}
				}
			}

			if (!$view) {
				return null;
			}

			$view["options"] = &$view["settings"]; // Backwards compatibility

			// Get the edit link
			if (isset($view["actions"]["edit"])) {
				if ($view["related_form"]) {
					$edit_action = null;
					$regular_action = null;

					foreach ($module_for_view["actions"] as $action) {
						if ($form["id"] == $view["related_form"] && substr($action["route"], 0, 4) == "edit") {
							$edit_action = $action;

							break;
						} elseif ($form["id"] == $view["related_form"]) {
							$regular_action = $action;
						}
					}

					if ($edit_action) {
						$view["edit_url"] = $module_root.$edit_action["route"]."/";
					} elseif ($regular_action) {
						$view["edit_url"] = $module_root.$regular_action["route"]."/";
					} else {
						$view["edit_url"] = null;
					}
				} else {
					$view["edit_url"] = $module_root."edit/";
				}
			}
			
			$fields = $view["fields"];

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

		public static function parseViewData($view,$items) {
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
							if ($form["fields"][$key]["type"] == "list" && $form["fields"][$key]["settings"]["list_type"] == "db") {
								$field_data = $form["fields"][$key];
								$f = sqlfetch(sqlquery("SELECT `".$field_data["settings"]["pop-description"]."` FROM `".$field_data["settings"]["pop-table"]."` WHERE `id` = '".sqlescape($value)."'"));
								$value = $f[$field_data["settings"]["pop-description"]];
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
				open_graph - Open Graph information
			
			Returns:
				The id of the new entry.
		*/
		
		public static function publishPendingItem($table, $id, $data, $many_to_many = array(), $tags = array(), $open_graph = array()) {
			return self::createItem($table, $data, $many_to_many, $tags, $id, $open_graph);
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
		
		public static function recacheItem($id,$table,$pending = false) {
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
		
		public static function sanitizeData($table,$data,$existing_description = false) {
			// Setup column info				
			$table_description = $existing_description ? $existing_description : BigTree::describeTable($table);
			$columns = $table_description["columns"];

			foreach ($data as $key => $val) {
				$allow_null = $columns[$key]["allow_null"];
				$type = $columns[$key]["type"];

				// Sanitize Integers
				if ($type == "tinyint" || $type == "smallint" || $type == "mediumint" || $type == "int" || $type == "bigint") {
					if ($allow_null == "YES" && ($val === null || $val === false || $val === "")) {
						$data[$key] = "NULL";	
					} else {
						$data[$key] = intval(str_replace(array(",","$"),"",$val));
					}
				}
				// Sanitize Floats
				if ($type == "float" || $type == "double" || $type == "decimal") {
					if ($allow_null == "YES" && ($val === null || $val === false || $val === "")) {
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
				Can only be called when logged into the admin.
			
			Parameters:
				module - The module for the entry.
				table - The table the entry is stored in.
				id - The id of the entry.
				data - The change request data.
				many_to_many - The many to many changes.
				tags - The tag changes.
				publish_hook - A function to call when this change is published from the Dashboard.
				open_graph - Open Graph changes.
			
			Returns:
				The id of the pending change.
		*/
		
		public static function submitChange($module, $table, $id, $data, $many_to_many = array(), $tags = array(), $publish_hook = null, $open_graph = array()) {
			global $admin;

			if (!isset($admin) || get_class($admin) != "BigTreeAdmin" || !$admin->ID) {
				throw new Exception("BigTreeAutoModule::submitChange must be called by a logged in user.");
			}

			$original = SQL::fetch("SELECT * FROM `$table` WHERE id = ?", $id);
			
			foreach ($data as $key => $val) {
				if ($val === "NULL") {
					$data[$key] = "";
				}

				if ($original && $original[$key] === $val) {
					unset($data[$key]);
				}
			}

			$tags = $tags ?: [];
			$many_to_many = $many_to_many ?: [];
			$open_graph = $open_graph ?: [];

			// Find out if there's already a change waiting
			if (substr($id, 0, 1) == "p") {
				$existing = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", substr($id, 1));
			} else {
				$existing = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE `table` = ? AND `item_id` = ?", $table, $id);
			}

			if ($existing) {
				SQL::update("bigtree_pending_changes", $existing["id"], [
					"user" => $admin->ID,
					"date" => "NOW()",
					"changes" => $data,
					"mtm_changes" => $many_to_many,
					"tags_changes" => $tags,
					"open_graph_changes" => $open_graph,
					"module" => $module,
					"type" => "EDIT",
					"publish_hook" => $publish_hook ?: null
				]);

				// If the id has a "p" it's still pending and we need to recache over the pending one.
				if (substr($id,0,1) == "p") {
					self::recacheItem(substr($id, 1), $table, true);
				} else {
					self::recacheItem($id, $table);					
				}
				
				$admin->track($table, $id, "updated-draft");

				return $existing["id"];
			} else {
				$change_id = SQL::insert("bigtree_pending_changes", [
					"user" => $admin->ID,
					"date" => "NOW()",
					"table" => $table,
					"item_id" => $id ?: null,
					"changes" => $data,
					"mtm_changes" => $many_to_many,
					"tags_changes" => $tags,
					"open_graph_changes" => $open_graph,
					"module" => $module,
					"type" => "EDIT",
					"publish_hook" => $publish_hook ?: null
				]);

				self::recacheItem($id, $table);
				$admin->track($table,$id,"saved-draft");
				
				return $change_id;
			}
		}

		/*
			Function: track
				Used internally by the class to facilitate audit trail tracking when a logged in user is making a call.

			Parameters:
				table - The table that is being changed
				id - The id of the record being changed
				action - The action being taken
				user - A user ID to attribute the change to
		*/

		public static function track($table, $id, $action, $user = null) {
			global $admin;

			if (isset($admin) && get_class($admin) == "BigTreeAdmin" && (!is_null($user) || $admin->ID)) {
				$admin->track($table, $id, $action, $user);
			}
		}
		
		/*
			Function: uncacheItem
				Removes a database entry from the view cache.
			
			Parameters:
				id - The id of the entry.
				table - The table the entry is in.
		*/
		
		public static function uncacheItem($id,$table) {
			$modules = BigTreeJSONDB::getAll("modules");

			foreach ($modules as $module) {
				if (is_array($module["views"])) {
					foreach ($module["views"] as $view) {
						if ($view["table"] == $table) {
							SQL::query("DELETE FROM bigtree_module_view_cache WHERE `view` = ? AND `id` = ?", $view["id"], $id);
						}
					}
				}
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
				open_graph - Open Graph information.
		*/
		
		public static function updateItem($table,$id,$data,$many_to_many = array(),$tags = array(),$open_graph = array()) {
			$id = sqlescape($id);
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
			$existing_tags = SQL::fetchAllSingle("SELECT `tag` FROM bigtree_tags_rel WHERE `table` = ? AND `entry` = ?", $table, $id);
			sqlquery("DELETE FROM bigtree_tags_rel WHERE `table` = '".sqlescape($table)."' AND entry = '$id'");
			
			if (!empty($tags) && is_array($tags)) {
				$tags = array_unique($tags);
				
				foreach ($tags as $tag) {
					$tag = intval($tag);
					
					sqlquery("DELETE FROM bigtree_tags_rel WHERE `table` = '".sqlescape($table)."' AND entry = $id AND tag = $tag");
					sqlquery("INSERT INTO bigtree_tags_rel (`table`,`entry`,`tag`) VALUES ('".sqlescape($table)."',$id,$tag)");
				}
			} else {
				$tags = [];
			}

			$update_tags = array_merge($tags, $existing_tags);

			if (count($update_tags)) {
				BigTreeAdmin::updateTagReferenceCounts($update_tags);
			}

			// Handle Open Graph
			BigTreeAdmin::handleOpenGraph($table, $id, $open_graph);

			// See if there's a pending change that's being published
			$change = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE `table` = ? AND `item_id` = ?", $table, $id);

			if ($change) {
				$change_data = BigTree::untranslateArray(json_decode($change["changes"], true));
				$exact = true;

				foreach ($change_data as $key => $value) {
					if (isset($data[$key]) && $data[$key] != $value) {
						$exact = false;
					}
				}

				SQL::delete("bigtree_pending_changes", $change["id"]);

				if ($exact) {
					self::track($table, $id, "updated via publisher", $change["user"]);
					self::track($table, $id, "published");
				} else {
					self::track($table, $id, "updated");
				}
			} else {
				self::track($table, $id, "updated");
			}

			if ($table != "bigtree_pages") {
				self::recacheItem($id,$table);
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
		
		public static function updatePendingItemField($id,$field,$value) {
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
		
		public static function validate($data,$type) {
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
				} elseif (in_array("required",$parts) && ($data === false || $data === "")) {
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
		
		public static function validationErrorMessage($data,$type) {
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
