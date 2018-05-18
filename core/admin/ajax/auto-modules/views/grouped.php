<?php
	// If it's an AJAX request, get our data.
	if (isset($_POST["view"])) {
		$bigtree["view"] = BigTreeAutoModule::getView($_POST["view"]);
		$bigtree["module"] = $admin->getModule(BigTreeAutoModule::getModuleForView($bigtree["view"]));
	}
	
	BigTree::globalizeArray($bigtree["view"]);
	
	$permission = $admin->getAccessLevel($bigtree["module"]["id"]);
	$module_page = ADMIN_ROOT.$bigtree["module"]["route"]."/";

	// Defaults
	$search = isset($_POST["search"]) ? $_POST["search"] : "";
	$search = isset($_GET["search"]) ? $_GET["search"] : $search;
	$draggable = (isset($bigtree["view"]["settings"]["draggable"]) && $bigtree["view"]["settings"]["draggable"]) ? true : false;
	$bigtree["view"]["settings"]["per_page"] = 10000;
	
	if (isset($bigtree["view"]["settings"]["sort_field"])) {
		$sort = $bigtree["view"]["settings"]["sort_field"]." ".$bigtree["view"]["settings"]["sort_direction"];
	} elseif (isset($bigtree["view"]["settings"]["sort"])) {
		$sort = $bigtree["view"]["settings"]["sort"];
	} else {
		$sort = "id DESC";
	}
	
	if ($draggable) {
		$sort = "position DESC, id ASC";
	}
	
	// Setup the preview action if we have a preview URL and field.
	if ($bigtree["view"]["preview_url"]) {
		$actions["preview"] = "on";
	}

	// Setup custom overrides for group titles when we're grouping by a special BigTree column
	$group_title_overrides = array();
	if ($bigtree["view"]["settings"]["group_field"] == "featured") {
		$group_title_overrides["on"] = "Featured";
		$group_title_overrides[""] = "Normal";
	} elseif ($bigtree["view"]["settings"]["group_field"] == "archived") {
		$group_title_overrides["on"] = "Archived";
		$group_title_overrides[""] = "Active";
	} elseif ($bigtree["view"]["settings"]["group_field"] == "approved") {
		$group_title_overrides["on"] = "Approved";
		$group_title_overrides[""] = "Not Approved";
	}
	
	// We're going to append information to the end of an edit string so that we can return to the same page / set of search results after submitting a form.
	$edit_append = "?view_data=".base64_encode(json_encode(array("view" => $bigtree["view"]["id"], "search" => $search)));
	
	// Cache the data in case it's not there.
	BigTreeAutoModule::cacheViewData($bigtree["view"]);
	
	$groups = BigTreeAutoModule::getGroupsForView($bigtree["view"]);
?>
<header>
	<?php
		$x = 0;
		foreach ($fields as $key => $field) {
			$x++;
	?>
	<span class="view_column" style="width: <?=$field["width"]?>px;"><?=$field["title"]?></span>
	<?php
		}
	?>
	<span class="view_status">Status</span>
	<span class="view_action" style="width: <?=(count($bigtree["view"]["actions"]) * 40)?>px;"><?php if (count($bigtree["view"]["actions"]) > 1) { ?>Actions<?php } ?></span>
</header>
<?php
	$gc = 0;
	foreach ($groups as $group => $title) {
		// If the group title contains the search phrase, show everything in that group.
		if (!$search || strpos(strtolower($title),strtolower($search)) !== false) {
			$search_in = "";
		} else {
			$search_in = $search;
		}
		
		$r = BigTreeAutoModule::getSearchResults($bigtree["view"],1,$search_in,$sort,$group);
		
		if (count($r["results"])) {
			$gc++;
?>
<header class="group"><?=(isset($group_title_overrides[$title]) ? $group_title_overrides[$title] : $title)?></header>
<ul id="sort_table_<?=$gc?>">
	<?php 
		foreach ($r["results"] as $item) {
			if ($item["status"] == "p") {
				$status = "Pending";
				$status_class = "pending";
			} elseif ($item["status"] == "c") {
				$status = "Changed";
				$status_class = "pending";
			} elseif ($item["status"] == "i") {
				$status = "Inactive";
				$status_class = "inactive";
			} else {
				$status = "Published";
				$status_class = "published";
			}
	?>
	<li id="row_<?=$item["id"]?>" class="<?=$status_class?>">
		<?php
			$x = 0;
			foreach ($fields as $key => $field) {
				$x++;
				$value = $item["column$x"];
		?>
		<section class="view_column" style="width: <?=$field["width"]?>px;">
			<?php if ($x == 1 && $permission == "p" && !$search && $draggable) { ?>
			<span class="icon_sort js-sort-hook"></span>
			<?php } ?>
			<?=$value?>
		</section>
		<?php
			}
		?>
		<section class="view_status status_<?=$status_class?>"><?=$status?></section>
		<?php
			$iperm = ($permission == "p") ? "p" : $admin->getCachedAccessLevel($bigtree["module"],$item,$bigtree["view"]["table"]);
			foreach ($actions as $action => $data) {
				if ($data == "on") {
					if (($action == "delete" || $action == "approve" || $action == "feature" || $action == "archive") && $iperm != "p") {
						if ($action == "delete" && $item["pending_owner"] == $admin->ID) {
							$class = "icon_delete js-delete-hook";
						} else {
							$class = "icon_disabled js-disabled-hook";
						}
					} else {
						$class = $admin->getActionClass($action,$item);
					}
					
					if ($action == "preview") {
						$link = rtrim($bigtree["view"]["preview_url"],"/")."/".$item["id"].'/" target="_preview';
					} elseif ($action == "edit") {
						$link = $bigtree["view"]["edit_url"].$item["id"]."/".$edit_append;
					} else {
						$link = "#".$item["id"];
					}

					$action_title = ucwords($action);

					if ($action == "archive" && $item["archived"]) {
						$action_title = "Restore";
					} elseif ($action == "feature" && $item["featured"]) {
						$action_title = "Unfeature";
					} elseif ($action == "approve" && $item["approved"]) {
						$action_title = "Unapprove";
					}
		?>
		<section class="view_action action_<?=$action?>"><a href="<?=$link?>" class="<?=$class?>" title="<?=$action_title?>"></a></section>
		<?php
				} else {
					$data = json_decode($data,true);
					$link = $module_page.$data["route"]."/".$item["id"]."/";

					if ($data["function"]) {
						$link = call_user_func($data["function"],$item);
					}

					$action_title = BigTree::safeEncode($data["name"]);
		?>
		<section class="view_action"><a href="<?=$link?>" class="<?=$data["class"]?>" title="<?=$action_title?>"></a></section>
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
?>	