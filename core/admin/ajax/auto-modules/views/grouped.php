<?
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
	$draggable = (isset($options["draggable"]) && $options["draggable"]) ? true : false;
	$bigtree["view"]["options"]["per_page"] = 10000;
	if (isset($options["sort_field"])) {
		$sort = $options["sort_field"]." ".$options["sort_direction"];
	} elseif (isset($options["sort"])) {
		$sort = $options["sort"];
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
	
	
	// We're going to append information to the end of an edit string so that we can return to the same page / set of search results after submitting a form.
	$edit_append = "?view_data=".base64_encode(serialize(array("view" => $bigtree["view"]["id"], "search" => $search)));
	
	// Cache the data in case it's not there.
	BigTreeAutoModule::cacheViewData($bigtree["view"]);
	
	$groups = BigTreeAutoModule::getGroupsForView($bigtree["view"]);
?>
<header>
	<?
		$x = 0;
		foreach ($fields as $key => $field) {
			$x++;
	?>
	<span class="view_column" style="width: <?=$field["width"]?>px;"><?=$field["title"]?></span>
	<?
		}
	?>
	<span class="view_status">Status</span>
	<span class="view_action" style="width: <?=(count($bigtree["view"]["actions"]) * 40)?>px;"><? if (count($bigtree["view"]["actions"]) > 1) { ?>Actions<? } ?></span>
</header>
<?	
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
<header class="group"><?=$title?></header>
<ul id="sort_table_<?=$gc?>">
	<? 
		foreach ($r["results"] as $item) {
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
	?>
	<li id="row_<?=$item["id"]?>" class="<?=$status_class?>">
		<?
			$x = 0;
			foreach ($fields as $key => $field) {
				$x++;
				$value = $item["column$x"];
		?>
		<section class="view_column" style="width: <?=$field["width"]?>px;">
			<? if ($x == 1 && $permission == "p" && !$search && $draggable) { ?>
			<span class="icon_sort"></span>
			<? } ?>
			<?=$value?>
		</section>
		<?
			}
		?>
		<section class="view_status status_<?=$status_class?>"><?=$status?></section>
		<?
			$iperm = ($permission == "p") ? "p" : $admin->getCachedAccessLevel($bigtree["module"],$item,$bigtree["view"]["table"]);
			foreach ($actions as $action => $data) {
				if ($data == "on") {
					if (($action == "delete" || $action == "approve" || $action == "feature" || $action == "archive") && $iperm != "p") {
						if ($action == "delete" && $item["pending_owner"] == $admin->ID) {
							$class = "icon_delete";
						} else {
							$class = "icon_disabled";
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
		?>
		<section class="view_action action_<?=$action?>"><a href="<?=$link?>" class="<?=$class?>" title="<?=ucwords($action)?>"></a></section>
		<?
				} else {
					$data = json_decode($data,true);
					$link = $module_page.$data["route"]."/".$item["id"]."/";
					if ($data["function"]) {
						$link = call_user_func($data["function"],$item);
					}
					$action = BigTree::safeEncode($data["name"]);
		?>
		<section class="view_action"><a href="<?=$link?>" class="<?=$data["class"]?>" title="<?=$action?>"></a></section>
		<?
				}
			}
		?>
	</li>
	<? } ?>
</ul>
<?
		}
	}
?>	