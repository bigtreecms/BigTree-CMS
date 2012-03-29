<?
	if ($_POST["view"]) {
		$autoModule = new BigTreeAutoModule;
		$view = BigTreeAutoModule::getView($_POST["view"]);
		
		BigTree::globalizeArray($view);
		
		$m = BigTreeAutoModule::getModuleForView($view);
		$perm = $admin->getAccessLevel($m);
		
		$suffix = $suffix ? "-".$suffix : "";
		
		// Setup the preview action if we have a preview URL and field.
		if ($view["preview_url"]) {
			$actions["preview"] = "on";
		}
	}
	
	$module = $admin->getModule($m);
	
	if ($_POST["search"]) {
		$view["options"]["per_page"] = 10000000;
		$r = BigTreeAutoModule::getSearchResults($view,0,$_POST["search"],"position DESC, id ASC","",false,$module);
		$items = $r["results"];
	} else {
		$items = BigTreeAutoModule::getViewData($view,"position DESC, id ASC","both",$module);
	}
	
	foreach ($items as $item) {
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
		<? if ($x == 1 && $perm == "p" && !$_POST["search"]) { ?>
		<span class="icon_sort"></span>
		<? } ?>
		<?=$value?>
	</section>
	<?
		}
	?>
	<section class="view_status status_<?=$status_class?>"><?=$status?></section>
	<?
		$iperm = ($perm == "p") ? "p" : $admin->getCachedAccessLevel($module,$item,$view["table"]);
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
					$link = rtrim($view["preview_url"],"/")."/".$item["id"].'/" target="_preview';
				} else {
					$link = "#".$item["id"];
				}
	?>
	<section class="view_action action_<?=$action?>"><a href="<?=$link?>" class="<?=$class?>"></a></section>
	<?
			} else {
				$data = json_decode($data,true);
				$link = $mpage.$data["route"]."/".$item["id"]."/";
				if ($data["function"]) {
					eval('$link = '.$data["function"].'($item);');
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