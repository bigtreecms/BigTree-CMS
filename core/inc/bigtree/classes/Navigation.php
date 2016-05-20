<?php
	/*
		Class: BigTree\Navigation
			Handles methods related to returning page tree navigation.
	*/

	namespace BigTree;

	class Navigation {

		/*
			Function: getLevel
				Returns a navigation array of pages visible in navigation.

			Parameters:
				parent - Either a single page ID or an array of page IDs -- the latter is used internally
				depth - The number of levels of navigation depth to recurse
				follow_module - Whether to pull module navigation or not (defaults to true)

			Returns:
				A navigation array containing "id", "parent", "title", "route", "link", "new_window", and "children" (containing children if depth > 1)
		*/

		static function getLevel($parent = 0, $depth = 1, $follow_module = true, $only_hidden = false) {
			global $bigtree;
			static $module_nav_count = 0;

			$nav = array();
			$find_children = array();

			// If the parent is an array, this is actually a recursed call.
			// We're finding all the children of all the parents at once -- then we'll assign them back to the proper parent instead of doing separate calls for each.
			if (is_array($parent)) {
				$where_parent = array();

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
				if ($bigtree["config"]["trailing_slash_behavior"] == "remove") {
					$link = WWW_ROOT.$child["path"];
				} else {
					$link = WWW_ROOT.$child["path"]."/";
				}

				// If we're REALLY an external link we won't have a template, so let's get the real link and not the encoded version.
				// Then we'll see if we should open this thing in a new window.
				$new_window = false;

				if ($child["external"] && $child["template"] == "") {
					$link = Link::iplDecode($child["external"]);

					if ($child["new_window"]) {
						$new_window = true;
					}
				}

				// Add it to the nav array
				$nav[$child["id"]] = array(
					"id" => $child["id"],
					"parent" => $child["parent"],
					"title" => $child["nav_title"],
					"route" => $child["route"],
					"link" => $link,
					"new_window" => $new_window,
					"children" => array()
				);

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
					$where_parent = array();

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
								$module_nav = array();

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

		function getHidden($parent) {
			return $this->getLevel($parent, 1, false, true);
		}
		
	}
