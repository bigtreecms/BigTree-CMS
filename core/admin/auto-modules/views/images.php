<?
	$module_page = ADMIN_ROOT.$module["route"]."/";
	
	$suffix = $view["suffix"] ? "-".$view["suffix"] : "";
		
	$module_id = BigTreeAutoModule::getModuleForView($view);
	$module = $admin->getModule($module_id);
	$permission = $admin->getAccessLevel($module_id);
	
	// Setup defaults
	$draggable = (isset($view["options"]["draggable"]) && $view["options"]["draggable"]) ? true : false;
	$prefix = (isset($view["options"]["prefix"]) && $view["options"]["prefix"]) ? $view["options"]["prefix"] : "";
	
	$items = array();
	if ($draggable) {
		$order = "position DESC, id ASC";
	} else {
		$order = "id DESC";
	}
	
	$items = BigTreeAutoModule::getViewData($view,$order,"active");
	$pending_items = BigTreeAutoModule::getViewData($view,$order,"pending");
?>
<div class="table auto_modules">
	<summary>
		<p><? if ($permission == "p" && $draggable) { ?>Click and drag the light gray area of an item to sort the images. <? } ?>Click an image to edit it.</p>
	</summary>
	<? if (count($pending_items)) { ?>
	<header><span style="padding: 0 0 0 20px;">Active</span></header>
	<? } ?>
	<section>
		<ul id="image_list" class="image_list">
			<?
				foreach ($items as $item) {
					$item["column1"] = str_replace(array("{wwwroot}","{staticroot}"),array(WWW_ROOT,STATIC_ROOT),$item["column1"]);
					if ($prefix) {
						$preview_image = BigTree::prefixFile($item["column1"],$prefix);
					} else {
						$preview_image = $item["column1"];
					}
			?>
			<li id="row_<?=$item["id"]?>"<? if ($permission != "p" || !$draggable) { ?> class="non_draggable"<? } ?>>
				<a class="image<? if (!isset($view["actions"]["edit"])) { ?> image_disabled<? } ?>" href="<?=$module_page?>edit<?=$suffix?>/<?=$item["id"]?>/"><img src="<?=$preview_image?>" alt="" /></a>
				<?
					if ($permission == "p" || ($module["gbp"]["enabled"] && in_array("p",$admin->Permissions["module_gbp"][$module["id"]])) || $item["pending_owner"] == $admin->ID) {
						$iperm = ($permission == "p") ? "p" : $admin->getCachedAccessLevel($module,$item,$view["table"]);
						foreach ($view["actions"] as $action => $data) {
							if ($action != "edit") {
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
								
								if ($data != "on") {
									$data = json_decode($data,true);
									$class = $data["class"];
									$link = $module_page.$data["route"]."/".$item["id"]."/";
									if ($data["function"]) {
										eval('$link = '.$data["function"].'($item);');
									}
								}
				?>
				<a href="<?=$link?>" class="<?=$class?>"></a>
				<?
							}
						}
					}
				?>
			</li>
			<?
				}
			?>
		</ul>
	</section>
	<? if (count($pending_items)) { ?>
	<header><span style="padding: 0 0 0 20px;">Pending</span></header>
	<section>
		<ul class="image_list">
			<?
				foreach ($pending_items as $item) {
					if ($prefix) {
						$preview_image = BigTree::prefixFile($item["column1"],$prefix);
					} else {
						$preview_image = $item["column1"];
					}
			?>
			<li id="row_<?=$item["id"]?>" class="non_draggable">
				<a class="image" href="<?=$module_page?>edit<?=$suffix?>/<?=$item["id"]?>/"><img src="<?=$preview_image?>" alt="" /></a>
				<?
					if ($permission == "p" || ($module["gbp"]["enabled"] && in_array("p",$admin->Permissions["module_gbp"][$module["id"]])) || $item["pending_owner"] == $admin->ID) {
						$iperm = ($permission == "p") ? "p" : $admin->getCachedAccessLevel($module,$item,$view["table"]);
						foreach ($view["actions"] as $action => $data) {
							if ($action != "edit") {
								if (($action == "delete" || $action == "approve" || $action == "feature" || $action == "archive") && $iperm != "p") {
									if ($action == "delete" && $item["pending_owner"] == $admin->ID) {
										$class = "icon_delete";
									} else {
										$class = "icon_disabled";
									}
								} else {
									$class = $admin->getActionClass($action,$item);
								}
								$link = "#".$item["id"];
								
								if ($data != "on") {
									$data = json_decode($data,true);
									$class = $data["class"];
									$link = $module_page.$data["route"]."/".$item["id"]."/";
									if ($data["function"]) {
										eval('$link = '.$data["function"].'($item);');
									}
								}
				?>
				<a href="<?=$link?>" class="<?=$class?>"></a>
				<?
							}
						}
					}
				?>
			</li>
			<?
				}
			?>
		</ul>
	</section>
	<? } ?>
</div>

<? include BigTree::path("admin/auto-modules/views/_common-js.php") ?>
<script>
	<? if ($permission == "p" && $draggable) { ?>
	$("#image_list").sortable({ containment: "parent", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/order/", { type: "POST", data: { view: "<?=$view["id"]?>", table_name: "image_list", sort: $("#image_list").sortable("serialize") } });
	}});
	<? } ?>
	
	// Stop disabled edit action from working.
	$(".image_list a.image_disabled").click(function() {
		return false;
	});
</script>