<?php
	/*
		Class: BigTree\Navigation
			Handles methods related to returning page tree navigation.
	*/
	
	namespace BigTree;
	
	use BigTreeCMS;
	
	class Navigation {
		
		public static $Trunk = false;
		
		/*
			Function: getBreadcrumb
				Returns an array of titles, links, and ids for the pages above the given page.

			Parameters:
				page - A page object or array (containing at least the "path" from the database)
				ignore_trunk - Ignores trunk settings when returning the breadcrumb

			Returns:
				An array of arrays with "title", "link", and "id" of each of the pages above the current (or passed in) page.
				If a trunk is hit, BigTree\Navigation::$Trunk is set to the trunk.

			See Also:
				<getBreadcrumb>
		*/
		
		static function getBreadcrumb($page, bool $ignore_trunk = false): array {
			global $bigtree;
			
			if (is_object($page)) {
				$page = $page->Array;
			}
			
			$bc = [];
			
			// Break up the pieces so we can get each piece of the path individually and pull all the pages above this one.
			$pieces = explode("/", $page["path"]);
			$paths = [];
			$path = "";
			
			foreach ($pieces as $piece) {
				$path = $path.$piece."/";
				$paths[] = "path = '".SQL::escape(trim($path, "/"))."'";
			}
			
			// Get all the ancestors, ordered by the page length so we get the latest first and can count backwards to the trunk.
			$ancestors = SQL::fetchAll("SELECT id, nav_title, path, trunk FROM bigtree_pages 
										WHERE (".implode(" OR ", $paths).") ORDER BY LENGTH(path) DESC");
			$trunk_hit = false;
			
			foreach ($ancestors as $ancestor) {
				// In case we want to know what the trunk is.
				if ($ancestor["trunk"] || $ancestor["id"] === BIGTREE_SITE_TRUNK) {
					$trunk_hit = true;
					BigTreeCMS::$BreadcrumbTrunk = $ancestor;
					Navigation::$Trunk = $ancestor;
				}
				
				if (!$trunk_hit || $ignore_trunk) {
					$bc[] = [
						"title" => stripslashes($ancestor["nav_title"]),
						"link" => Link::byPath($ancestor["path"]),
						"id" => $ancestor["id"]
					];
				}
			}
			
			$bc = array_reverse($bc);
			
			// Check for module breadcrumbs
			$module_class = SQL::fetchSingle("SELECT bigtree_modules.class
											  FROM bigtree_modules JOIN bigtree_templates
											  ON bigtree_modules.id = bigtree_templates.module
											  WHERE bigtree_templates.id = ?", $page["template"]);
			
			if ($module_class && class_exists($module_class)) {
				$module = new $module_class;
				
				if (method_exists($module, "getBreadcrumb")) {
					$bc = array_merge($bc, $module->getBreadcrumb($page, array_filter((array) $bigtree["routed_path"])));
				}
			}
			
			return $bc;
		}
		
		/*
			Function: getLevel
				Returns a navigation array of pages visible in navigation.

			Parameters:
				parent - Either a single page ID or an array of page IDs -- the latter is used internally
				depth - The number of levels of navigation depth to recurse
				follow_module - Whether to pull module navigation or not (defaults to true)
				only_hidden - Whether to pull visible (false) or hidden (true) pages
				explicit_zero - In a multi-site environment you must pass true for this parameter if you want root level children rather than the site-root level

			Returns:
				A navigation array containing "id", "parent", "title", "route", "link", "new_window", and "children" (containing children if depth > 1)
		*/
		
		static function getLevel(int $parent = 0, int $depth = 1, bool $follow_module = true, bool $only_hidden = false,
								 bool $explicit_zero = false): array {
			static $module_nav_count = 0;
			
			$nav = [];
			$find_children = [];
			
			// If we're asking for root (0) and in multi-site, use that site's root instead of the top-level root
			if (!$explicit_zero && $parent === 0 && BIGTREE_SITE_TRUNK !== 0) {
				$parent = BIGTREE_SITE_TRUNK;
			}
			
			// If the parent is an array, this is actually a recursed call.
			// We're finding all the children of all the parents at once -- then we'll assign them back to the proper parent instead of doing separate calls for each.
			if (is_array($parent)) {
				$where_parent = [];
				
				foreach ($parent as $page_id) {
					$where_parent[] = "parent = '".SQL::escape($page_id)."'";
				}
				
				$where_parent = "(".implode(" OR ", $where_parent).")";
			} else {
				// If it's an integer, let's just pull the children for the provided parent.
				$parent = SQL::escape($parent);
				$where_parent = "parent = '$parent'";
			}
			
			if ($only_hidden) {
				$in_nav = "";
				$sort = "nav_title ASC";
			} else {
				$in_nav = "on";
				$sort = "position DESC, id ASC";
			}
			
			$children = SQL::fetchAll("SELECT id,nav_title,parent,external,new_window,template,route,path 
									   FROM bigtree_pages
									   WHERE $where_parent 
										 AND in_nav = '$in_nav'
										 AND archived != 'on'
										 AND (publish_at <= NOW() OR publish_at IS NULL) 
										 AND (expire_at >= NOW() OR expire_at IS NULL) 
									   ORDER BY $sort");
			
			// Wrangle up some kids
			foreach ($children as $child) {
				// If we're REALLY an external link we won't have a template, so let's get the real link and not the encoded version.
				// Then we'll see if we should open this thing in a new window.
				$new_window = false;
				
				if ($child["external"] && $child["template"] == "") {
					$link = Link::iplDecode($child["external"]);
					
					if ($child["new_window"]) {
						$new_window = true;
					}
				} else {
					$link = Link::byPath($child["path"]);
				}
				
				// Add it to the nav array
				$nav[$child["id"]] = [
					"id" => $child["id"],
					"parent" => $child["parent"],
					"title" => $child["nav_title"],
					"route" => $child["route"],
					"link" => $link,
					"new_window" => $new_window,
					"children" => []
				];
				
				// If we're going any deeper, mark down that we're looking for kids of this kid.
				if ($depth > 1) {
					$find_children[] = $child["id"];
				}
			}
			
			// If we're looking for children, send them all back into getNavByParent, decrease the depth we're looking for by one.
			if (count($find_children)) {
				$subnav = static::getLevel($find_children, $depth - 1, $follow_module);
				
				foreach ($subnav as $item) {
					// Reassign these new children back to their parent node.
					$nav[$item["parent"]]["children"][$item["id"]] = $item;
				}
			}
			
			// If we're pulling in module navigation...
			if ($follow_module) {
				// This is a recursed iteration.
				if (is_array($parent)) {
					$where_parent = [];
					
					foreach ($parent as $p) {
						$where_parent[] = "bigtree_pages.id = '".SQL::escape($p)."'";
					}
					
					$module_pages = SQL::fetchAll("SELECT bigtree_modules.class,
														  bigtree_templates.routed,
														  bigtree_templates.module,
														  bigtree_pages.id,
														  bigtree_pages.path,
														  bigtree_pages.template
												   FROM bigtree_modules JOIN bigtree_templates JOIN bigtree_pages 
												   ON bigtree_templates.id = bigtree_pages.template 
												   WHERE bigtree_modules.id = bigtree_templates.module 
													 AND (".implode(" OR ", $where_parent).")");
					
					foreach ($module_pages as $module_page) {
						// If the class exists, instantiate it and call it
						if ($module_page["class"] && class_exists($module_page["class"])) {
							$module = new $module_page["class"];
							
							if (method_exists($module, "getNav")) {
								$modNav = $module->getNav($module_page);
								
								// Give the parent back to each of the items it returned so they can be reassigned to the proper parent.
								$module_nav = [];
								
								foreach ($modNav as $item) {
									$item["parent"] = $module_page["id"];
									$item["id"] = "module_nav_".$module_nav_count;
									$module_nav[] = $item;
									$module_nav_count++;
								}
								
								if ($module->NavPosition == "top") {
									$nav = array_merge($module_nav, $nav);
								} else {
									$nav = array_merge($nav, $module_nav);
								}
							}
						}
					}
				} else {
					// This is the first iteration.
					$module_page = SQL::fetch("SELECT bigtree_modules.class,
													  bigtree_templates.routed,
													  bigtree_templates.module,
													  bigtree_pages.id,
													  bigtree_pages.path,
													  bigtree_pages.template 
											   FROM bigtree_modules JOIN bigtree_templates JOIN bigtree_pages 
											   ON bigtree_templates.id = bigtree_pages.template 
											   WHERE bigtree_modules.id = bigtree_templates.module 
											   	 AND bigtree_pages.id = ?", $parent);
					
					// If the class exists, instantiate it and call it.
					if ($module_page["class"] && class_exists($module_page["class"])) {
						$module = new $module_page["class"];
						
						if (method_exists($module, "getNav")) {
							if ($module->NavPosition == "top") {
								$nav = array_merge($module->getNav($module_page), $nav);
							} else {
								$nav = array_merge($nav, $module->getNav($module_page));
							}
						}
					}
				}
			}
			
			return $nav;
		}
		
		/*
			Function: getHidden
				Returns a navigation array of pages hidden from navigation below the given parent.

			Parameters:
				parent - A page ID

			Returns:
				A navigation array containing "id", "parent", "title", "route", "link", and "new_window"
		*/
		
		function getHidden(int $parent): array {
			return $this->getLevel($parent, 1, false, true);
		}
		
	}
