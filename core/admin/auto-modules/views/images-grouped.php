<?
	BigTree::globalizeArray($view);
	
	// Make sure our view data is cached;
	BigTreeAutoModule::cacheViewData($view);
	
	$module_id = BigTreeAutoModule::getModuleForView($view);
	$module = $admin->getModule($module_id);
	$permission = $admin->getAccessLevel($module_id);
	$module_page = ADMIN_ROOT.$module["route"]."/";
	$suffix = $suffix ? "-".$suffix : "";
	$draggable = (isset($view["options"]["draggable"]) && $view["options"]["draggable"]) ? true : false;
	$groups = BigTreeAutoModule::getGroupsForView($view);
?>
<div class="table auto_modules">
	<summary>
		<p><? if ($permission == "p" && $draggable) { ?>Click and drag the light gray area of an item to sort the images. <? } ?>Click an image to edit it.</p>
	</summary>
	<?
		$y = 0;
		foreach ($groups as $group => $title) {
			$y++;
			
			if ($draggable) {
				$items = BigTreeAutoModule::getViewDataForGroup($view,$group,"position DESC, id ASC","active");
			} else {
				$items = BigTreeAutoModule::getViewDataForGroup($view,$group,"id DESC","active");
			}
			$pending_items = BigTreeAutoModule::getViewDataForGroup($view,$group,"id DESC","pending");
	?>
	<header class="group"><?=$title?></header>
	<section>
		<?
			if (count($items)) {
		?>
		<ul id="image_list_<?=$y?>" class="image_list">
			<?
				foreach ($items as $item) {
					$item["column1"] = str_replace(array("{wwwroot}","{staticroot}"),array(WWW_ROOT,STATIC_ROOT),$item["column1"]);
					if ($options["prefix"]) {
						$preview_image = BigTree::prefixFile($item["column1"],$options["prefix"]);
					} else {
						$preview_image = $item["column1"];
					}
			?>
			<li id="row_<?=$item["id"]?>"<? if ($permission != "p" || !$draggable) { ?> class="non_draggable"<? } ?>>
				<a class="image<? if (!isset($view["actions"]["edit"])) { ?> image_disabled<? } ?>" href="<?=$module_page?>edit<?=$suffix?>/<?=$item["id"]?>/"><img src="<?=$preview_image?>" alt="" style="<?=$style?>" /></a>
				<?
					if ($permission == "p" || ($module["gbp"]["enabled"] && in_array("p",$admin->Permissions["module_gbp"][$module["id"]])) || $item["pending_owner"] == $admin->ID) {
						$iperm = ($permission == "p") ? "p" : $admin->getCachedAccessLevel($module,$item,$view["table"]);
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
		<?
			}
			
			if (count($pending_items)) {
		?>
		<p><em>Pending Images</em></p>
		<ul class="image_list">
			<?
				foreach ($pending_items as $item) {
					if ($options["prefix"]) {
						$preview_image = BigTree::prefixFile($item["column1"],$options["prefix"]);
					} else {
						$preview_image = $item["column1"];
					}
			?>
			<li id="row_<?=$item["id"]?>" class="non_draggable">
				<a class="image<? if (!isset($view["actions"]["edit"])) { ?> image_disabled<? } ?>" href="<?=$module_page?>edit<?=$suffix?>/<?=$item["id"]?>/"><img src="<?=$preview_image?>" alt="" style="<?=$style?>" /></a>
				<?
					if ($permission == "p" || ($module["gbp"]["enabled"] && in_array("p",$admin->Permissions["module_gbp"][$module["id"]])) || $item["pending_owner"] == $admin->ID) {
						$iperm = ($permission == "p") ? "p" : $admin->getCachedAccessLevel($module,$item,$view["table"]);
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
		<?
			}
		?>
	</section>
	<?
		}
	?>
</div>

<? include BigTree::path("admin/auto-modules/views/_common-js.php") ?>
<script>
	<? if ($permission == "p" && $draggable) { ?>
	$(".image_list").each(function() {
		if ($(this).attr("id")) {
			$(this).sortable({ containment: "parent", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: $.proxy(function() {
				$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/order/",  { type: "POST", data: { view: "<?=$view["id"]?>", table_name: "image_list", sort: $(this).sortable("serialize") } });
			},this)});
		}
	});
	<? } ?>
	
	// Stop disabled edit action from working.
	$(".image_list a.image_disabled").click(function() {
		return false;
	});
</script>