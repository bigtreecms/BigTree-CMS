<?
	$autoModule = new BigTreeAutoModule;

	// Grab View Data
	if (isset($_GET["view"])) {
		$bigtree["view"] = BigTreeAutoModule::getView($_GET["view"]);
	}
	if (isset($_GET["module"])) {
		$bigtree["module"] = $admin->getModuleByRoute($_GET["module"]);
	}

	BigTree::globalizeArray($bigtree["view"]);

	$search = isset($_GET["search"]) ? $_GET["search"] : "";
	$page = isset($_GET["page"]) ? $_GET["page"] : 1;
	
	if (isset($_GET["sort"])) {
		$sort = $_GET["sort"]." ".$_GET["sort_direction"];
		
		// Append information to the end of an edit string so that we can return to the same set of search results after submitting a form.
		$edit_append = "?view_data=".base64_encode(serialize(array("view" => $bigtree["view"]["id"], "sort" => $_GET["sort"], "sort_direction" => $_GET["sort_direction"], "search" => $search, "page" => $page)));
	} else {
		if (isset($options["sort_column"])) {
			$sort = $options["sort_column"]." ".$options["sort_direction"];
		} elseif (isset($options["sort"])) {
			$sort = $options["sort"];
		} else {
			$sort = "id DESC";
		}
		
		// Same thing we were going to do above but omit the sort stuff.
		$edit_append = "?view_data=".base64_encode(serialize(array("view" => $bigtree["view"]["id"], "search" => $search, "page" => $page)));
	}
	
	$module_page = ADMIN_ROOT.$bigtree["module"]["route"]."/";
	
	// Setup the preview action if we have a preview URL and field.
	if ($bigtree["view"]["preview_url"]) {
		$actions["preview"] = "on";
	}
	
	$perm = $admin->getAccessLevel($bigtree["module"]);
		
	// Handle how many pages we have and get our results.
	$data = BigTreeAutoModule::getSearchResults($bigtree["view"],$page,$search,$sort,false);
	$pages = $data["pages"];
	$items = $data["results"];
	
	foreach ($items as $item) {
		// If it's straight from the db, it's published.
		if (!isset($item["status"])) {
			$item["status"] = "";
		}
		
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
		<?=$value?>
	</section>
	<?
		}
	?>
	<section class="view_status status_<?=$status_class?>"><?=$status?></section>
	<?	
		$iperm = ($perm == "p") ? "p" : $admin->getCachedAccessLevel($bigtree["module"],$item,$bigtree["view"]["table"]);
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
<?
	}
?>
<script>
	BigTree.SetPageCount("#view_paging",<?=$pages?>,<?=$page?>);
</script>