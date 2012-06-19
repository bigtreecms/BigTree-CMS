<?
	/*
		Class: BigTreeAutoModule
			Handles functions for auto module forms / views created in Developer.
	*/

	class BigTreeAutoModule {
		
		/*
			Function: cacheViewData
				Grabs all the data from a view and does parsing on it based on automatic assumptions and manual parsers.
			
			Parameters:
				view - The view entry to cache data for.
		*/
		
		static function cacheViewData($view) {
			// See if we already have cached data.
			$r = sqlrows(sqlquery("SELECT id FROM bigtree_module_view_cache WHERE view = '".$view["id"]."'"));
			if ($r) {
				return;
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
			$cache_columns = sqlcolumns("bigtree_module_view_cache");
			$cc = count($cache_columns) - 11;
			while ($field_count > $cc) {
				$cc++;
				sqlquery("ALTER TABLE bigtree_module_view_cache ADD COLUMN column$cc TEXT NOT NULL AFTER column".($cc-1));
			}
			
			// Cache all records that are published (and include their pending changes)
			$q = sqlquery("SELECT `".$view["table"]."`.*,bigtree_pending_changes.changes AS bigtree_changes FROM `".$view["table"]."` LEFT JOIN bigtree_pending_changes ON (bigtree_pending_changes.item_id = `".$view["table"]."`.id AND bigtree_pending_changes.table = '".$view["table"]."')");
			while ($item = sqlfetch($q)) {
				if ($item["bigtree_changes"]) {
					$changes = json_decode($item["bigtree_changes"],true);
					foreach ($changes as $key => $change) {
						$item[$key] = $change;
					}
				}	

				self::cacheRecord($item,$view,$parsers,$poplists);
			}

			$q = sqlquery("SELECT * FROM bigtree_pending_changes WHERE `table` = '".$view["table"]."' AND item_id = '0'");
			while ($f = sqlfetch($q)) {
				$item = json_decode($f["changes"],true);
				$item["bigtree_pending"] = true;
				$item["bigtree_pending_owner"] = $f["user"];
				$item["id"] = "p".$f["id"];
				
				self::cacheRecord($item,$view,$parsers,$poplists);
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
		*/
		
		static function cacheRecord($item,$view,$parsers,$poplists) {
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
			
			// Let's see if we have a grouping field.  If we do, let's get all that info and cache it as well.
			if ($view["options"]["group_field"]) {
				$value = $item[$view["options"]["group_field"]];

				// Check for a parser
				if (isset($view["options"]["group_parser"]) && $view["options"]["group_parser"]) {
					@eval($view["options"]["group_parser"]);
				}

				$fields[] = "group_field";
				$vals[] = "'".mysql_real_escape_string($value)."'";
				
				if (is_numeric($value) && $view["options"]["other_table"]) {
					$f = sqlfetch(sqlquery("SELECT * FROM `".$view["options"]["other_table"]."` WHERE id = '$value'"));
					if ($view["options"]["ot_sort_field"]) {
						$fields[] = "group_sort_field";
						$vals[] = "'".mysql_real_escape_string($f[$view["options"]["ot_sort_field"]])."'";
					}
				}
			}
			
			// Group based permissions data
			if (isset($view["gbp"]["enabled"]) && $view["gbp"]["table"] == $view["table"]) {
				$fields[] = "gbp_field";
				$vals[] = "'".mysql_real_escape_string($item[$view["gbp"]["group_field"]])."'";
			}
			
			// Run parsers
			foreach ($parsers as $key => $parser) {
				$value = $item[$key];
				@eval($parser);
				$item[$key] = $value;
			}
			
			// Run pop lists
			foreach ($poplists as $key => $pop) {
				$f = sqlfetch(sqlquery("SELECT `".$pop["description"]."` FROM `".$pop["table"]."` WHERE id = '".$item[$key]."'"));
				if (is_array($f)) {
					$item[$key] = current($f);
				}
			}
			
			$cache = true;
			if (isset($view["options"]["filter"]) && $view["options"]["filter"]) {
				@eval('$cache = '.$view["options"]["filter"].'($item);');
			}
			
			if ($cache) {
				$x = 1;
				
				if ($view["type"] == "images" || $view["type"] == "images-grouped") {
					$fields[] = "column1";
					$vals[] = "'".$item[$view["options"]["image"]]."'";
					$fields[] = "column2";
					$vals[] = "'".$item[$view["options"]["caption"]]."'";
				} else {
					foreach ($view["fields"] as $field => $options) {
						$item[$field] = $cms->replaceInternalPageLinks($item[$field]);
						$fields[] = "column$x";
						if (isset($parsers[$field]) && $parsers[$field]) {
							$vals[] = "'".mysql_real_escape_string($item[$field])."'";					
						} else {
							$vals[] = "'".mysql_real_escape_string(strip_tags($item[$field]))."'";
						}
						$x++;
					}
				}
				
				sqlquery("INSERT INTO bigtree_module_view_cache (".implode(",",$fields).") VALUES (".implode(",",$vals).")");
			}
		}
		
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
			}
			
			$q = sqlquery("SELECT * FROM bigtree_module_views WHERE `table` = '$table'");
			while ($view = sqlfetch($q)) {
				if ($recache) {
					sqlquery("DELETE FROM bigtree_module_view_cache WHERE `view` = '".$view["id"]."' AND id = '".$item["id"]."'");
				}
				
				$view["fields"] = json_decode($view["fields"],true);
				$view["actions"] = json_decode($view["actions"],true);
				$view["options"] = json_decode($view["options"],true);
				
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
				
				self::cacheRecord($item,$view,$parsers,$poplists);
			}
		}
		
		/*
			Function: clearCache
				Clears the cache of a view or all views with a given table.
			
			Parameters:
				view - The view id or view entry to clear the cache for or a table to find all views for (and clear their caches).
		*/
		
		static function clearCache($view) {
			if (is_array($view)) {
				sqlquery("DELETE FROM bigtree_module_view_cache WHERE view = '".mysql_real_escape_string($view["id"])."'");		
			} elseif (is_numeric($view)) {
				sqlquery("DELETE FROM bigtree_module_view_cache WHERE view = '$view'");
			} else {
				$q = sqlquery("SELECT id FROM bigtree_module_views WHERE `table` = '".mysql_real_escape_string($view)."'");
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
			
			$columns = sqlcolumns($table);
			$query_fields = array();
			$query_vals = array();
			foreach ($data as $key => $val) {
				if (array_key_exists($key,$columns)) {
					$query_fields[] = "`".$key."`";
					if ($val === "NULL" || $val == "NOW()") {
						$query_vals[] = $val;
					} else {
						$query_vals[] = "'".mysql_real_escape_string($val)."'";
					}
				}
			}
			sqlquery("INSERT INTO $table (".implode(",",$query_fields).") VALUES (".implode(",",$query_vals).")");
			$id = sqlid();

			// Handle many to many
			foreach ($many_to_many as $mtm) {
				$cols = sqlcolumns($mtm["table"]);
				if (is_array($mtm["data"])) {
					foreach ($mtm["data"] as $position => $item) {
						if ($cols["position"])
							sqlquery("INSERT INTO `".$mtm["table"]."` (`".$mtm["my-id"]."`,`".$mtm["other-id"]."`,`position`) VALUES ('$id','$item','$position')");
						else
							sqlquery("INSERT INTO `".$mtm["table"]."` (`".$mtm["my-id"]."`,`".$mtm["other-id"]."`) VALUES ('$id','$item')");
					}
				}
			}

			// Handle the tags
			$mid = mysql_real_escape_string($module["id"]);
			sqlquery("DELETE FROM bigtree_tags_rel WHERE module = '$mid' AND entry = '$id'");
			if (is_array($tags)) {
				foreach ($tags as $tag) {
					sqlquery("DELETE FROM bigtree_tags_rel WHERE module = $mid AND entry = $id AND tag = $tag");
					sqlquery("INSERT INTO bigtree_tags_rel (`module`,`entry`,`tag`) VALUES ($mid,$id,$tag)");
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
			}

			$data = mysql_real_escape_string(json_encode($data));
			$many_data = mysql_real_escape_string(json_encode($many_to_many));
			$tags_data = mysql_real_escape_string(json_encode($tags));
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
			
			$id = mysql_real_escape_string($id);
			sqlquery("DELETE FROM $table WHERE id = '$id'");
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
			
			$id = mysql_real_escape_string($id);
			sqlquery("DELETE FROM bigtree_pending_changes WHERE `table` = '$table' AND id = '$id'");
			self::uncacheItem("p$id",$table);

			if ($admin) {
				$admin->track($table,"p$id","deleted-pending");
			}
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
						$gfl[] = "`gbp_field` = '".mysql_real_escape_string($g)."'";
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
			
			Returns:
				A module form entry with fields decoded.
		*/

		static function getForm($id) {
			if (is_array($id)) {
				$id = $id["id"];
			}
			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_module_forms WHERE id = '$id'"));
			$f["fields"] = json_decode($f["fields"],true);
			return $f;
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
				$query .= " ORDER BY group_sort_field ".$view["options"]["ot_sort_direction"];
			} else {
				$query .= " ORDER BY group_field";
			}
			$q = sqlquery($query);
			
			// If there's another table, we're going to query it separately.
			if ($view["options"]["other_table"] && !$view["options"]["group_parser"]) {
				$otq = array();
				while ($f = sqlfetch($q)) {
					$otq[] = "id = '".$f["group_field"]."'";
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
				An array with the follow elements:
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
			// Otherwise it's a live entry
			} else {
				$item = sqlfetch(sqlquery("SELECT * FROM $table WHERE id = '$id'"));
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
					$tags = self::getTagsForEntry($module["id"],$id);
				}
			}
			
			// Process the internal page links, turn json_encoded arrays into arrays.
			foreach ($item as $key => $val) {
				if (is_array(json_decode($val,true))) {
					$item[$key] = BigTree::untranslateArray(json_decode($val,true));
				} else {
					$item[$key] = $cms->replaceInternalPageLinks($val);
				}
			}
			return array("item" => $item, "mtm" => $many_to_many, "tags" => $tags, "status" => $status);
		}
		
		/*
			Function: getRelatedFormForView
				Returns the form for the same table as the given view.
			
			Paramaters:
				view - A view entry.
			
			Returns:
				A form entry with fields decoded.
		*/

		static function getRelatedFormForView($view) {
			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_module_forms WHERE `table` = '".mysql_real_escape_string($view["table"])."'"));
			$f["fields"] = json_decode($f["fields"],true);
			return $f;
		}
		
		/*
			Function: getRelatedViewForForm
				Returns the view for the same table as the given form.
			
			Paramaters:
				form - A form entry.
			
			Returns:
				A view entry.
		*/

		static function getRelatedViewForForm($form) {
			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_module_views WHERE `table` = '".mysql_real_escape_string($form["table"])."'"));
			return $f;
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
				module - The module entry to check permissions against.
		
			Returns:
				An array containing "pages" with the number of result pages and "results" with the results for the given page.
		*/
		
		static function getSearchResults($view,$page = 0,$query = "",$sort = "id DESC",$group = false, $module = false) {
			global $last_query,$admin;
			
			// If we don't need parsed data, just use the normal table.
			if ($view["uncached"]) {
				$search_parts = explode(" ",strtolower($query));
				
				if ($group) {
					$query = "SELECT * FROM ".$view["table"]." WHERE `".$view["options"]["group_field"]."` = '".mysql_real_escape_string($group)."'".self::getUncachedFilterQuery($view);
				} else {
					$query = "SELECT * FROM ".$view["table"]." WHERE 1".self::getUncachedFilterQuery($view);
				}
				
				foreach ($search_parts as $part) {
					$x = 0;
					$qp = array();
					$part = mysql_real_escape_string(strtolower($part));
					foreach ($view["fields"] as $key => $field) {
						$qp[] = "LOWER(`$key`) LIKE '%$part%'";
					}
					$query .= " AND (".implode(" OR ",$qp).")";
				}
				
				$per_page = $view["options"]["per_page"] ? $view["options"]["per_page"] : 15;
				
				$qc = sqlfetch(sqlquery(str_replace("SELECT * FROM","SELECT COUNT(id) as `count` FROM",$query)));
				$count = $qc["count"];
				
				$pages = ceil($count / $per_page);
				$pages = ($pages > 0) ? $pages : 1;
				$results = array();
				
				$q = sqlquery($query." ORDER BY $sort LIMIT ".($page * $per_page).",$per_page");
				
				while ($f = sqlfetch($q)) {
					$featured = isset($f["featured"]) ? $f["featured"] : false;
					$position = isset($f["position"]) ? $f["position"] : 0;
					$approved = isset($f["approved"]) ? $f["approved"] : false;
					$archived = isset($f["archived"]) ? $f["archived"] : false;
					
					$item = array("id" => $f["id"], "featured" => $featured, "position" => $position, "approved" => $approved, "archived" => $archived);
					$x = 0;
					foreach ($view["fields"] as $key => $field) {
						$x++;
						$item["column$x"] = strip_tags($f[$key]);
						if (isset($module["gbp"]["enabled"]) && $module["gbp"]["enabled"]) {
							$item["gbp_field"] = $f[$module["gbp"]["group_field"]];
						}
					}
					$results[] = $item;
				}
				
				return array("pages" => $pages, "results" => $results);
			}
			
			// Check to see if we've cached this table before.
			self::cacheViewData($view);
			
			$search_parts = explode(" ",strtolower($query));
			$view_columns = count($view["fields"]);
			
			if ($group !== false) {
				$query = "SELECT * FROM bigtree_module_view_cache WHERE view = '".$view["id"]."' AND group_field = '".mysql_real_escape_string($group)."'".self::getFilterQuery($view);				
			} else {
				$query = "SELECT * FROM bigtree_module_view_cache WHERE view = '".$view["id"]."'".self::getFilterQuery($view);
			}

			foreach ($search_parts as $part) {
				$x = 0;
				$qp = array();
				$part = mysql_real_escape_string(strtolower($part));
				while ($x < $view_columns) {
					$x++;
					$qp[] = "LOWER(column$x) LIKE '%$part%'";
				}
				$query .= " AND (".implode(" OR ",$qp).")";
			}
			
			$per_page = $view["options"]["per_page"] ? $view["options"]["per_page"] : 15;
			$pages = ceil(sqlrows(sqlquery($query)) / $per_page);
			$pages = ($pages > 0) ? $pages : 1;
			$results = array();
			
			// Get the correct column name for sorting
			list($sort_field,$sort_direction) = explode(" ",$sort);
			if ($sort_field != "id") {
				$x = 0;
				foreach ($view["fields"] as $field => $options) {
					$x++;
					if ($field == $sort_field) {
						$sort_field = "column$x";
					}
				}
			} else {
				$sort_field = "CONVERT(id,UNSIGNED)";
			}
			
			if ($page === "all") {
				$q = sqlquery($query." ORDER BY $sort_field $sort_direction");
			} else {
				$q = sqlquery($query." ORDER BY $sort_field $sort_direction LIMIT ".($page * $per_page).",$per_page");
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
				module - The module id for the entry.
				id - The id of the entry.
			
			Returns:
				An array ot tags from bigtree_tags.
		*/
		
		static function getTagsForEntry($module,$id) {
			$tags = array();
			$q = sqlquery("SELECT bigtree_tags.* FROM bigtree_tags JOIN bigtree_tags_rel WHERE bigtree_tags_rel.module = '$module' AND bigtree_tags_rel.entry = '$id' AND bigtree_tags_rel.tag = bigtree_tags.id ORDER BY bigtree_tags.tag ASC");
			while ($f = sqlfetch($q)) {
				$tags[] = $f;
			}
			return $tags;
		}
		
		/*
			Function: getUncachedFilterQuery
				Returns a query string that is used for searching views based on group permissions.
				This query is used on the table itself instead of the view cache.
			
			Parameters:
				view - The view to create a filter for.
			
			Returns:
				A set of MySQL statements that filter out information the user cannot access.
		*/
		
		static function getUncachedFilterQuery($view) {
			global $admin;
			$module = $admin->getModule(self::getModuleForView($view));
			if (isset($module["gbp"]["enabled"]) && $module["gbp"]["enabled"] && $module["gbp"]["table"] == $view["table"]) {
				$groups = $admin->getAccessGroups($module["id"]);
				if (is_array($groups)) {
					$gfl = array();
					foreach ($groups as $g) {
						$gfl[] = "`".$module["gbp"]["group_field"]."` = '".mysql_real_escape_string($g)."'";
					}
					return " AND (".implode(" OR ",$gfl).")";
				}
			}
			return "";
		}
		
		/*
			Function: getView
				Returns a view.
			
			Parameters:
				id - The id of the view.
				
			Returns:
				A view entry with actions, options, and fields decoded.  fields also receive a width column for the view.
		*/

		static function getView($id) {
			if (is_array($id)) {
				$id = $id["id"];
			}
			
			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_module_views WHERE id = '$id'"));
			$f["actions"] = json_decode($f["actions"],true);
			$f["options"] = json_decode($f["options"],true);
			
			$actions = $f["preview_url"] ? ($f["actions"] + array("preview" => "on")) : $f["actions"];
			$fields = json_decode($f["fields"],true);
			$first = current($fields);
			if (!isset($first["width"]) || !$first["width"]) {) {
				$awidth = count($actions) * 62;
				$available = 888 - $awidth;
				$percol = floor($available / count($fields));
			
				foreach ($fields as $key => $field) {
					$fields[$key]["width"] = $percol - 20;
				}
			}
			$f["fields"] = $fields;

			return $f;
		}
		
		/*
			Function: getViewData
				Gets a list of data for a view.
			
			Parameters:
				view - The view entry to pull data for.
				sort - The sort direction, defaults to most recent.
				type - Whether to get only active entries, pending entries, or both.
				module - The module entry for the view (for group based permissions)
			
			Returns:
				An array of items from bigtree_module_view_cache.
		*/
		
		static function getViewData($view,$sort = "id DESC",$type = "both",$module = false) {
			// If we don't need parsed data, just use the normal table.
			if ($view["uncached"]) {
				$view["per_page"] = 10000;
				$r = self::getSearchResults($view,0,"",$sort,false,$module);
				return $r["results"];
			}
		
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
				$items[] = $f;
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
				$q = sqlquery("SELECT * FROM bigtree_module_view_cache WHERE group_field = '".mysql_real_escape_string($group)."' AND view = '".$view["id"]."'".self::getFilterQuery($view)." ORDER BY $sort");
			} elseif ($type == "active") {
				$q = sqlquery("SELECT * FROM bigtree_module_view_cache WHERE group_field = '".mysql_real_escape_string($group)."' AND status != 'p' AND view = '".$view["id"]."'".self::getFilterQuery($view)." ORDER BY $sort");
			} elseif ($type == "pending") {
				$q = sqlquery("SELECT * FROM bigtree_module_view_cache WHERE group_field = '".mysql_real_escape_string($group)."' AND status = 'p' AND view = '".$view["id"]."'".self::getFilterQuery($view)." ORDER BY $sort");
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
		
		function getViewForTable($table) {
			$table = mysql_real_escape_string($table);
			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_module_views WHERE `table` = '$table'"));
			$f["options"] = json_decode($f["options"],true);
			
			$fields = json_decode($f["fields"],true);
			$first = current($fields);
			// Three or four actions, depending on preview availability.
			if ($f["preview_url"]) {
				$available = 578;
			} else {
				$available = 633;
			}
			$percol = floor($available / count($fields));
			foreach ($fields as $key => $field) {
				$fields[$key]["width"] = $percol - 20;
			}
			$f["fields"] = $fields;

			return $f;
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
							@eval($field["parser"]);
							$item[$key] = $value;
						// If we know this field is a populated list, get the title they entered in the form.
						} else {
							if ($form["fields"][$key]["type"] == "list" && $form["fields"][$key]["list_type"] == "db") {
								$fdata = $form["fields"][$key];
								$f = sqlfetch(sqlquery("SELECT `".$fdata["pop-description"]."` FROM `".$fdata["pop-table"]."` WHERE `id` = '".mysql_real_escape_string($value)."'"));
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
			global $module;
			
			self::deletePendingItem($table,$id);
			
			$query_fields = array();
			$query_vals = array();
			$columns = sqlcolumns($table);
			
			foreach ($data as $key => $val) {
				if (array_key_exists($key,$columns)) {
					$query_fields[] = "`".$key."`";
					if ($val === "NULL" || $val == "NOW()") {
						$query_vals[] = $val;
					} else {
						$query_vals[] = "'".mysql_real_escape_string($val)."'";
					}
				}
			}
			sqlquery("INSERT INTO $table (".implode(",",$query_fields).") VALUES (".implode(",",$query_vals).")");
			$id = sqlid();

			// Handle many to many
			foreach ($many_to_many as $mtm) {
				$cols = sqlcolumns($mtm["table"]);
				if (!empty($mtm["data"])) {
					foreach ($mtm["data"] as $position => $item) {
						if ($cols["position"]) {
							sqlquery("INSERT INTO `".$mtm["table"]."` (`".$mtm["my-id"]."`,`".$mtm["other-id"]."`,`position`) VALUES ('$id','$item','$position')");
						} else {
							sqlquery("INSERT INTO `".$mtm["table"]."` (`".$mtm["my-id"]."`,`".$mtm["other-id"]."`) VALUES ('$id','$item')");
						}
					}
				}
			}

			// Handle the tags
			$mid = mysql_real_escape_string($module["id"]);
			sqlquery("DELETE FROM bigtree_tags_rel WHERE module = '$mid' AND entry = '$id'");
			if (!empty($tags)) {
				foreach ($tags as $tag) {
					sqlquery("DELETE FROM bigtree_tags_rel WHERE module = $mid AND entry = $id AND tag = $tag");
					sqlquery("INSERT INTO bigtree_tags_rel (`module`,`entry`,`tag`) VALUES ($mid,$id,$tag)");
				}
			}
			
			self::cacheNewItem($id,$table);
			
			return $id;
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

			$original = sqlfetch(sqlquery("SELECT * FROM $table WHERE id = '$id'"));
			foreach ($data as $key => $val) {
				if ($val === "NULL")
					$data[$key] = "";
				if ($original[$key] == $val)
					unset($data[$key]);
			}
			$changes = mysql_real_escape_string(json_encode($data));
			$many_data = mysql_real_escape_string(json_encode($many_to_many));
			$tags_data = mysql_real_escape_string(json_encode($tags));

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
				$comments = mysql_real_escape_string(json_encode($comments));
				sqlquery("UPDATE bigtree_pending_changes SET comments = '$comments', changes = '$changes', mtm_changes = '$many_data', tags_changes = '$tags_data', date = NOW(), user = '".$admin->ID."', type = 'EDIT' WHERE id = '".$existing["id"]."'");
				self::recacheItem($id,$table);
				
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
			$columns = sqlcolumns($table);
			$query = "UPDATE $table SET";
			foreach ($data as $key => $val) {
				if (array_key_exists($key,$columns)) {
					if (is_array($val)) {
						$val = json_encode($val);
					}
					if ($val === "NULL" || $val == "NOW()") {
						$query .= "`$key` = $val,";
					} else {
						$query .= "`$key` = '".mysql_real_escape_string($val)."',";
					}
				}
			}
			$query = rtrim($query,",")." WHERE id = '$id'";
			sqlquery($query);

			// Handle many to many
			if (!empty($many_to_many)) {
				foreach ($many_to_many as $mtm) {
					sqlquery("DELETE FROM `".$mtm["table"]."` WHERE `".$mtm["my-id"]."` = '$id'");
					$cols = sqlcolumns($mtm["table"]);
					if (is_array($mtm["data"])) {
						foreach ($mtm["data"] as $position => $item) {
							if ($cols["position"]) {
								sqlquery("INSERT INTO `".$mtm["table"]."` (`".$mtm["my-id"]."`,`".$mtm["other-id"]."`,`position`) VALUES ('$id','$item','$position')");
							} else {
								sqlquery("INSERT INTO `".$mtm["table"]."` (`".$mtm["my-id"]."`,`".$mtm["other-id"]."`) VALUES ('$id','$item')");
							}
						}
					}
				}
			}

			// Handle the tags
			$mid = mysql_real_escape_string($module["id"]);
			sqlquery("DELETE FROM bigtree_tags_rel WHERE module = '$mid' AND entry = '$id'");
			if (!empty($tags)) {
				foreach ($tags as $tag) {
					sqlquery("DELETE FROM bigtree_tags_rel WHERE module = $mid AND entry = $id AND tag = $tag");
					sqlquery("INSERT INTO bigtree_tags_rel (`module`,`entry`,`tag`) VALUES ($mid,$id,$tag)");
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
			$id = mysql_real_escape_string($id);
			$item = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE id = '$id'"));
			$changes = json_decode($item["changes"],true);
			$changes[$field] = $value;
			$changes = mysql_real_escape_string(json_encode($changes));
			sqlquery("UPDATE bigtree_pending_changes SET changes = '$changes' WHERE id = '$id'");
		}
	}
?>
