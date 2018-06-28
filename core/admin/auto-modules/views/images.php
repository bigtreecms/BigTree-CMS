<?php
	$permission = $admin->getAccessLevel($bigtree["module"]["id"]);
	
	// Setup defaults
	$draggable = (isset($bigtree["view"]["settings"]["draggable"]) && $bigtree["view"]["settings"]["draggable"]) ? true : false;
	$prefix = (isset($bigtree["view"]["settings"]["prefix"]) && $bigtree["view"]["settings"]["prefix"]) ? $bigtree["view"]["settings"]["prefix"] : "";
	
	$items = array();
	if ($draggable) {
		$order = "position DESC, CAST(id AS UNSIGNED) ASC";
	} else {
		if ($bigtree["view"]["settings"]["sort"] && ($bigtree["view"]["settings"]["sort"] == "ASC" || $bigtree["view"]["settings"]["sort"] == "DESC")) {
			$order = "CAST(id AS UNSIGNED) ".$bigtree["view"]["settings"]["sort"];
		} else {
			$order = "CAST(id AS UNSIGNED) DESC";
		}
	}

	$items = BigTreeAutoModule::getViewData($bigtree["view"],$order,"active");
	$pending_items = BigTreeAutoModule::getViewData($bigtree["view"],$order,"pending");
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
		if (count($pending_items)) {
	?>
	<header><span>Active</span></header>
	<?php
		}
	?>
	<section>
		<ul id="image_list">
			<?php
				foreach ($items as $item) {
					$item["column1"] = str_replace(array("{wwwroot}","{staticroot}"),array(WWW_ROOT,STATIC_ROOT),$item["column1"]);
					if ($prefix) {
						$preview_image = BigTree::prefixFile($item["column1"],$prefix);
					} else {
						$preview_image = $item["column1"];
					}
			?>
			<li id="row_<?=$item["id"]?>"<?php if ($permission != "p" || !$draggable) { ?> class="non_draggable"<?php } ?>>
				<a class="image<?php if (!isset($bigtree["view"]["actions"]["edit"])) { ?> image_disabled<?php } ?>" href="<?=$bigtree["view"]["edit_url"].$item["id"]?>/"><img src="<?=$preview_image?>" alt="" /></a>
				<?php
					if ($permission == "p" || ($bigtree["module"]["gbp"]["enabled"] && in_array("p",$admin->Permissions["module_gbp"][$bigtree["module"]["id"]])) || $item["pending_owner"] == $admin->ID) {
						$iperm = ($permission == "p") ? "p" : $admin->getCachedAccessLevel($bigtree["module"],$item,$bigtree["view"]["table"]);
						foreach ($bigtree["view"]["actions"] as $action => $data) {
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
	</section>
	<?php if (count($pending_items)) { ?>
	<header><span>Pending</span></header>
	<section>
		<ul>
			<?php
				foreach ($pending_items as $item) {
					$item["column1"] = str_replace(array("{wwwroot}","{staticroot}"),array(WWW_ROOT,STATIC_ROOT),$item["column1"]);
					if ($prefix) {
						$preview_image = BigTree::prefixFile($item["column1"],$prefix);
					} else {
						$preview_image = $item["column1"];
					}
			?>
			<li id="row_<?=$item["id"]?>" class="non_draggable">
				<a class="image<?php if (!isset($bigtree["view"]["actions"]["edit"])) { ?> image_disabled<?php } ?>" href="<?=$bigtree["view"]["edit_url"].$item["id"]?>/"><img src="<?=$preview_image?>" alt="" /></a>
				<?php
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
	</section>
	<?php } ?>
</div>

<?php include BigTree::path("admin/auto-modules/views/_common-js.php"); ?>
<script>
	<?php if ($permission == "p" && $draggable) { ?>
	$("#image_list").sortable({ containment: "parent", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: function() {
		$.secureAjax("<?=ADMIN_ROOT?>ajax/auto-modules/views/order/", { type: "POST", data: { view: "<?=$bigtree["view"]["id"]?>", table_name: "image_list", sort: $("#image_list").sortable("serialize") } });
	}});
	<?php } ?>
	
	// Stop disabled edit action from working.
	$(".image_list a.image_disabled").click(function() {
		return false;
	});
</script>