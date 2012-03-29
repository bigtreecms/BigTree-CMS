<?
	$mpage = $admin_root.$module["route"]."/";
	
	BigTree::globalizeArray($view);
	$o = $options;
	
	$suffix = $suffix ? "-".$suffix : "";
	
	BigTreeAutoModule::cacheViewData($view);
	$m = BigTreeAutoModule::getModuleForView($view);
	$module = $admin->getModule($m);
	$perm = $admin->getAccessLevel($m);
	
	$items = array();
	if ($view["options"]["draggable"]) {
		$order = "`$table`.position DESC, `$table`.id ASC";
	} else {
		$order = "`$table`.id DESC";
	}
	
	$groups = BigTreeAutoModule::getGroupsForView($view);
?>
<div class="table auto_modules">
	<summary>
		<p><? if ($perm == "p" && $view["options"]["draggable"]) { ?>Click and drag the light gray area of an item to sort the images. <? } ?>Click an image to edit it.</p>
	</summary>
	<?
		$y = 0;
		foreach ($groups as $group => $title) {
			$y++;
			
			if ($o["draggable"]) {
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
		<?
			}
			
			if (count($pending_items)) {
		?>
		<p><em>Pending Images</em></p>
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
		<?
			}
		?>
	</section>
	<?
		}
	?>
</div>

<? include BigTree::path("admin/auto-modules/views/_common-js.php") ?>
<script type="text/javascript">
	<? if ($perm == "p" && $o["draggable"]) { ?>
	$(".image_list").each(function() {
		if ($(this).attr("id")) {
			$(this).sortable({ containment: "parent", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: $.proxy(function() {
				$.ajax("<?=$admin_root?>ajax/auto-modules/views/order/?view=<?=$view["id"]?>&table_name=" + $(this).attr("id") + "&sort=" + escape($(this).sortable("serialize")));;
			},this)});
		}
	});
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