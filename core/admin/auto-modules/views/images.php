<?php
	namespace BigTree;

	/**
	 * @global \BigTreeAdmin $admin
	 * @global Module $module
	 * @global ModuleView $view
	 */
	
	$module_permission = $module->UserAccessLevel;
	
	// Setup defaults
	$draggable = (isset($view->Settings["draggable"]) && $view->Settings["draggable"]) ? true : false;
	$prefix = (isset($view->Settings["prefix"]) && $view->Settings["prefix"]) ? $view->Settings["prefix"] : "";
	
	if ($draggable) {
		$order = "position DESC, CAST(id AS UNSIGNED) ASC";
	} else {
		if ($view->Settings["sort"] && ($view->Settings["sort"] == "ASC" || $view->Settings["sort"] == "DESC")) {
			$order = "CAST(id AS UNSIGNED) ".$view->Settings["sort"];
		} else {
			$order = "CAST(id AS UNSIGNED) DESC";
		}
	}

	$items = $view->getData($order, "active");
	$pending_items = $view->getData($order, "pending");
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
		if (count($pending_items)) {
	?>
	<header><span><?=Text::translate("Active")?></span></header>
	<?php
		}
	?>
	<section>
		<ul id="image_list">
			<?php
				foreach ($items as $item) {
					$item["column1"] = str_replace(array("{wwwroot}","{staticroot}"),array(WWW_ROOT,STATIC_ROOT),$item["column1"]);
					if ($prefix) {
						$preview_image = FileSystem::getPrefixedFile($item["column1"],$prefix);
					} else {
						$preview_image = $item["column1"];
					}
			?>
			<li id="row_<?=$item["id"]?>"<?php if ($module_permission != "p" || !$draggable) { ?> class="non_draggable"<?php } ?>>
				<a class="image<?php if (!isset($view->Actions["edit"])) { ?> image_disabled<?php } ?>" href="<?=$view->EditURL.$item["id"]?>/"><img src="<?=$preview_image?>" alt="" /></a>
				<?php
					if ($module_permission == "p" || ($module->GroupBasedPermissions["enabled"] && in_array("p",Auth::user()->Permissions["module_gbp"][$module->ID])) || $item["pending_owner"] == Auth::user()->ID) {
						$iperm = ($module_permission == "p") ? "p" : Auth::user()->getCachedAccessLevel($module, $item, $view->Table);
						foreach ($view->Actions as $action => $data) {
							if ($action != "edit") {
								if (($action == "delete" || $action == "approve" || $action == "feature" || $action == "archive") && $iperm != "p") {
									if ($action == "delete" && $item["pending_owner"] == Auth::user()->ID) {
										$class = "icon_delete";
									} else {
										$class = "icon_disabled";
									}
								} else {
									$class = ModuleView::generateActionClass($action, $item);
								}
								
								if ($action == "preview") {
									$link = rtrim($view->PreviewURL, "/")."/".$item["id"].'/" target="_preview';
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
									$action = Text::htmlEncode($data["name"]);
								}
				?>
				<a href="<?=$link?>" class="<?=$class?>" title="<?=Text::translate($action, true)?>"></a>
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
	</section>
	<?php if (count($pending_items)) { ?>
	<header><span><?=Text::translate("Pending")?></span></header>
	<section>
		<ul>
			<?php
				foreach ($pending_items as $item) {
					$item["column1"] = str_replace(array("{wwwroot}","{staticroot}"),array(WWW_ROOT,STATIC_ROOT),$item["column1"]);
					if ($prefix) {
						$preview_image = FileSystem::getPrefixedFile($item["column1"],$prefix);
					} else {
						$preview_image = $item["column1"];
					}
			?>
			<li id="row_<?=$item["id"]?>" class="non_draggable">
				<a class="image<?php if (!isset($view->Actions["edit"])) { ?> image_disabled<?php } ?>" href="<?=$view->EditURL.$item["id"]?>/"><img src="<?=$preview_image?>" alt="" /></a>
				<?php
					if ($module_permission == "p" || ($module->GroupBasedPermissions["enabled"] && in_array("p",Auth::user()->Permissions["module_gbp"][$module->ID])) || $item["pending_owner"] == Auth::user()->ID) {
						$iperm = ($module_permission == "p") ? "p" : Auth::user()->getCachedAccessLevel($module, $item, $view->Table);
						foreach ($view->Actions as $action => $data) {
							if ($action != "edit") {
								if (($action == "delete" || $action == "approve" || $action == "feature" || $action == "archive") && $iperm != "p") {
									if ($action == "delete" && $item["pending_owner"] == Auth::user()->ID) {
										$class = "icon_delete";
									} else {
										$class = "icon_disabled";
									}
								} else {
									$class = ModuleView::generateActionClass($action, $item);
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
									$action = Text::htmlEncode($data["name"]);
								}
				?>
				<a href="<?=$link?>" class="<?=$class?>" title="<?=Text::translate($action, true)?>"></a>
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
	</section>
	<?php } ?>
</div>

<?php include Router::getIncludePath("admin/auto-modules/views/_common-js.php") ?>
<script>
	<?php if ($module_permission == "p" && $draggable) { ?>
	$("#image_list").sortable({ containment: "parent", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/order/", { type: "POST", data: { view: "<?=$view->ID?>", table_name: "image_list", sort: $("#image_list").sortable("serialize") } });
	}});
	<?php } ?>
	
	// Stop disabled edit action from working.
	$(".image_list a.image_disabled").click(function() {
		return false;
	});
</script>