<?php
	BigTree::globalizeArray($bigtree["view"]);
	
	// Make sure our view data is cached;
	BigTreeAutoModule::cacheViewData($bigtree["view"]);
	
	$permission = $admin->getAccessLevel($bigtree["module"]["id"]);
	$draggable = (isset($bigtree["view"]["settings"]["draggable"]) && $bigtree["view"]["settings"]["draggable"]) ? true : false;
	$groups = BigTreeAutoModule::getGroupsForView($bigtree["view"]);
	if ($draggable) {
		$order = "position DESC, id ASC";
	} else {
		if ($bigtree["view"]["settings"]["sort"] && ($bigtree["view"]["settings"]["sort"] == "ASC" || $bigtree["view"]["settings"]["sort"] == "DESC")) {
			$order = "CAST(id AS UNSIGNED) ".$bigtree["view"]["settings"]["sort"];
		} else {
			$order = "CAST(id AS UNSIGNED) DESC";
		}
	}

	// Setup custom overrides for group titles when we're grouping by a special BigTree column
	$group_title_overrides = array();
	if ($bigtree["view"]["settings"]["group_field"] == "featured") {
		$group_title_overrides["on"] = "Featured";
		$group_title_overrides[""] = "Normal";
	} elseif ($bigtree["view"]["settings"]["group_field"] == "archived") {
		$group_title_overrides["on"] = "Archived";
		$group_title_overrides[""] = "Active";
	} elseif ($bigtree["view"]["settings"]["group_field"] == "approved") {
		$group_title_overrides["on"] = "Approved";
		$group_title_overrides[""] = "Not Approved";
	}
?>
<div class="table auto_modules image_list">
	<?php
		include "_developer-buttons.php";
		
		if (($permission == "p" && $draggable) || isset($view["actions"]["edit"])) {
	?>
	<summary>
		<p><?php if ($permission == "p" && $draggable) { ?>Click and drag the light gray area of an item to sort the images. <?php } ?><?php if (isset($view["actions"]["edit"])) { ?>Click an image to edit it.<?php } ?></p>
	</summary>
	<?php
		}
		$y = 0;
		foreach ($groups as $group => $title) {
			$y++;
			
			$items = BigTreeAutoModule::getViewDataForGroup($bigtree["view"],$group,$order,"active");
			$pending_items = BigTreeAutoModule::getViewDataForGroup($bigtree["view"],$group,$order,"pending");
	?>
	<header class="group"><?=(isset($group_title_overrides[$title]) ? $group_title_overrides[$title] : $title)?></header>
	<section>
		<?php
			if (count($items)) {
		?>
		<ul id="image_list_<?=$y?>">
			<?php
				foreach ($items as $item) {
					$item["column1"] = str_replace(array("{wwwroot}","{staticroot}"),array(WWW_ROOT,STATIC_ROOT),$item["column1"]);
					if ($bigtree["view"]["settings"]["prefix"]) {
						$preview_image = BigTree::prefixFile($item["column1"],$bigtree["view"]["settings"]["prefix"]);
					} else {
						$preview_image = $item["column1"];
					}
			?>
			<li id="row_<?=$item["id"]?>"<?php if ($permission != "p" || !$draggable) { ?> class="non_draggable"<?php } ?>>
				<a class="image<?php if (!isset($bigtree["view"]["actions"]["edit"])) { ?> image_disabled<?php } ?>" href="<?=$bigtree["view"]["edit_url"].$item["id"]?>/"><img src="<?=$preview_image?>" alt="" style="<?=$style?>" /></a>
				<?php
					if ($permission == "p" || ($bigtree["module"]["gbp"]["enabled"] && in_array("p",$admin->Permissions["module_gbp"][$bigtree["module"]["id"]])) || $item["pending_owner"] == $admin->ID) {
						$iperm = ($permission == "p") ? "p" : $admin->getCachedAccessLevel($bigtree["module"],$item,$bigtree["view"]["table"]);
						foreach ($actions as $action => $data) {
							if ($action != "edit") {
								if (($action == "delete" || $action == "approve" || $action == "feature" || $action == "archive") && $iperm != "p") {
									if ($action == "delete" && $item["pending_owner"] == $admin->ID) {
										$class = "icon_delete js-delete-hook";
									} else {
										$class = "icon_disabled js-disabled-hook";
									}
								} else {
									$class = $admin->getActionClass($action,$item);
								}
								
								if ($action == "preview") {
									$link = rtrim($bigtree["view"]["preview_url"],"/")."/".$item["id"].'/" target="_preview';
								} else {
									$link = "#".$item["id"];
								}
								
								$action_title = ucwords($action);

								if ($action == "archive" && $item["archived"]) {
									$action_title = "Restore";
								} elseif ($action == "feature" && $item["featured"]) {
									$action_title = "Unfeature";
								} elseif ($action == "approve" && $item["approved"]) {
									$action_title = "Unapprove";
								}
								
								if ($data != "on") {
									$data = json_decode($data,true);
									$class = $data["class"];
									$link = MODULE_ROOT.$data["route"]."/".$item["id"]."/";
									
									if ($data["function"]) {
										$link = call_user_func($data["function"],$item);
									}

									$action_title = BigTree::safeEncode($action_title);
								}
				?>
				<a href="<?=$link?>" class="<?=$class?>" title="<?=$action_title?>"></a>
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
		<header class="image_pending_divider">Pending Entries</header>
		<ul>
			<?php
				foreach ($pending_items as $item) {
					$item["column1"] = str_replace(array("{wwwroot}","{staticroot}"),array(WWW_ROOT,STATIC_ROOT),$item["column1"]);
					if ($bigtree["view"]["settings"]["prefix"]) {
						$preview_image = BigTree::prefixFile($item["column1"],$bigtree["view"]["settings"]["prefix"]);
					} else {
						$preview_image = $item["column1"];
					}
			?>
			<li id="row_<?=$item["id"]?>" class="non_draggable">
				<a class="image<?php if (!isset($bigtree["view"]["actions"]["edit"])) { ?> image_disabled<?php } ?>" href="<?=$bigtree["view"]["edit_url"].$item["id"]?>/"><img src="<?=$preview_image?>" alt="" style="<?=$style?>" /></a>
				<?php
					if ($permission == "p" || ($bigtree["module"]["gbp"]["enabled"] && in_array("p",$admin->Permissions["module_gbp"][$bigtree["module"]["id"]])) || $item["pending_owner"] == $admin->ID) {
						$iperm = ($permission == "p") ? "p" : $admin->getCachedAccessLevel($bigtree["module"],$item,$bigtree["view"]["table"]);
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
									$link = rtrim($bigtree["view"]["preview_url"],"/")."/".$item["id"].'/" target="_preview';
								} else {
									$link = "#".$item["id"];
								}

								$action_title = ucwords($action);

								if ($action == "archive" && $item["archived"]) {
									$action_title = "Restore";
								} elseif ($action == "feature" && $item["featured"]) {
									$action_title = "Unfeature";
								} elseif ($action == "approve" && $item["approved"]) {
									$action_title = "Unapprove";
								}

								if ($data != "on") {
									$data = json_decode($data,true);
									$class = $data["class"];
									$link = MODULE_ROOT.$data["route"]."/".$item["id"]."/";
									
									if ($data["function"]) {
										$link = call_user_func($data["function"],$item);
									}

									$action_title = BigTree::safeEncode($action_title);
								}
				?>
				<a href="<?=$link?>" class="<?=$class?>" title="<?=$action_title?>"></a>
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

<?php include BigTree::path("admin/auto-modules/views/_common-js.php"); ?>
<script>
	<?php if ($permission == "p" && $draggable) { ?>
	$(".image_list ul").each(function() {
		if ($(this).attr("id")) {
			$(this).sortable({ containment: "parent", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: $.proxy(function() {
				$.secureAjax("<?=ADMIN_ROOT?>ajax/auto-modules/views/order/",  { type: "POST", data: { view: "<?=$bigtree["view"]["id"]?>", table_name: "image_list", sort: $(this).sortable("serialize") } });
			},this)});
		}
	});
	<?php } ?>
	
	// Stop disabled edit action from working.
	$(".image_list a.image_disabled").click(function() {
		return false;
	});
</script>