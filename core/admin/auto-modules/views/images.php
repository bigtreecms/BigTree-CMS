<?
	$permission = $admin->getAccessLevel($bigtree["module"]["id"]);
	
	// Setup defaults
	$draggable = (isset($bigtree["view"]["options"]["draggable"]) && $bigtree["view"]["options"]["draggable"]) ? true : false;
	$prefix = (isset($bigtree["view"]["options"]["prefix"]) && $bigtree["view"]["options"]["prefix"]) ? $bigtree["view"]["options"]["prefix"] : "";
	
	$items = array();
	if ($draggable) {
		$order = "position DESC, CAST(id AS UNSIGNED) ASC";
	} else {
		if ($bigtree["view"]["options"]["sort"] && ($bigtree["view"]["options"]["sort"] == "ASC" || $bigtree["view"]["options"]["sort"] == "DESC")) {
			$order = "CAST(id AS UNSIGNED) ".$bigtree["view"]["options"]["sort"];
		} else {
			$order = "CAST(id AS UNSIGNED) DESC";
		}
	}

	$items = BigTreeAutoModule::getViewData($bigtree["view"],$order,"active");
	$pending_items = BigTreeAutoModule::getViewData($bigtree["view"],$order,"pending");
?>
<div class="table auto_modules image_list">
	<summary>
		<p><? if ($permission == "p" && $draggable) { ?>Click and drag the light gray area of an item to sort the images. <? } ?>Click an image to edit it.</p>
	</summary>
	<? if (count($pending_items)) { ?>
	<header><span>Active</span></header>
	<? } ?>
	<section>
		<ul id="image_list">
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
				<a class="image<? if (!isset($bigtree["view"]["actions"]["edit"])) { ?> image_disabled<? } ?>" href="<?=$bigtree["view"]["edit_url"].$item["id"]?>/"><img src="<?=$preview_image?>" alt="" /></a>
				<?
					if ($permission == "p" || ($bigtree["module"]["gbp"]["enabled"] && in_array("p",$admin->Permissions["module_gbp"][$bigtree["module"]["id"]])) || $item["pending_owner"] == $admin->ID) {
						$iperm = ($permission == "p") ? "p" : $admin->getCachedAccessLevel($bigtree["module"],$item,$bigtree["view"]["table"]);
						foreach ($bigtree["view"]["actions"] as $action => $data) {
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
									$link = rtrim($bigtree["view"]["preview_url"],"/")."/".$item["id"].'/" target="_preview';
								} else {
									$link = "#".$item["id"];
								}
								
								$action = ucwords($action);
								if ($data != "on") {
									$data = json_decode($data,true);
									$class = $data["class"];
									$link = MODULE_ROOT.$data["route"]."/".$item["id"]."/";
									if ($data["function"]) {
										$link = call_user_func($data["function"],$item);
									}
									$action = BigTree::safeEncode($data["name"]);
								}
				?>
				<a href="<?=$link?>" class="<?=$class?>" title="<?=$action?>"></a>
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
	<header><span>Pending</span></header>
	<section>
		<ul>
			<?
				foreach ($pending_items as $item) {
					$item["column1"] = str_replace(array("{wwwroot}","{staticroot}"),array(WWW_ROOT,STATIC_ROOT),$item["column1"]);
					if ($prefix) {
						$preview_image = BigTree::prefixFile($item["column1"],$prefix);
					} else {
						$preview_image = $item["column1"];
					}
			?>
			<li id="row_<?=$item["id"]?>" class="non_draggable">
				<a class="image" href="<?=$bigtree["view"]["edit_url"].$item["id"]?>/"><img src="<?=$preview_image?>" alt="" /></a>
				<?
					if ($permission == "p" || ($bigtree["module"]["gbp"]["enabled"] && in_array("p",$admin->Permissions["module_gbp"][$bigtree["module"]["id"]])) || $item["pending_owner"] == $admin->ID) {
						$iperm = ($permission == "p") ? "p" : $admin->getCachedAccessLevel($bigtree["module"],$item,$bigtree["view"]["table"]);
						foreach ($bigtree["view"]["actions"] as $action => $data) {
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
								
								$action = ucwords($action);
								if ($data != "on") {
									$data = json_decode($data,true);
									$class = $data["class"];
									$link = MODULE_ROOT.$data["route"]."/".$item["id"]."/";
									if ($data["function"]) {
										$link = call_user_func($data["function"],$item);
									}
									$action = BigTree::safeEncode($data["name"]);
								}
				?>
				<a href="<?=$link?>" class="<?=$class?>" title="<?=$action?>"></a>
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
		$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/order/", { type: "POST", data: { view: "<?=$bigtree["view"]["id"]?>", table_name: "image_list", sort: $("#image_list").sortable("serialize") } });
	}});
	<? } ?>
	
	// Stop disabled edit action from working.
	$(".image_list a.image_disabled").click(function() {
		return false;
	});
</script>