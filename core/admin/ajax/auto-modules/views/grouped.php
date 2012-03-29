<?
	// If it's an AJAX request, get our data.
	if ($_POST["view"]) {
		$view = BigTreeAutoModule::getView($_POST["view"]);
	}
	
	BigTree::globalizeArray($view);
	$m = BigTreeAutoModule::getModuleForView($view);
	$perm = $admin->getAccessLevel($m);
	$module = $admin->getModule($m);

	$suffix = $suffix ? "-".$suffix : "";
	$o = $options;
	$view["per_page"] = 10000;
	
	// Setup the preview action if we have a preview URL and field.
	if ($view["preview_url"]) {
		$actions["preview"] = "on";
	}
	
	// Cache the data in case it's not there.
	BigTreeAutoModule::cacheViewData($view);
	
	$groups = BigTreeAutoModule::getGroupsForView($view);
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
	<?	
			foreach ($actions as $action => $status) {
	?>
	<span class="view_action"><?=$action?></span>
	<?
			}
	?>
</header>
<?	
	$q = sqlquery($query);
	$gc = 0;
	foreach ($groups as $group => $title) {		
		if ($o["draggable"]) {
			$r = BigTreeAutoModule::getSearchResults($view,0,$_POST["search"],"position DESC, id ASC","",$group,$module);
		} else {
			$r = BigTreeAutoModule::getSearchResults($view,0,$_POST["search"],$o["sort_field"],$o["sort_direction"],$group,$module);
		}
		
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
			<? if ($x == 1 && $perm == "p" && !$_POST["search"] && $o["draggable"]) { ?>
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
	<? } ?>
</ul>
<?
		}
	}
?>	