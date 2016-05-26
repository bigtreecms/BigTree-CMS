<?php
	namespace BigTree;
	
	// If it's an AJAX request, get our data.
	if (isset($_POST["view"])) {
		$view = new ModuleView($_POST["view"]);
		$module = new Module($view->Module);
	}
	
	$module_permission = $module->UserAccessLevel;
	$module_page = ADMIN_ROOT.$module->Route."/";
	
	// Grouped views don't use pagination so we set it to 1000000
	$view->Settings["per_page"] = 1000000;
	$draggable = !empty($view->Settings["draggable"]) ? true : false;
	
	// Get search query info
	$query = "";
	if (!empty($_POST["search"]) || !empty($_GET["search"])) {
		$query = !empty($_GET["search"]) ? $_GET["search"] : $_POST["search"];
	}
	
	// Get sort column information
	if ($draggable) {
		$sort = "position DESC, id ASC";
	} else {
		if (isset($view->Settings["sort_field"])) {
			$sort = $view->Settings["sort_field"]." ".$view->Settings["sort_direction"];
		} elseif (isset($view->Settings["sort"])) {
			$sort = $view->Settings["sort"];
		} else {
			$sort = "id DESC";
		}
	}

	// Setup custom overrides for group titles when we're grouping by a special BigTree column
	$group_title_overrides = array();

	if ($view->Settings["group_field"] == "featured") {
		$group_title_overrides["on"] = "Featured";
		$group_title_overrides[""] = "Normal";
	} elseif ($view->Settings["group_field"] == "archived") {
		$group_title_overrides["on"] = "Archived";
		$group_title_overrides[""] = "Active";
	} elseif ($view->Settings["group_field"] == "approved") {
		$group_title_overrides["on"] = "Approved";
		$group_title_overrides[""] = "Not Approved";
	}
	
	// We're going to append information to the end of an edit string so that we can return to the same page / set of search results after submitting a form.
	$edit_append = "?view_data=".base64_encode(json_encode(array("view" => $view->ID, "search" => $query)));
	
	// Cache the data in case it's not there then grab groups from the cached data
	$view->cacheAllData();
	$groups = $view->Groups;
?>
<header>
	<?php
		$x = 0;
		foreach ($view->Fields as $key => $field) {
			$x++;
	?>
	<span class="view_column" style="width: <?=$field["width"]?>px;"><?=$field["title"]?></span>
	<?php
		}
	?>
	<span class="view_status"><?=Text::translate("Status")?></span>
	<span class="view_action" style="width: <?=(count($view->Actions) * 40)?>px;"><?php if (count($view->Actions) > 1) { echo Text::translate("Actions"); } ?></span>
</header>
<?php
	$gc = 0;
	foreach ($groups as $group => $title) {
		// If the group title contains the search phrase, show everything in that group.
		if (!$query || strpos(strtolower($title), strtolower($query)) !== false) {
			$search_in = "";
		} else {
			$search_in = $query;
		}
		
		$search = $view->searchData(1, $search_in, $sort, $group);
		
		if (count($search["results"])) {
			$gc++;
?>
<header class="group"><?=(isset($group_title_overrides[$title]) ? $group_title_overrides[$title] : $title)?></header>
<ul id="sort_table_<?=$gc?>">
	<?php 
		foreach ($search["results"] as $item) {
			if ($item["status"] == "p") {
				$status = "Pending";
				$status_class = "pending";
			} elseif ($item["status"] == "c") {
				$status = "Changed";
				$status_class = "pending";
			} else {
				$status = "Published";
				$status_class = "published";
			}

			$entry_permission = ($module_permission == "p") ? "p" : $module->getCachedAccessLevel($item, $view->Table);
	?>
	<li id="row_<?=$item["id"]?>" class="<?=$status_class?>">
		<?php
			$x = 0;
			foreach ($view->Fields as $key => $field) {
				$x++;
				$value = $item["column$x"];
		?>
		<section class="view_column" style="width: <?=$field["width"]?>px;">
			<?php if ($x == 1 && $module_permission == "p" && !$search && $draggable) { ?>
			<span class="icon_sort"></span>
			<?php } ?>
			<?=$value?>
		</section>
		<?php
			}
		?>
		<section class="view_status status_<?=$status_class?>"><?=$status?></section>
		<?php
			foreach ($view->Actions as $action => $data) {
				if ($data == "on") {
					if (($action == "delete" || $action == "approve" || $action == "feature" || $action == "archive") && $entry_permission != "p") {
						if ($action == "delete" && $item["pending_owner"] == $admin->ID) {
							$class = "icon_delete";
						} else {
							$class = "icon_disabled";
						}
					} else {
						$class = $view->generateActionClass($action, $item);
					}
					
					if ($action == "preview") {
						$link = rtrim($view->PreviewURL,"/")."/".$item["id"].'/" target="_preview';
					} elseif ($action == "edit") {
						$link = $view->EditURL.$item["id"]."/".$edit_append;
					} else {
						$link = "#".$item["id"];
					}
		?>
		<section class="view_action action_<?=$action?>"><a href="<?=$link?>" class="<?=$class?>" title="<?=ucwords($action)?>"></a></section>
		<?php
				} else {
					$data = json_decode($data,true);
					$link = $module_page.$data["route"]."/".$item["id"]."/";

					if ($data["function"]) {
						$link = call_user_func($data["function"],$item);
					}
		?>
		<section class="view_action"><a href="<?=$link?>" class="<?=$data["class"]?>" title="<?=Text::translate($data["name"], true)?>"></a></section>
		<?php
				}
			}
		?>
	</li>
	<?php } ?>
</ul>
<?php
		}
	}
