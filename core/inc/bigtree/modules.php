<?
	/*
		Class: BigTreeModule
			Base class from which all BigTree module classes inherit from.
	*/
	
	class BigTreeModule {
	
		var $Table = "";
		var $Module = "";
		
		/*
			Function: add
				Adds an entry to the table.
			
			Parameters:
				keys - The column names to add
				vals - The values for each of the columns
			
			Returns:
				The "id" of the new entry.
			
			See Also:
				<delete>
				<save>
				<update>
		*/
		
		function add($keys,$vals) {
			/* Prevent Duplicates! */
			$query = "SELECT id FROM `".$this->Table."` WHERE ";
			$kparts = array();
			$x = 0;
			while ($x < count($keys)) {
				$kparts[] = "`".$keys[$x]."` = '".sqlescape($vals[$x])."'";
				$x++;
			}
			$query .= implode(" AND ",$kparts);
			if (sqlrows(sqlquery($query))) {
				return false;
			}
			/* Done preventing dupes! */
			
			$query = "INSERT INTO `".$this->Table."` (";
			$kparts = array();
			$vparts = array();
			foreach ($keys as $key) {
				$kparts[] = "`".$key."`";
			}
			
			$query .= implode(",",$kparts).") VALUES (";

			foreach ($vals as $val) {
				$vparts[] = "'".sqlescape($val)."'";
			}
			
			$query .= implode(",",$vparts).")";
			sqlquery($query);
			
			$id = sqlid();
			BigTreeAutoModule::cacheNewItem($id,$this->Table);
			
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
				id - The "id" of the entry to delete.
			
			See Also:
				<add>
				<save>
				<update>
		*/
		
		function delete($id) {
			$id = sqlescape($id);
			sqlquery("DELETE FROM `".$this->Table."` WHERE id = '$id'");
			sqlquery("DELETE FROM bigtree_pending_changes WHERE `table` = '".$this->Table."' AND item_id = '$id'");
			BigTreeAutoModule::uncacheItem($id,$this->Table);
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
				sort - The sort order (in MySQL syntax, i.e. "id DESC")
		
			Returns:
				An array of items from the table.
		*/

		function getAll($sort = false) {
			$order_by = $sort ? "ORDER BY $sort" : "";
			$items = array();
			
			$q = sqlquery("SELECT * FROM `".$this->Table."` $order_by");
			while ($f = sqlfetch($q)) {
				$items[] = $this->get($f);
			}
			
			return $items;
		}
		
		/*
			Function: getAllPositioned
				Returns all entries from the table based on position.
			
			Returns:
				An array of entries from the table.
		*/
		
		function getAllPositioned() {
			return $this->fetch("position DESC, id ASC");
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
			Function: getMatching
				Returns entries from the table that match the key/value pairs.
			
			Parameters:
				fields - Either a single field key or an array of field keys (if you pass an array you must pass an array for values as well)
				values - Either a signle field value or an array of field values (if you pass an array you must pass an array for fields as well)
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				limit - Max number of entries to return, defaults to all
			
			Returns:
				An array of entries from the table.
		*/
		
		function getMatching($fields,$values,$sortby = false,$limit = false) {
			if (!is_array($fields)) {
				$where = "`$fields` = '".sqlescape($values)."'";
			} else {
				$x = 0;
				while ($x < count($fields)) {
					$where[] = "`".$fields[$x]."` = '".sqlescape($values[$x])."'";
					$x++;
				}
				$where = implode(" AND ",$where);
			}
			
			return $this->fetch($sortby,$limit,$where);
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
			Function: getPage
				Returns a page of entries from the table.
			
			Parameters:
				page - The page to return.
				orderby - The MySQL sort order.
				where - Optional MySQL WHERE conditions.
				perpage - The number of results per page.
			
			Returns:
				Array of entries from the table.
			
			See Also:
				<getPageCount>
		*/
		
		function getPage($page = 1,$orderby = "id ASC", $where = false, $perpage = 15) {
			return $this->fetch($orderby,(($page - 1) * $perpage).", $perpage",$where);
		}
		
		/*
			Function: getPageCount
				Returns the number of pages of entries in the table.
			
			Parameters:
				where - Optional MySQL WHERE conditions.
				perpage - The number of results per page.
		
			Returns:
				The number of pages.
			
			See Also:
				<getPage>
		*/
		
		function getPageCount($where = false, $perpage = 15) {
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
			
			foreach ($item as $key => $val) {
				$item[$key] = $cms->replaceInternalPageLinks($val);
			}
			
			return $item;
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
			return $this->fetch("rand()",$count);
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
				
			See Also:
				<add>
				<delete>
				<update>
		*/
		
		function save($item) {
			$id = $item["id"];
			unset($item["id"]);
			
			$keys = array_keys($item);
			$this->update($id,$keys,$item);
			BigTreeAutoModule::recacheItem($id,$this->Table);
		}
		
		/*
			Function: search
				Returns an array of entries from the table with columns that match the search query.
			
			Parameters:
				query - A string to search for.
				sortby - A MySQL sort parameter.
				limit - Max entries to return.
				case_sensitive - Case sensitivity (defaults to false).
			
			Returns:
				An array of entries from the table.
		*/
		
		function search($query,$sortby = false,$limit = false,$case_sensitive = false) {
			$table_description = BigTree::describeTable($this->Table);

			foreach ($table_description["columns"] as $field => $parameters) {
				if ($case_sensitive) {
					$where[] = "`$field` LIKE '%".sqlescape($query)."%'";
				} else {
					$where[] = "LOWER(`$field`) LIKE '%".sqlescape(strtolower($query))."%'";
				}
			}
			
			return $this->fetch($sortby,$limit,implode(" OR ",$where));
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
				keys - The column names to update.
				vals - The values to update the columns to.
			
			See Also:
				<add>
				<delete>
				<save>
		*/
		
		function update($id,$keys,$vals) {
			$id = sqlescape($id);
			$query = "UPDATE `".$this->Table."` SET ";
			
			if (is_array($keys)) {
				$kparts = array();
				foreach ($keys as $key) {
					$kparts[] = "`".$key."` = '".sqlescape(current($vals))."'";
					next($vals);
				}
			
				$query .= implode(", ",$kparts)." WHERE id = '$id'";
			} else {
				$query = "UPDATE `".$this->Table."` SET `$keys` = '".sqlescape($vals)."' WHERE id = '$id'";
			}
			
			sqlquery($query);
			BigTreeAutoModule::recacheItem($id,$this->Table);
		}
	}
?>