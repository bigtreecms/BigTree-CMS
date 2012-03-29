<?
	$mpage = $admin_root.$module["route"]."/";
	
	BigTree::globalizeArray($view);
	
	$suffix = $suffix ? "-".$suffix : "";
		
	$m = BigTreeAutoModule::getModuleForView($view);
	$module = $admin->getModule($m);
	$perm = $admin->getAccessLevel($m);
	
	$items = array();
	if ($view["options"]["draggable"]) {
		$order = "position DESC, id ASC";
	} else {
		$order = "id DESC";
	}
	
	$items = BigTreeAutoModule::getViewData($view,$order,"active");
	$pending_items = BigTreeAutoModule::getViewData($view,$order,"pending");
?>
<div class="table auto_modules">
	<summary>
		<p><? if ($perm == "p" && $view["options"]["draggable"]) { ?>Click and drag the light gray area of an item to sort the images. <? } ?>Click an image to edit it.</p>
	</summary>
	<? if (count($pending_items)) { ?>
	<header><span style="padding: 0 0 0 20px;">Active</span></header>
	<? } ?>
	<section>
		<ul id="image_list" class="image_list">
			<?
				foreach ($items as $item) {
					$item["column1"] = str_replace("{wwwroot}",$www_root,$item["column1"]);
					if ($options["preview_prefix"]) {
						$preview_image = BigTree::prefixFile($item["column1"],$options["preview_prefix"]);
					} else {
						$preview_image = $item["column1"];
					}
			?>
			<li id="row_<?=$item["id"]?>"<? if ($perm != "p" || !$view["options"]["draggable"]) { ?> class="non_draggable"<? } ?>>
				<a class="image" href="<?=$mpage?>edit<?=$suffix?>/<?=$item["id"]?>/"><img src="<?=$preview_image?>" alt="" style="<?=$style?>" /></a>
				<?
					if ($perm == "p" || ($module["gbp"]["enabled"] && in_array("p",$admin->Permissions["module_gbp"][$module["id"]])) || $item["pending_owner"] == $admin->ID) {
						$iperm = ($perm == "p") ? "p" : $admin->getCachedAccessLevel($module,$item,$view["table"]);
						foreach ($actions as $action => $data) {
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
									$link = $mpage.$data["route"]."/".$item["id"]."/";
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
					if ($options["preview_prefix"]) {
						$preview_image = BigTree::prefixFile($item["column1"],$options["preview_prefix"]);
					} else {
						$preview_image = $item["column1"];
					}
			?>
			<li id="row_<?=$item["id"]?>" class="non_draggable">
				<a class="image" href="<?=$mpage?>edit<?=$suffix?>/<?=$item["id"]?>/"><img src="<?=$preview_image?>" alt="" style="<?=$style?>" /></a>
				<?
					if ($perm == "p" || ($module["gbp"]["enabled"] && in_array("p",$admin->Permissions["module_gbp"][$module["id"]])) || $item["pending_owner"] == $admin->ID) {
						$iperm = ($perm == "p") ? "p" : $admin->getCachedAccessLevel($module,$item,$view["table"]);
						foreach ($actions as $action => $data) {
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
									$link = $mpage.$data["route"]."/".$item["id"]."/";
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
<script type="text/javascript">
	<? if ($perm == "p" && $view["options"]["draggable"]) { ?>
	$("#image_list").sortable({ containment: "parent", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: function() {
		$.ajax("<?=$admin_root?>ajax/auto-modules/views/order/?view=<?=$view["id"]?>&table_name=image_list&sort=" + escape($("#image_list").sortable("serialize")));
	}});
	<? } ?>
	 
	$(".image_list img").load(function() {
		w = $(this).width();
		h = $(this).height();
		if (w > h) {
			perc = 108 / w;
			h = perc * h;
			style = { margin: Math.floor((108 - h) / 2) + "px 0 0 0" };
		} else {
			style = { margin: "0px" };
		}
		
		$(this).css(style);
	});
</script>