<?
	if (isset($_POST["view"])) {
		$bigtree["view"] = BigTreeAutoModule::getView($_POST["view"]);
		$bigtree["module"] = $admin->getModule(BigTreeAutoModule::getModuleForView($bigtree["view"]));
	}
	
	$permission = $admin->getAccessLevel($bigtree["module"]["id"]);
	
	// Setup the preview action if we have a preview URL and field.
	if ($bigtree["view"]["preview_url"]) {
		$bigtree["view"]["actions"]["preview"] = "on";
	}
	
	$module_page = ADMIN_ROOT.$bigtree["module"]["route"]."/";
	
	// Retrieve our results.
	if ((isset($_POST["search"]) && $_POST["search"]) || (isset($_GET["search"]) && $_GET["search"])) {
		$search = isset($_GET["search"]) ? $_GET["search"] : $_POST["search"];
		$bigtree["view"]["options"]["per_page"] = 10000000;
		$r = BigTreeAutoModule::getSearchResults($bigtree["view"],1,$search,"column1 ASC",false);
		$items = $r["results"];
	} else {
		$items = BigTreeAutoModule::getViewData($bigtree["view"],"position DESC, CAST(id AS UNSIGNED) ASC","both");
		$search = "";
	}
	
	// We're going to append information to the end of an edit string so that we can return to the same page / set of search results after submitting a form.
	$edit_append = "?view_data=".base64_encode(serialize(array("view" => $bigtree["view"]["id"], "search" => $search)));
	
	foreach ($items as $item) {
		// Stop the item status notice
		if (!isset($item["status"])) {
			$item["status"] = false;
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
		foreach ($bigtree["view"]["fields"] as $key => $field) {
			$x++;
			$value = $item["column$x"];
	?>
	<section class="view_column" style="width: <?=$field["width"]?>px;">
		<? if ($x == 1 && $permission == "p" && !$search) { ?>
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
		foreach ($bigtree["view"]["actions"] as $action => $data) {
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
	<section class="view_action action_<?=$action?>"><a href="<?=$link?>" class="<?=$class?>"></a></section>
	<?
			} else {
				$data = json_decode($data,true);
				$link = $module_page.$data["route"]."/".$item["id"]."/";
				if ($data["function"]) {
					$link = call_user_func($data["function"],$item);
				}
	?>
	<section class="view_action"><a href="<?=$link?>" class="<?=$data["class"]?>"></a></section>
	<?
			}
		}
	?>
</li>
<?
	}
?>