<?php
	namespace BigTree;

	/**
	 * @global \BigTreeAdmin $admin
	 * @global Module $module
	 * @global ModuleView $view
	 */

	$view->cacheAllData();
	$module_permission = $module->UserAccessLevel;
	$draggable = (isset($view->Settings["draggable"]) && $view->Settings["draggable"]) ? true : false;
	$groups = $view->Groups;

	if ($draggable) {
		$order = "position DESC, id ASC";
	} else {
		if ($view->Settings["sort"] && ($view->Settings["sort"] == "ASC" || $view->Settings["sort"] == "DESC")) {
			$order = "CAST(id AS UNSIGNED) ".$view->Settings["sort"];
		} else {
			$order = "CAST(id AS UNSIGNED) DESC";
		}
	}

	// Setup custom overrides for group titles when we're grouping by a special BigTree column
	$group_title_overrides = array();
	if ($view->Settings["group_field"] == "featured") {
		$group_title_overrides["on"] = "Featured";
		$group_title_overrides[""] = "Normal";
	} elseif ($view->Settings["group_field"] == "archived") {
		$group_title_overrides["on"] = "Archived";
		$group_title_overrides[""] = "Active";
	} elseif ($view->Settings["group_field"] == "approved") {
		$group_title_overrides["on"] = "Approved";
		$group_title_overrides[""] = "Not Approved";
	}
?>
<div class="table auto_modules image_list">
	<?php
		if (($module_permission == "p" && $draggable) || isset($view["actions"]["edit"])) {
	?>
	<summary>
		<p>
			<?php
				if ($module_permission == "p" && $draggable) { 
					echo Text::translate("Click and drag the light gray area of an item to sort the images."); 
				}

				if (isset($view["actions"]["edit"])) { 
					echo Text::translate("Click an image to edit it.");
				}
			?>
		</p>
	</summary>
	<?php
		}
		$y = 0;
		foreach ($groups as $group => $title) {
			$y++;

			$items = $view->getData($order, "active", $group);
			$pending_items = $view->getData($order, "pending", $group);
	?>
	<header class="group"><?=Text::translate((isset($group_title_overrides[$title]) ? $group_title_overrides[$title] : $title))?></header>
	<section>
		<?php
			if (count($items)) {
		?>
		<ul id="image_list_<?=$y?>">
			<?php
				foreach ($items as $item) {
					$item["column1"] = strtr($item["column1"], array(
						"{wwwroot}" => WWW_ROOT,
						"{staticroot}" => STATIC_ROOT
					));

					if ($view->Settings["prefix"]) {
						$preview_image = FileSystem::getPrefixedFile($item["column1"], $view->Settings["prefix"]);
					} else {
						$preview_image = $item["column1"];
					}

					$entry_permission = ($module_permission == "p") ? "p" : Auth::user()->getCachedAccessLevel($module, $item, $view->Table);
			?>
			<li id="row_<?=$item["id"]?>"<?php if ($module_permission != "p" || !$draggable) { ?> class="non_draggable"<?php } ?>>
				<a class="image<?php if (empty($view->Actions["edit"])) { ?> image_disabled<?php } ?>" href="<?=$view->EditURL.$item["id"]?>/"><img src="<?=$preview_image?>" alt="" /></a>
				<?php
					if ($module_permission == "p" || ($module->Group["enabled"] && in_array("p",$admin->Permissions["module_gbp"][$module->ID])) || $item["pending_owner"] == $admin->ID) {
						foreach ($view->Actions as $action => $data) {
							if ($action != "edit") {
								if (($action == "delete" || $action == "approve" || $action == "feature" || $action == "archive") && $entry_permission != "p") {
									if ($action == "delete" && $item["pending_owner"] == $admin->ID) {
										$class = "icon_delete";
									} else {
										$class = "icon_disabled";
									}
								} else {
									$class = $admin->getActionClass($action,$item);
								}
								
								if ($action == "preview") {
									$link = rtrim($view->PreviewURL,"/")."/".$item["id"].'/" target="_preview';
								} else {
									$link = "#".$item["id"];
								}
								
								if ($data != "on") {
									$data = json_decode($data,true);
									$class = $data["class"];
									$link = MODULE_ROOT.$data["route"]."/".$item["id"]."/";

									if ($data["function"]) {
										$link = call_user_func($data["function"],$item);
									}
								}
				?>
				<a href="<?=$link?>" class="<?=$class?>"></a>
				<?php
							}
						}
					}
				?>
			</li>
			<?php
				}
			?>
		</ul>
		<?php
			}
			
			if (count($pending_items)) {
		?>
		<header class="image_pending_divider"><?=Text::translate("Pending Entries")?></header>
		<ul>
			<?php
				foreach ($pending_items as $item) {
					$item["column1"] = strtr($item["column1"], array(
						"{wwwroot}" => WWW_ROOT,
						"{staticroot}" => STATIC_ROOT
					));

					if ($view->Settings["prefix"]) {
						$preview_image = FileSystem::getPrefixedFile($item["column1"], $view->Settings["prefix"]);
					} else {
						$preview_image = $item["column1"];
					}

					$entry_permission = ($module_permission == "p") ? "p" : Auth::user()->getCachedAccessLevel($module, $item, $view->Table);
			?>
			<li id="row_<?=$item["id"]?>" class="non_draggable">
				<a class="image<?php if (empty($view->Actions["edit"])) { ?> image_disabled<?php } ?>" href="<?=$view->EditURL.$item["id"]?>/"><img src="<?=$preview_image?>" alt="" /></a>
				<?php
					if ($module_permission == "p" || ($module->GroupBasedPermissions["enabled"] && in_array("p",$admin->Permissions["module_gbp"][$module->ID])) || $item["pending_owner"] == $admin->ID) {
						foreach ($view->Actions as $action => $data) {
							if ($action != "edit") {
								if (($action == "delete" || $action == "approve" || $action == "feature" || $action == "archive") && $entry_permission != "p") {
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
									$link = MODULE_ROOT.$data["route"]."/".$item["id"]."/";

									if ($data["function"]) {
										$link = call_user_func($data["function"],$item);
									}
								}
				?>
				<a href="<?=$link?>" class="<?=$class?>" title="<?=Text::translate($data["name"], true)?>"></a>
				<?php
							}
						}
					}
				?>
			</li>
			<?php
				}
			?>
		</ul>
		<?php
			}
		?>
	</section>
	<?php
		}
	?>
</div>

<?php include Router::getIncludePath("admin/auto-modules/views/_common-js.php") ?>
<script>
	<?php if ($module_permission == "p" && $draggable) { ?>
	$(".image_list ul").each(function() {
		if ($(this).attr("id")) {
			$(this).sortable({ containment: "parent", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: $.proxy(function() {
				$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/order/",  { type: "POST", data: { view: "<?=$view->ID?>", table_name: "image_list", sort: $(this).sortable("serialize") } });
			},this)});
		}
	});
	<?php } ?>
	
	// Stop disabled edit action from working.
	$(".image_list a.image_disabled").click(function() {
		return false;
	});
</script>