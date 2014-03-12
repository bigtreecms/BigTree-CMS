<?
	if ((isset($_POST["search"]) && $_POST["search"]) || (isset($_GET["search"]) && $_GET["search"])) {
		include "draggable.php";
?>
<script>$("#nested_container").removeClass("nested_table");</script>
<?
	} else {
		if (isset($_POST["view"])) {
			$bigtree["view"] = BigTreeAutoModule::getView($_POST["view"]);
			$bigtree["module"] = $admin->getModule(BigTreeAutoModule::getModuleForView($bigtree["view"]));
		}
	
		$module_page = ADMIN_ROOT.$bigtree["module"]["route"]."/";
		$permission = $admin->getAccessLevel($bigtree["module"]["id"]);
	
		// Setup the preview action if we have a preview URL and field.
		if ($bigtree["view"]["preview_url"]) {
			$bigtree["view"]["actions"]["preview"] = "on";
		}

		function _localDrawLevel($items,$depth) {
			global $bigtree,$module_page,$permission,$admin;

			foreach ($items as $item) {
				$expanded = !empty($_COOKIE["bigtree_admin"]["nested_views"][$bigtree["view"]["id"]][$item["id"]]) ? true : false;
				$children = BigTreeAutoModule::getViewDataForGroup($bigtree["view"],$item["id"],"position DESC, id ASC","both");
				
				// Stop the item status notice
				if (!isset($item["status"])) {
					$item["status"] = false;
				}
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
				if ($expanded) {
					$status_class .= " expanded";
				}
?>
<li id="row_<?=$item["id"]?>" class="<?=$status_class?>">
	<span class="depth" style="width: <?=($depth * 24)?>px;">
		<? if ($permission == "p") { ?>
		<span class="icon_sort"></span>
		<? } ?>
	</span>
	<?
				$x = 0;
				$depth_minus = ceil((24 * $depth + 1) / count($bigtree["view"]["fields"]));
				foreach ($bigtree["view"]["fields"] as $key => $field) {
					$x++;
					$value = $item["column$x"];
					if ($x == 1) {
						$field["width"] -= 20;
					}
	?>
	<section class="view_column<? if ($x == 1 && !count($children)) { ?> disabled<? } ?>" style="width: <?=($field["width"] - $depth_minus)?>px;"><?=$value?></section>
	<?
				}
	?>
	<section class="view_status status_<?=$status_class?>"><?=$status?></section>
	<?
				$iperm = ($permission == "p") ? "p" : $admin->getCachedAccessLevel($bigtree["module"],$item,$bigtree["view"]["table"]);
				foreach ($bigtree["view"]["actions"] as $action => $data) {
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
							$link = rtrim($bigtree["view"]["preview_url"],"/")."/".$item["id"].'/" target="_preview';
						} elseif ($action == "edit") {
							$link = $bigtree["view"]["edit_url"].$item["id"]."/".$edit_append;
						} else {
							$link = "#".$item["id"];
						}
	?>
	<section class="view_action action_<?=$action?>"><a href="<?=$link?>" class="<?=$class?>"></a></section>
	<?
					} else {
						$data = json_decode($data,true);
						$link = $module_page.$data["route"]."/".$item["id"]."/";
						if ($data["function"]) {
							$link = call_user_func($data["function"],$item);
						}
	?>
	<section class="view_action"><a href="<?=$link?>" class="<?=$data["class"]?>"></a></section>
	<?
					}
				}

				if (count($children)) {
					if ($expanded) {
						echo "<ul>";
					} else {
						echo '<ul style="display: none;">';
					}
					_localDrawLevel($children,$depth + 1);
					echo "</ul>";
				}
	?>
</li>
<?
			}
		}

		$table_description = BigTree::describeTable($bigtree["view"]["table"]);
		if ($table_description["columns"][$bigtree["view"]["options"]["nesting_column"]]["allow_null"]) {
			_localDrawLevel(BigTreeAutoModule::getViewDataForGroup($bigtree["view"],"","position DESC, id ASC","both"),1);
		} else {
			_localDrawLevel(BigTreeAutoModule::getViewDataForGroup($bigtree["view"],"0","position DESC, id ASC","both"),1);
		}
?>
<script>
	$("#nested_container").addClass("nested_table");
	<? if ($permission == "p") { ?>
	BigTree.localCreateSortable("#table_data");
	<? } ?>
</script>
<?
	}
?>