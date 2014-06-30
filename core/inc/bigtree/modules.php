<?
	/*
		Class: BigTreeModule
			Base class from which all BigTree module classes inherit from.
	*/
	
	class BigTreeModule {
	
		var $Table = "";
		var $Module = "";
		var $NavPosition = "bottom";
		
		/*
			Function: add
				Adds an entry to the table.
			
			Parameters:
				keys - An array of column names to add
				vals - An array of values for each of the columns
				enforce_unique - Check to see if this entry is already in the database (prevent duplicates, defaults to false)
				ignore_cache - If this is set to true, BigTree will not cache this entry in bigtree_module_view_cache - faster entry if you don't have an admin view (defaults to false)
			
			Returns:
				The "id" of the new entry.
			
			See Also:
				<delete>
				<save>
				<update>
		*/
		
		function add($keys,$vals,$enforce_unique = false,$ignore_cache = false) {
			$admin = new BigTreeAdmin;
			$existing_parts = $key_parts = $value_parts = array();
			$x = 0;

			// Get a bunch of query parts.
			foreach ($keys as $key) {
				$val = current($vals);
				$val = is_array($val) ? sqlescape(json_encode(BigTree::translateArray($val))) : sqlescape($admin->autoIPL($val));
				$existing_parts[] = "`$key` = '$val'";
				$key_parts[] = "`$key`";
				$value_parts[] = "'$val'";
				next($vals);
			}

			// Prevent Duplicates
			if ($enforce_unique) {
				$row = sqlfetch(sqlquery("SELECT id FROM `".$this->Table."` WHERE ".implode(" AND ",$existing_parts)." LIMIT 1"));
				// If it's the same as an existing entry, return that entry's id
				if ($row) {
					return $row["id"];
				}
			}
			
			// Add the entry and cache it.
			sqlquery("INSERT INTO `".$this->Table."` (".implode(",",$key_parts).") VALUES (".implode(",",$value_parts).")");
			$id = sqlid();
			if (!$ignore_cache) {
				BigTreeAutoModule::cacheNewItem($id,$this->Table);
			}

			return $id;
		}
		
		/*
			Function: approve
				Approves a given entry.
			
			Parameters:
				item - The "id" of an entry or an entry from the table.
			
			See Also:
				<unapprove>
		*/
		
		function approve($item) {
			if (is_array($item)) {
				$item = $item["id"];
			}
			$this->update($item,"approved","on");
			BigTreeAutoModule::recacheItem($item,$this->Table);
		}
		
		/*
			Function: archive
				Archives a given entry.
			
			Parameters:
				item - The "id" of an entry or an entry from the table.
			
			See Also:
				<unarchive>
		*/
		
		function archive($item) {
			if (is_array($item)) {
				$item = $item["id"];
			}
			$this->update($item,"archived","on");
			BigTreeAutoModule::recacheItem($item,$this->Table);
		}
		
		/*
			Function: delete
				Deletes an entry from the table.
			
			Parameters:
				item - The id of the entry to delete or the entry itself.
			
			See Also:
				<add>
				<save>
				<update>
		*/
		
		function delete($item) {
			if (is_array($item)) {
				$item = $item["id"];
			}
			$item = sqlescape($item);
			sqlquery("DELETE FROM `".$this->Table."` WHERE id = '$item'");
			sqlquery("DELETE FROM bigtree_pending_changes WHERE `table` = '".$this->Table."' AND item_id = '$item'");
			BigTreeAutoModule::uncacheItem($item,$this->Table);
		}
		
		/*
			Function: feature
				Features a given entry.
			
			Parameters:
				item - The "id" of an entry or an entry from the table.
			
			See Also:
				<unfeature>
		*/
		
		function feature($item) {
			if (is_array($item)) {
				$item = $item["id"];
			}
			$this->update($item,"featured","on");
			BigTreeAutoModule::recacheItem($item,$this->Table);
		}
		
		/*
			Function: fetch
				Protected function used by other table querying functions.
		*/
		
		protected function fetch($sortby = false,$limit = false,$where = false) {
			$query = "SELECT * FROM `".$this->Table."`";

			if ($where) {
				$query .= " WHERE $where";
			}
			
			if ($sortby) {
				$query .= " ORDER BY $sortby";
			}
			
			if ($limit) {
				$query .= " LIMIT $limit";
			}
			
			$items = array();
			$q = sqlquery($query);
			while ($f = sqlfetch($q)) {
				$items[] = $this->get($f);
			}
			
			return $items;
		}
		
		/*
			Function: get
				Gets a single entry from the table or translates an entry from the table.
				This method is called on each entry retrieved in every other function in this class so it can be used for additional data transformation overrides in your module class.
			
			Parameters:
				item - Either the ID of an item to pull from the table or a table entry to parse.
			
			Returns:
				A translated item from the table.
		*/
		
		function get($item) {
			global $cms;
			
			if (!is_array($item)) {
				$item = sqlfetch(sqlquery("SELECT * FROM `".$this->Table."` WHERE id = '".sqlescape($item)."'"));
			}
			
			if (!$item) {
				return false;
			}
			
			foreach ($item as $key => $val) {
				if (is_array($val)) {
					$item[$key] = BigTree::untranslateArray($val);
				} elseif (is_array(json_decode($val,true))) {
					$item[$key] = BigTree::untranslateArray(json_decode($val,true));
				} else {
					$item[$key] = $cms->replaceInternalPageLinks($val);
				}
			}
			
			return $item;
		}
		
		/*
			Function: getAll
				Returns all items from the table.
			
			Parameters:
				order - The sort order (in MySQL syntax, i.e. "id DESC")
		
			Returns:
				An array of items from the table.
		*/

		function getAll($order = false) {
			return $this->fetch($order);
		}
		
		/*
			Function: getAllPositioned
				Returns all entries from the table based on position.
			
			Returns:
				An array of entries from the table.
		*/
		
		function getAllPositioned() {
			return $this->getAll("position DESC, id ASC");
		}
		
		/*
			Function: getApproved
				Returns approved entries from the table.
			
			Parameters:
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				limit - Max number of entries to return, defaults to all
			
			Returns:
				An array of entries from the table.
				
			See Also:
				<getMatching>
		*/
		
		function getApproved($order = false,$limit = false) {
			return $this->getMatching("approved","on",$order,$limit);
		}

		/*
			Function: getArchived
				Returns archived entries from the table.
			
			Parameters:
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				limit - Max number of entries to return, defaults to all
			
			Returns:
				An array of entries from the table.
				
			See Also:
				<getMatching>
		*/
		
		function getArchived($order = false,$limit = false) {
			return $this->getMatching("archived","on",$order,$limit);
		}
		
		/*
			Function: getBreadcrumb
				An optional function to override in your module class.
				Provides additional breadcrumb segments when <BigTreeCMS.getBreadcrumb> is called on a page with a template that uses this module.
			
			Parameters:
				page - The page data for the current page the user is on.
			
			Returns:
				An array of arrays with "title" and "link" key/value pairs.
		*/
		
		function getBreadcrumb($page) {
			return array();
		}
		
		/*
			Function: getByRoute
				Returns a table entry that has a `route` field matching the given value.
			
			Parameters:
				route - A string to check the `route` field for.
			
			Returns:
				An entry from the table if one is found.
		*/
		
		function getByRoute($route) {
			$item = sqlfetch(sqlquery("SELECT * FROM `".$this->Table."` WHERE route = '".sqlescape($route)."'"));

			if (!$item) {
				return false;
			} else {
				return $this->get($item);
			}
		}
		
		/*
			Function: getFeatured
				Returns featured entries from the table.
			
			Parameters:
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				limit - Max number of entries to return, defaults to all
			
			Returns:
				An array of entries from the table.
				
			See Also:
				<getMatching>
		*/
		
		function getFeatured($order = false,$limit = false) {
			return $this->getMatching("featured","on",$order,$limit);
		}
		
		/*
			Function: getInfo
				Returns information about a given entry from the module.

			Parameters:
				entry - An entry from this module or an id

			Returns:
				An array of keyed information:
					"created_at" - A datestamp of the created date/time
					"updated_at" - A datestamp of the last updated date/time
					"creator" - The original creator of this entry (the user's ID)
					"last_updated_by" - The last user to update this entry (the user's ID)
					"status" - Whether this entry has pending changes "changed" or not "published"
		*/

		function getInfo($entry) {
			$info = array();
			$base = "SELECT * FROM bigtree_audit_trail WHERE `table` = '".$this->Table."' AND entry = '$entry'";
			if (is_array($entry)) {
				$entry = sqlescape($entry["id"]);
			} else {
				$entry = sqlescape($entry);
			}

			$created = sqlfetch(sqlquery($base." AND type = 'created'"));
			if ($created) {
				$info["created_at"] = $created["date"];
				$info["creator"] = $created["user"];
			}

			$updated = sqlfetch(sqlquery($base." AND type = 'updated' ORDER BY date DESC LIMIT 1"));
			if ($updated) {
				$info["updated_at"] = $updated["date"];
				$info["last_updated_by"] = $updated["user"];
			}
			
			$changed = sqlfetch(sqlquery($base." AND type = 'saved-draft' ORDER BY date DESC LIMIT 1"));
			if ($changed && strtotime($changed) > strtotime($info["updated_at"])) {
				$info["status"] = "changed";
			} else {
				$info["status"] = "published";
			}

			return $info;
		}
		
		/*
			Function: getMatching
				Returns entries from the table that match the key/value pairs.
			
			Parameters:
				fields - Either a single column key or an array of column keys (if you pass an array you must pass an array for values as well)
				values - Either a signle column value or an array of column values (if you pass an array you must pass an array for fields as well)
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				limit - Max number of entries to return, defaults to all
				exact - If you want exact matches for NULL, "", and 0, pass true, otherwise 0 = NULL = ""
			
			Returns:
				An array of entries from the table.
		*/
		
		function getMatching($fields,$values,$sortby = false,$limit = false,$exact = false) {
			if (!is_array($fields)) {
				$search = array($fields => $values);
			} else {
				$search = array_combine($fields,$values);
			}
			$where = array();
			foreach ($search as $key => $value) {
				if (!$exact && ($value === "NULL" || !$value)) {
					$where[] = "(`$key` IS NULL OR `$key` = '' OR `$key` = '0')";
				} else {
					$where[] = "`$key` = '".sqlescape($value)."'";
				}
			}
			
			return $this->fetch($sortby,$limit,implode(" AND ",$where));
		}
		
		/*
			Function: getNav
				An optional function to override in your module class.
				Provides additional navigation children when <BigTreeCMS.getNavByParent> is called on a page with a template that uses this module.
			
			Parameters:
				page - The page data for the current page the user is on.
			
			Returns:
				An array of arrays with "title" and "link" key/value pairs. Also accepts "children" for sending grandchildren as well.
		*/
		
		function getNav($page) {
			return array();
		}
		
		/*
			Function: getNonarchived
				Returns nonarchived entries from the table.
			
			Parameters:
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				limit - Max number of entries to return, defaults to all
			
			Returns:
				An array of entries from the table.
				
			See Also:
				<getMatching>
		*/
		
		function getNonarchived($order = false,$limit = false) {
			return $this->getMatching("archived","",$order,$limit);
		}
		
		/*
			Function: getPage
				Returns a page of entries from the table.
			
			Parameters:
				page - The page to return
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				perpage - The number of results per page (defaults to 15)
				where - Optional MySQL WHERE conditions
			
			Returns:
				Array of entries from the table.
			
			See Also:
				<getPageCount>
		*/
		
		function getPage($page = 1,$order = "id ASC",$perpage = 15,$where = false) {
			// Backwards compatibility with old argument order
			if (!is_numeric($perpage)) {
				$saved = $perpage;
				$perpage = $where;
				$where = $saved;
			}
			// Don't try to hit page 0.
			if ($page < 1) {
				$page = 1;
			}
			return $this->fetch($order,(($page - 1) * $perpage).", $perpage",$where);
		}
		
		/*
			Function: getPageCount
				Returns the number of pages of entries in the table.
			
			Parameters:
				perpage - The number of results per page (defaults to 15)
				where - Optional MySQL WHERE conditions
		
			Returns:
				The number of pages.
			
			See Also:
				<getPage>
		*/
		
		function getPageCount($perpage = 15,$where = false) {
			// Backwards compatibility with old argument order
			if (!is_numeric($perpage)) {
				$saved = $perpage;
				$perpage = is_numeric($where) ? $where : 15;
				$where = $saved;
			}
			if ($where) {
				$query = "SELECT id FROM `".$this->Table."` WHERE $where";
			} else {
				$query = "SELECT id FROM `".$this->Table."`";
			}			
			$pages = ceil(sqlrows(sqlquery($query)) / $perpage);
			if ($pages == 0) {
				$pages = 1;
			}
			return $pages;
		}
		
		/*
			Function: getPending
				Returns an entry from the table with pending changes applied.
			
			Parameters:
				id - The id of the entry in the table, or the id of the pending entry in bigtree_pending_changes prefixed with a "p"
			
			Returns:
				The entry from the table with pending changes applied.
		*/
		
		function getPending($id) {
			global $cms;
			
			$id = sqlescape($id);
			
			if (substr($id,0,1) == "p") {
				$f = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE id = '".substr($id,1)."'"));
				$item = json_decode($f["changes"],true);
				$item["id"] = $id;
			} else {
				$item = sqlfetch(sqlquery("SELECT * FROM `".$this->Table."` WHERE id = '$id'"));
				$c = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE item_id = '$id' AND `table` = '".$this->Table."'"));
				if ($c) {
					$changes = json_decode($c["changes"],true);
					foreach ($changes as $key => $val) {
						$item[$key] = $val;
					}
				}
			
			}
			
			// Translate it's roots and return it
			return $this->get($item);
		}
		
		/*
			Function: getRandom
				Returns a single (or many) random entries from the table.
			
			Parameters:
				count - The number of entries to return (if more than one).
			
			Returns:
				If "count" is passed, an array of entries from the table. Otherwise, a single entry from the table.
		*/
		
		function getRandom($count = false) {
			if ($count === false) {
				$f = sqlfetch(sqlquery("SELECT * FROM `".$this->Table."` ORDER BY RAND() LIMIT 1"));
				return $this->get($f);
			}
			return $this->fetch("RAND()",$count);
		}

		/*
			Function: getRecent
				Returns an array of entries from the table that have passed.
			
			Parameters:
				count - Number of entries to return.
				field - Field to use for the date check.
			
			Returns:
				An array of entries from the table.
			
			See Also:
				<getRecentFeatured>
		*/
		
		function getRecent($count = 5, $field = "date") {
			return $this->fetch("$field DESC",$count,"`$field` <= '".date("Y-m-d")."'");
		}

		/*
			Function: getRecentFeatured
				Returns an array of entries from the table that have passed and are featured.
			
			Parameters:
				count - Number of entries to return.
				field - Field to use for the date check.
			
			Returns:
				An array of entries from the table.
			
			See Also:
				<getRecent>
		*/
		
		function getRecentFeatured($count = 5, $field = "date") {
			return $this->fetch("$field ASC",$count,"featured = 'on' AND `$field` <= '".date("Y-m-d")."'");
		}
		
		/*
			Function: getRelatedByTags
				Returns relevant entries from the table that match the given tags.
			
			Parameters:
				tags - An array of tags to match against.
			
			Returns:
				An array of entries from the table sorted by most relevant to least.
		*/
		
		function getRelatedByTags($tags = array()) {
			$results = array();
			$relevance = array();
			foreach ($tags as $tag) {
				if (is_array($tag)) {
					$tag = $tag["tag"];
				}
				$tdat = sqlfetch(sqlquery("SELECT * FROM bigtree_tags WHERE tag = '".sqlescape($tag)."'"));
				if ($tdat) {
					$q = sqlquery("SELECT * FROM bigtree_tags_rel WHERE tag = '".$tdat["id"]."' AND `table` = '".sqlescape($this->Table)."'");
					while ($f = sqlfetch($q)) {
						$id = $f["entry"];
						if (in_array($id,$results)) {
							$relevance[$id]++;
						} else {
							$results[] = $id;
							$relevance[$id] = 1;
						}
					}
				}
			}
			array_multisort($relevance,SORT_DESC,$results);
			$items = array();
			foreach ($results as $result) {
				$items[] = $this->get($result);
			}
			return $items;
		}
		
		/*
			Function: getSitemap
				An optional function to override in your module class.
				Provides additional sitemap children when <BigTreeCMS.getNavByParent> is called on a page with a template that uses this module.
			
			Parameters:
				page - The page data for the current page the user is on.
			
			Returns:
				An array of arrays with "title" and "link" key/value pairs. Should not be a multi level array.
		*/
		
		function getSitemap($page) {
			return array();
		}
		
		/*
			Function: getTagsForItem
				Returns a list of tags the given table entry has been tagged with.
			
			Parameters:
				item - Either a table entry or the "id" of a table entry.
			
			Returns:
				An array of tags (strings).
		*/
		
		function getTagsForItem($item) {
			if (!is_numeric($item)) {
				$item = $item["id"];
			}
			
			$item = sqlescape($item);
			
			$q = sqlquery("SELECT bigtree_tags.tag FROM bigtree_tags JOIN bigtree_tags_rel ON bigtree_tags.id = bigtree_tags_rel.tag WHERE bigtree_tags_rel.`table` = '".sqlescape($this->Table)."' AND bigtree_tags_rel.entry = '$item' ORDER BY bigtree_tags.tag");

			$tags = array();
			while ($f = sqlfetch($q)) {
				$tags[] = $f["tag"];
			}
			
			return $tags;
		}

		/*
			Function: getUnarchived
				Returns entries that are not archived from the table.
				Equivalent to getNonarchived.
			
			Parameters:
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				limit - Max number of entries to return, defaults to all
			
			Returns:
				An array of entries from the table.
				
			See Also:
				<getMatching> <getNonarchived>
		*/
		
		function getUnarchived($order = false,$limit = false) {
			return $this->getMatching("archived","",$order,$limit);
		}

		/*
			Function: getUnapproved
				Returns unapproved entries from the table.
			
			Parameters:
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				limit - Max number of entries to return, defaults to all
			
			Returns:
				An array of entries from the table.
				
			See Also:
				<getMatching>
		*/
		
		function getUnapproved($order = false,$limit = false) {
			return $this->getMatching("approved","",$order,$limit);
		}
		
		/*
			Function: getUpcoming
				Returns an array of entries from the table that occur in the future.
			
			Parameters:
				count - Number of entries to return.
				field - Field to use for the date check.
			
			Returns:
				An array of entries from the table.
			
			See Also:
				<getUpcomingFeatured>
		*/
		
		function getUpcoming($count = 5, $field = "date") {
			return $this->fetch("$field ASC",$count,"`$field` >= '".date("Y-m-d")."'");
		}
		
		/*
			Function: getUpcomingFeatured
				Returns an array of entries from the table that occur in the future and are featured.
			
			Parameters:
				count - Number of entries to return.
				field - Field to use for the date check.
			
			Returns:
				An array of entries from the table.
			
			See Also:
				<getUpcoming>
		*/
		
		function getUpcomingFeatured($count = 5, $field = "date") {
			return $this->fetch("$field ASC",$count,"featured = 'on' AND `$field` >= '".date("Y-m-d")."'");
		}
		
		/*
			Function: save
				Saves the given entry back to the table.
			
			Parameters:
				item - A modified entry from the table.
				ignore_cache - If this is set to true, BigTree will not cache this entry in bigtree_module_view_cache - faster entry if you don't have an admin view (defaults to false)
							
			See Also:
				<add>
				<delete>
				<update>
		*/
		
		function save($item,$ignore_cache = false) {
			$id = $item["id"];
			unset($item["id"]);
			
			$keys = array_keys($item);
			$this->update($id,$keys,$item,$ignore_cache);
		}
		
		/*
			Function: search
				Returns an array of entries from the table with columns that match the search query.
			
			Parameters:
				query - A string to search for.
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				limit - Max entries to return (defaults to all)
				split_search - If set to true, splits the query into parts and searches each part (defaults to false).
				case_sensitive - Case sensitivity (defaults to false / the collation of the database).
			
			Returns:
				An array of entries from the table.
		*/
		
		function search($query,$order = false,$limit = false,$split_search = false,$case_sensitive = false) {
			$table_description = BigTree::describeTable($this->Table);
			$where = array();

			if ($split_search) {
				$pieces = explode(" ",$query);
				foreach ($pieces as $piece) {
					if ($piece) {
						$where_piece = array();
						foreach ($table_description["columns"] as $field => $parameters) {
							if ($case_sensitive) {
								$where_piece[] = "`$field` LIKE '%".sqlescape($piece)."%'";
							} else {
								$where_piece[] = "LOWER(`$field`) LIKE '%".sqlescape(strtolower($piece))."%'";
							}
						}
						$where[] = "(".implode(" OR ",$where_piece).")";
					}
				}
				return $this->fetch($order,$limit,implode(" AND ",$where));
			} else {
				foreach ($table_description["columns"] as $field => $parameters) {
					if ($case_sensitive) {
						$where[] = "`$field` LIKE '%".sqlescape($query)."%'";
					} else {
						$where[] = "LOWER(`$field`) LIKE '%".sqlescape(strtolower($query))."%'";
					}
				}
				return $this->fetch($order,$limit,implode(" OR ",$where));
			}
		}
		
		/*
			Function: setPosition
				Sets the position of a given entry.
			
			Parameters:
				item - The "id" of an entry or an entry from the table.
				position - The position to set. BigTree sorts by default as position DESC, id ASC.
		*/
		
		function setPosition($item,$position) {
			if (is_array($item)) {
				$item = $item["id"];
			}
			$this->update($item,"position",$position);
			BigTreeAutoModule::recacheItem($item,$this->Table);
		}
		
		/*
			Function: unapprove
				Unapproves a given entry.
			
			Parameters:
				item - The "id" of an entry or an entry from the table.
			
			See Also:
				<approve>
		*/
		
		function unapprove($item) {
			if (is_array($item)) {
				$item = $item["id"];
			}
			$this->update($item,"approved","");
			BigTreeAutoModule::recacheItem($item,$this->Table);
		}	
		
		/*
			Function: unarchive
				Unarchives a given entry.
			
			Parameters:
				item -  The "id" of an entry or an entry from the table.
			
			See Also:
				<archive>
		*/
		
		function unarchive($item) {
			if (is_array($item)) {
				$item = $item["id"];
			}
			$this->update($item,"archived","");
			BigTreeAutoModule::recacheItem($item,$this->Table);
		}
		
		/*
			Function: unfeature
				Unfeatures a given entry.
			
			Parameters:
				item - The "id" of an entry or an entry from the table.
			
			See Also:
				<feature>
		*/
		
		function unfeature($item) {
			if (is_array($item)) {
				$item = $item["id"];
			}
			$this->update($item,"featured","");
			BigTreeAutoModule::recacheItem($item,$this->Table);
		}
		
		/*
			Function: update
				Updates an entry in the table.
			
			Parameters:
				id - The "id" of the entry in the table.
				fields - Either a single column key or an array of column keys (if you pass an array you must pass an array for values as well)
				values - Either a signle column value or an array of column values (if you pass an array you must pass an array for fields as well)
				ignore_cache - If this is set to true, BigTree will not cache this entry in bigtree_module_view_cache - faster entry if you don't have an admin view (defaults to false)	
			
			See Also:
				<add>
				<delete>
				<save>
		*/
		
		function update($id,$fields,$values,$ignore_cache = false) {
			$admin = new BigTreeAdmin;
			$id = sqlescape($id);
			// Multiple columns to update			
			if (is_array($fields)) {
				$query_parts = array();
				foreach ($fields as $key) {
					$val = current($values);
					if (is_array($val)) {
						$val = json_encode(BigTree::translateArray($val));
					} else {
						$val = $admin->autoIPL($val);
					}
					$query_parts[] = "`$key` = '".sqlescape($val)."'";
					next($values);
				}
			
				sqlquery("UPDATE `".$this->Table."` SET ".implode(", ",$query_parts)." WHERE id = '$id'");
			// Single column to update
			} else {
				if (is_array($values)) {
					$val = json_encode(BigTree::translateArray($values));
				} else {
					$val = $admin->autoIPL($values);
				}
				sqlquery("UPDATE `".$this->Table."` SET `$fields` = '".sqlescape($val)."' WHERE id = '$id'");
			}
			if (!$ignore_cache) {
				BigTreeAutoModule::recacheItem($id,$this->Table);
			}
		}
	}
?>