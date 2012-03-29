<?
	$autoModule = new BigTreeAutoModule;

	// Grab View Data
	if ($_GET["view"])
		$view = $_GET["view"];
	if ($_GET["module"])
		$module = $admin->getModuleByRoute($_GET["module"]);

	$view = BigTreeAutoModule::getView($view);
	BigTree::globalizeArray($view);
		
	$search = $_GET["search"] ? $_GET["search"] : "";
	
	$sort = $options["sort_column"] ? $options["sort_column"] : "id";
	$sort_direction = $options["sort_direction"] ? $options["sort_direction"] : "DESC";
	$sort = $_GET["sort"] ? $_GET["sort"] : $sort;
	$sort_direction = $_GET["sort_direction"] ? $_GET["sort_direction"] : $sort_direction;
	
	$mpage = $admin_root.$module["route"]."/";
	
	// Setup the preview action if we have a preview URL and field.
	if ($view["preview_url"]) {
		$actions["preview"] = "on";
	}
	
	$perm = $admin->getAccessLevel($module);
		
	// If this is a second view inside a module, we might need a suffix for edits.
	$suffix = $suffix ? "-".$suffix : "";
	
	// Handle how many pages we have and what page we're on.
	$page = $_GET["page"] ? $_GET["page"] : 0;
	$data = BigTreeAutoModule::getSearchResults($view,$page,$search,$sort,$sort_direction,false,$module);
	$pages = $data["pages"];
	$items = $data["results"];
	
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
<script type="text/javascript">
	BigTree.SetPageCount("#view_paging",<?=$pages?>,<?=$page?>);
</script>