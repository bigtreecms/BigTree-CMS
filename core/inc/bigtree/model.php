<?php
	/*
		Class: BigTree\Model
			Provides an interface for working with module-based data.
	*/
	
	namespace BigTree;
	
	use BigTree, BigTreeAdmin, BigTreeAutoModule, SQL;
	use BigTree\GraphQL\TypeService;
	use BigTree\GraphQL\QueryService;
	use GraphQL\Type\Definition\ObjectType;
	use GraphQL\Type\Definition\Type;
	
	class Model {
		
		public static $GraphQLEnabled = false;
		public static $GraphQLType = null;
		public static $NavPosition = "bottom";
		public static $Table = "";
		
		/*
			Function: add
				Adds an entry to the table.
			
			Parameters:
				fields - Either a single column key or an array of column keys (if you pass an array you must pass an array for values as well) - Optionally this can be a key/value array and the values field kept false
				values - Either a signle column value or an array of column values (if you pass an array you must pass an array for fields as well)
				enforce_unique - Check to see if this entry is already in the database (prevent duplicates, defaults to false)
				ignore_cache - If this is set to true, BigTree will not cache this entry in bigtree_module_view_cache - faster entry if you don't have an admin view (defaults to false)
			
			Returns:
				The "id" of the new entry.
			
			See Also:
				<delete>
				<save>
				<update>
		*/
		
		public static function add($fields, $values = false, $enforce_unique = false, $ignore_cache = false) {
			$insert = [];
			
			// Single column/value add
			if (is_string($fields)) {
				$insert[$fields] = is_array($values) ? BigTree::translateArray($values) : BigTreeAdmin::autoIPL($values);
			// Multiple columns / values
			} else {
				// If we didn't pass in values (=== false) then we're using a key => value array
				if ($values === false) {
					$insert = BigTree::translateArray($fields);
				// Separate arrays for keys and values
				} else {
					$insert = array_combine($fields, BigTree::translateArray($values));
				}
			}
			
			// Prevent Duplicates
			if ($enforce_unique) {
				$where = [];
				
				foreach ($insert as $key => $value) {
					$where[] = "`$key` = '".SQL::escape($value)."'";
				}
				
				$id = SQL::fetchSingle("SELECT id FROM `".static::$Table."` WHERE ".implode(" AND ", $where));

				// If it's the same as an existing entry, return that entry's id
				if ($id) {
					return $id;
				}
			}
			
			// Add the entry and cache it.
			$id = SQL::insert(static::$Table, $insert);

			if (!$ignore_cache) {
				BigTreeAutoModule::cacheNewItem($id, static::$Table);
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
		
		public static function approve($item) {
			static::update(is_array($item) ? $item["id"] : $item, "approved", "on");
		}
		
		/*
			Function: archive
				Archives a given entry.
			
			Parameters:
				item - The "id" of an entry or an entry from the table.
			
			See Also:
				<unarchive>
		*/
		
		public static function archive($item) {
			static::update(is_array($item) ? $item["id"] : $item, "archived", "on");
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
		
		public static function delete($item) {
			$id = is_array($item) ? $item["id"] : $item;
			
			SQL::delete(static::$Table, $id);
			SQL::delete("bigtree_pending_changes", ["table" => static::$Table, "item_id" => $id]);
			BigTreeAutoModule::uncacheItem($id, static::$Table);
		}
		
		/*
			Function: feature
				Features a given entry.
			
			Parameters:
				item - The "id" of an entry or an entry from the table.
			
			See Also:
				<unfeature>
		*/
		
		public static function feature($item) {
			static::update(is_array($item) ? $item["id"] : $item, "featured", "on");
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
		
		public static function get($item) {
			if (!is_array($item)) {
				$item = SQL::fetch("SELECT * FROM `".static::$Table."` WHERE id = ?", $item);
			}
			
			if (!$item) {
				return null;
			}
			
			return BigTree::untranslateArray($item);
		}
		
		/*
			Function: getAll
				Returns all items from the table.
			
			Parameters:
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				columns - The columns to return (defaults to all)
		
			Returns:
				An array of items from the table.
		*/
		
		public static function getAll($order = false, $columns = false) {
			$columns = static::_getFetchableColumns($columns);
			$items = SQL::fetchAll("SELECT $columns FROM `".static::$Table."`".($order ? "ORDER BY $order" : ""));
			
			return array_map([static::class, "get"], $items);
		}
		
		/*
			Function: getAllPositioned
				Returns all entries from the table based on position.

			Parameters:
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				An array of entries from the table.
		*/
		
		public static function getAllPositioned($columns = false) {
			return static::getAll("position DESC, id ASC", $columns);
		}
		
		/*
			Function: getApproved
				Returns approved entries from the table.
			
			Parameters:
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				limit - Max number of entries to return, defaults to all
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				An array of entries from the table.
				
			See Also:
				<getMatching>
		*/
		
		public static function getApproved($order = false, $limit = false, $columns = false) {
			return static::getMatching("approved", "on", $order, $limit, false, $columns);
		}
		
		/*
			Function: getArchived
				Returns archived entries from the table.
			
			Parameters:
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				limit - Max number of entries to return, defaults to all
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				An array of entries from the table.
				
			See Also:
				<getMatching>
		*/
		
		public static function getArchived($order = false, $limit = false, $columns = false) {
			return static::getMatching("archived", "on", $order, $limit, false, $columns);
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
		
		public static function getBreadcrumb($page) {
			return [];
		}
		
		/*
			Function: getByRoute
				Returns a table entry that has a `route` field matching the given value.
			
			Parameters:
				route - A string to check the `route` field for.
			
			Returns:
				An entry from the table if one is found.
		*/
		
		public static function getByRoute($route) {
			$item = SQL::fetch("SELECT * FROM `".static::$Table."` WHERE `route` = ?", $route);
			
			return $item ? static::get($item) : null;
		}
		
		/*
			Function: getFeatured
				Returns featured entries from the table.
			
			Parameters:
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				limit - Max number of entries to return, defaults to all
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				An array of entries from the table.
				
			See Also:
				<getMatching>
		*/
		
		public static function getFeatured($order = false, $limit = false, $columns = false) {
			return static::getMatching("featured", "on", $order, $limit, false, $columns);
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
		
		public static function getInfo($entry) {
			$info = [];
			$id = is_array($entry) ? $entry["id"] : $entry;
			$base = "SELECT * FROM bigtree_audit_trail WHERE `table` = '".static::$Table."' AND entry = ?";

			$created = SQL::fetch($base." AND type = 'created'", $id);
			$updated = SQL::fetch($base." AND type = 'updated' ORDER BY date DESC LIMIT 1", $id);
			$changed = SQL::fetch($base." AND type = 'saved-draft' ORDER BY date DESC LIMIT 1", $id);
			
			if ($created) {
				$info["created_at"] = $created["date"];
				$info["creator"] = $created["user"];
			}
			
			if ($updated) {
				$info["updated_at"] = $updated["date"];
				$info["last_updated_by"] = $updated["user"];
			}
			
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
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				An array of entries from the table.
		*/
		
		public static function getMatching($fields, $values, $order = false, $limit = false, $exact = false, $columns = false) {
			if (!is_array($fields)) {
				$search = [$fields => $values];
			} else {
				$search = array_combine($fields, $values);
			}
			
			$where = [];
			$order = $order ? "ORDER BY $order" : "";
			$limit = $limit ? "LIMIT $limit" : " ";
			$columns = static::_getFetchableColumns($columns);

			foreach ($search as $key => $value) {
				if (!$exact && ($value === "NULL" || !$value)) {
					$where[] = "(`$key` IS NULL OR `$key` = '' OR `$key` = '0')";
				} else {
					$where[] = "`$key` = '".sqlescape($value)."'";
				}
			}
			
			$items = SQL::fetchAll("SELECT $columns FROM `".static::$Table."` WHERE ".implode(" AND ", $where)." $order $limit");
			
			return array_map([static::class, "get"], $items);
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
		
		public static function getNav($page) {
			return [];
		}
		
		/*
			Function: getNonarchived
				Returns nonarchived entries from the table.
			
			Parameters:
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				limit - Max number of entries to return, defaults to all
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				An array of entries from the table.
				
			See Also:
				<getMatching>
		*/
		
		public static function getNonarchived($order = false, $limit = false, $columns = false) {
			return static::getMatching("archived", "", $order, $limit, false, $columns);
		}
		
		/*
			Function: getPage
				Returns a page of entries from the table.
			
			Parameters:
				page - The page to return
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				per_page - The number of results per page (defaults to 15)
				where - Optional MySQL WHERE conditions
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				Array of entries from the table.
			
			See Also:
				<getPageCount>
		*/
		
		public static function getPage($page = 1, $order = "id ASC", $per_page = 15, $where = false, $columns = false) {
			$columns = static::_getFetchableColumns($columns);
			$page = ($page < 1) ? 1 : $page;
			$order = $order ? "ORDER BY $order" : "";
			$where = $where ? "WHERE $where" : "";
			$limit = "LIMIT ".(($page - 1) * $per_page).", $per_page";
			
			$items = SQL::fetchAll("SELECT $columns FROM `".static::$Table."` $where $order $limit");
			
			return array_map([static::class, "get"], $items);
		}
		
		/*
			Function: getPageCount
				Returns the number of pages of entries in the table.
			
			Parameters:
				per_page - The number of results per page (defaults to 15)
				where - Optional MySQL WHERE conditions
		
			Returns:
				The number of pages.
			
			See Also:
				<getPage>
		*/
		
		public static function getPageCount($per_page = 15, $where = false) {
			$where = $where ? "WHERE $where" : "";
			$count = SQL::fetchSingle("SELECT COUNT(*) FROM `".static::$Table."` $where");
			
			return ceil($count / $per_page) ?: 1;
		}
		
		/*
			Function: getPending
				Returns an entry from the table with pending changes applied.
			
			Parameters:
				id - The id of the entry in the table, or the id of the pending entry in bigtree_pending_changes prefixed with a "p"
			
			Returns:
				The entry from the table with pending changes applied.
		*/
		
		public static function getPending($id) {
			if (substr($id, 0, 1) == "p") {
				$pending_data = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", substr($id, 1));
				$item = json_decode($pending_data["changes"], true);
				$item["id"] = $id;
			} else {
				$item = SQL::fetch("SELECT * FROM `".static::$Table."` WHERE id = ?", $id);
				$pending_data = SQL::fetch("SELECT * FROM bigtree_pending_changes
											WHERE item_id = ? AND `table` = '".static::$Table."'", $id);
				
				if ($pending_data) {
					$changes = json_decode($pending_data["changes"], true);
					$item = array_merge($item, $changes);
				}
			}
			
			return static::get($item);
		}
		
		/*
			Function: getRandom
				Returns a single (or many) random entries from the table.
			
			Parameters:
				count - The number of entries to return (if more than one).
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				If "count" is passed, an array of entries from the table. Otherwise, a single entry from the table.
		*/
		
		public static function getRandom($count = false, $columns = false) {
			$columns = static::_getFetchableColumns($columns);
			
			if ($count === false) {
				return static::get(SQL::fetch("SELECT $columns FROM `".static::$Table."` ORDER BY RAND() LIMIT 1"));
			}
			
			$items = SQL::fetchAll("SELECT $columns FROM `".static::$Table."` ORDER BY RAND() LIMIT $count");
			
			return array_map([static::class, "get"], $items);
		}
		
		/*
			Function: getRecent
				Returns an array of entries from the table that have passed.
			
			Parameters:
				count - Number of entries to return.
				field - Field to use for the date check.
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				An array of entries from the table.
			
			See Also:
				<getRecentFeatured>
		*/
		
		public static function getRecent($count = 5, $field = "date", $columns = false) {
			$columns = static::_getFetchableColumns($columns);
			$items = SQL::fetchAll("SELECT $columns FROM `".static::$Table."`
									WHERE DATE(`$field`) <= DATE(NOW())
									ORDER BY `$field` DESC
									LIMIT $count");
			
			return array_map([static::class, "get"], $items);
		}
		
		/*
			Function: getRecentFeatured
				Returns an array of entries from the table that have passed and are featured.
			
			Parameters:
				count - Number of entries to return.
				field - Field to use for the date check.
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				An array of entries from the table.
			
			See Also:
				<getRecent>
		*/
		
		public static function getRecentFeatured($count = 5, $field = "date", $columns = false) {
			$columns = static::_getFetchableColumns($columns);
			$items = SQL::fetchAll("SELECT $columns FROM `".static::$Table."`
									WHERE DATE(`$field`) <= DATE(NOW()) AND `featured` = 'on'
									ORDER BY `$field` DESC
									LIMIT $count");
			
			return array_map([static::class, "get"], $items);
		}
		
		/*
			Function: getRelatedByTags
				Returns relevant entries from the table that match the given tags.
				Entries are given a _relevance key based on the number of matches.
			
			Parameters:
				tags - An array of tags to match against.
			
			Returns:
				An array of entries from the table sorted by most relevant to least.
		*/
		
		public static function getRelatedByTags($tags = []) {
			$items = [];
			
			// Get the IDs of any string based tags
			$tags = array_filter(array_map(function($item) {
				return is_array($item) ? $item["id"] :
					SQL::fetchSingle("SELECT `id` FROM `bigtree_tags` WHERE tag = ?", $item);
			}, $tags));
			
			// Bring the related items in sorted by most matches for tags first
			$related = SQL::fetchAll("SELECT COUNT(*) AS `count`, `entry` FROM bigtree_tags_rel
								 	  WHERE `tag` IN (".implode(",", $tags).")
										AND `table` = '".static::$Table."'
									  GROUP BY `entry`
									  ORDER BY `count` DESC");
			
			foreach ($related as $result) {
				$item = static::get($result["entry"]);
				$item["_relevance"] = $result["count"];
				$items[] = $item;
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
		
		public static function getSitemap($page) {
			return [];
		}
		
		/*
			Function: getTagsForItem
				Returns a list of tags the given table entry has been tagged with.
			
			Parameters:
				item - Either a table entry or the "id" of a table entry.
				full - Whether to return a full tag array or only the tag string (defaults to only the tag string)
			
			Returns:
				An array of tags (strings).
		*/
		
		public static function getTagsForItem($item, $full = false) {
			if (!is_numeric($item)) {
				$item = $item["id"];
			}
			
			if ($full) {
				return SQL::fetchAll("SELECT bigtree_tags.* FROM bigtree_tags JOIN bigtree_tags_rel
									  ON bigtree_tags_rel.tag = bigtree_tags.id
									  WHERE bigtree_tags_rel.`table` = ?
										AND bigtree_tags_rel.`entry` = ?
						  			  ORDER BY bigtree_tags.tag ASC", static::$Table, $item);
			}
			
			return SQL::fetchAllSingle("SELECT bigtree_tags.tag FROM bigtree_tags JOIN bigtree_tags_rel
										ON bigtree_tags_rel.tag = bigtree_tags.id
										WHERE bigtree_tags_rel.`table` = ?
										  AND bigtree_tags_rel.`entry` = ?
						  				ORDER BY bigtree_tags.tag ASC", static::$Table, $item);
		}
		
		/*
			Function: getUnarchived
				Returns entries that are not archived from the table.
				Equivalent to getNonarchived.
			
			Parameters:
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				limit - Max number of entries to return, defaults to all
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				An array of entries from the table.
				
			See Also:
				<getMatching> <getNonarchived>
		*/
		
		public static function getUnarchived($order = false, $limit = false, $columns = false) {
			return static::getMatching("archived", "", $order, $limit, false, $columns);
		}
		
		/*
			Function: getUnapproved
				Returns unapproved entries from the table.
			
			Parameters:
				order - The sort order (in MySQL syntax, i.e. "id DESC")
				limit - Max number of entries to return, defaults to all
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				An array of entries from the table.
				
			See Also:
				<getMatching>
		*/
		
		public static function getUnapproved($order = false, $limit = false, $columns = false) {
			return static::getMatching("approved", "", $order, $limit, false, $columns);
		}
		
		/*
			Function: getUpcoming
				Returns an array of entries from the table that occur in the future ordered by soonest first.
			
			Parameters:
				count - Number of entries to return.
				field - Field to use for the date check.
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				An array of entries from the table.
			
			See Also:
				<getUpcomingFeatured>
		*/
		
		public static function getUpcoming($count = 5, $field = "date", $columns = false) {
			$columns = static::_getFetchableColumns($columns);
			$items = SQL::fetchAll("SELECT $columns FROM `".static::$Table."`
									WHERE DATE(`$field`) >= DATE(NOW())
									ORDER BY `$field` ASC
									LIMIT $count");
			
			return array_map([static::class, "get"], $items);
		}
		
		/*
			Function: getUpcomingFeatured
				Returns an array of entries from the table that occur in the future and are featured.
			
			Parameters:
				count - Number of entries to return.
				field - Field to use for the date check.
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				An array of entries from the table.
			
			See Also:
				<getUpcoming>
		*/
		
		public static function getUpcomingFeatured($count = 5, $field = "date", $columns = false) {
			$columns = static::_getFetchableColumns($columns);
			$items = SQL::fetchAll("SELECT $columns FROM `".static::$Table."`
									WHERE DATE(`$field`) >= DATE(NOW()) AND featured = 'on'
									ORDER BY `$field` ASC
									LIMIT $count");
			
			return array_map([static::class, "get"], $items);
		}
		
		/*
			Function: registerGraphQLMethods
				Registers GraphQL methods for use with the GraphQL API.
				Methods should be uniquely keyed across all modules and match graphql-php syntax.
		*/
		
		public static function registerGraphQLMethods() {
			$type = static::$GraphQLType ?: static::class;
			
			QueryService::register("query", [
				"get$type" => [
					"type" => Type::listOf(TypeService::get($type)),
					"args" => [
						"sort_field" => Type::string(),
						"sort_direction" => Type::string(),
						"per_page" => Type::int(),
						"page" => Type::int()
					],
					"resolve" => function ($root, $args, $context) {
						$order = "";
						$limit = "";
						
						if (!empty($args["sort_field"])) {
							// Validate field against TypeService
							$type = TypeService::get(static::$GraphQLType ?: static::class);
							
							if (isset($type->config["fields"][$args["sort_field"]])) {
								$sort_direction = strtolower(!empty($args["sort_direction"]) ? $args["sort_direction"] : "ASC");
								
								if ($sort_direction != "asc" && $sort_direction != "desc") {
									$sort_direction = "asc";
								}
								
								$order = "ORDER BY `".$args["sort_field"]."` $sort_direction";
							}
						}
						
						if (!empty($args["per_page"])) {
							$page = !empty($args["page"]) ? intval($args["page"]) : 1;
							$per_page = intval($args["per_page"]);
							$limit = "LIMIT ".(($page - 1) * $per_page).", $per_page";
						}
						
						return SQL::fetchAll("SELECT * FROM ".static::$Table." $order $limit");
					}
				]
			]);
		}
		
		/*
			Function: registerGraphQLTypes
				Registers GraphQL object types for use with the GraphQL API.
				Types should be uniquely keyed across all modules and match graphql-php syntax.
		*/
		
		public static function registerGraphQLTypes() {
			$fields = [];
			$db_schema = SQL::describeTable(static::$Table);
			
			foreach ($db_schema["columns"] as $id => $column) {
				$type = Type::string();
				
				if (!empty($column["auto_increment"])) {
					$type = Type::id();
				} elseif (in_array($column["type"], TypeService::$ScalarTypeRefs["int"])) {
					$type = Type::int();
				} elseif (in_array($column["type"], TypeService::$ScalarTypeRefs["float"])) {
					$type = Type::float();
				} elseif (in_array($column["type"], TypeService::$ScalarTypeRefs["boolean"])) {
					$type = Type::boolean();
				}
				
				$fields[$id] = $type;
			}
			
			TypeService::set(static::$GraphQLType ?: static::class, new ObjectType([
				"name" => static::$GraphQLType ?: static::class,
				"fields" => $fields
			]));
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
		
		public static function save($item, $ignore_cache = false) {
			$id = $item["id"];
			unset($item["id"]);
			
			static::update($id, array_keys($item), $item, $ignore_cache);
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
				columns - The columns to retrieve (defaults to all)
			
			Returns:
				An array of entries from the table.
		*/
		
		public static function search($query, $order = false, $limit = false, $split_search = false, $case_sensitive = false, $columns = false) {
			$table_description = BigTree::describeTable(static::$Table);
			$columns = static::_getFetchableColumns($columns);
			$order = $order ? "ORDER BY $order" : "";
			$limit = $limit ? "LIMIT $limit" : "";
			$where = [];
			
			if ($split_search) {
				$pieces = explode(" ", $query);
				foreach ($pieces as $piece) {
					if ($piece) {
						$where_piece = [];
						
						foreach ($table_description["columns"] as $field => $parameters) {
							if ($case_sensitive) {
								$where_piece[] = "`$field` LIKE '%".SQL::escape($piece)."%'";
							} else {
								$where_piece[] = "LOWER(`$field`) LIKE '%".SQL::escape(strtolower($piece))."%'";
							}
						}
						
						$where[] = "(".implode(" OR ", $where_piece).")";
					}
				}
			} else {
				foreach ($table_description["columns"] as $field => $parameters) {
					if ($case_sensitive) {
						$where[] = "`$field` LIKE '%".SQL::escape($query)."%'";
					} else {
						$where[] = "LOWER(`$field`) LIKE '%".SQL::escape(strtolower($query))."%'";
					}
				}
			}
			
			$items = SQL::fetchAll("SELECT $columns FROM `".static::$Table."`
									WHERE ".implode(" OR ", $where)." $order $limit");
			
			return array_map([static::class, "get"], $items);
		}
		
		/*
			Function: setPosition
				Sets the position of a given entry.
			
			Parameters:
				item - The "id" of an entry or an entry from the table.
				position - The position to set. BigTree sorts by default as position DESC, id ASC.
		*/
		
		public static function setPosition($item, $position) {
			static::update(is_array($item) ? $item["id"] : $item, "position", $position);
		}
		
		/*
			Function: unapprove
				Unapproves a given entry.
			
			Parameters:
				item - The "id" of an entry or an entry from the table.
			
			See Also:
				<approve>
		*/
		
		public static function unapprove($item) {
			static::update(is_array($item) ? $item["id"] : $item, "approved", "");
		}
		
		/*
			Function: unarchive
				Unarchives a given entry.
			
			Parameters:
				item -  The "id" of an entry or an entry from the table.
			
			See Also:
				<archive>
		*/
		
		public static function unarchive($item) {
			static::update(is_array($item) ? $item["id"] : $item, "archived", "");
		}
		
		/*
			Function: unfeature
				Unfeatures a given entry.
			
			Parameters:
				item - The "id" of an entry or an entry from the table.
			
			See Also:
				<feature>
		*/
		
		public static function unfeature($item) {
			static::update(is_array($item) ? $item["id"] : $item, "featured", "");
		}
		
		/*
			Function: update
				Updates an entry in the table.
			
			Parameters:
				id - The "id" of the entry in the table.
				fields - Either a single column key or an array of column keys (if you pass an array you must pass an array for values as well) — Optionally this can be a key/value array and the values field kept false
				values - Either a signle column value or an array of column values (if you pass an array you must pass an array for fields as well)
				ignore_cache - If this is set to true, BigTree will not cache this entry in bigtree_module_view_cache - faster entry if you don't have an admin view (defaults to false)
			
			See Also:
				<add>
				<delete>
				<save>
		*/
		
		public static function update($id, $fields, $values = false, $ignore_cache = false) {
			if (is_array($fields)) {
				$data = ($values === false) ? $fields : array_combine($fields, $values);
			} else {
				$data = [];
				$data[$fields] = $values;
			}
			
			$data = BigTree::translateArray($data);
			
			SQL::update(static::$Table, $id, $data);

			if (!$ignore_cache) {
				BigTreeAutoModule::recacheItem($id, static::$Table);
			}
		}
		
		protected static function _getFetchableColumns($columns) {
			$fetchable_columns = "*";
			
			if (!empty($columns) && is_array($columns)) {
				$fetchable_columns = [];
				
				foreach ($columns as $column) {
					$fetchable_columns[] = "`".$column."`";
				}
				
				$fetchable_columns = implode(", ", $fetchable_columns);
			}
			
			return $fetchable_columns;
		}
		
	}
