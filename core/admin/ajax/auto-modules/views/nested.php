<?php
	namespace BigTree;

	/**
	 * @global Module $module
	 * @global ModuleView $view
	 */

	// If a search has occurred, the behavior is the same as a draggable view
	if (!empty($_POST["search"]) || !empty($_GET["search"])) {
		include "draggable.php";
?>
<script>$("#nested_container").removeClass("nested_table");</script>
<?php
	} else {
		// If it's an AJAX request, get our data.
		if (isset($_POST["view"])) {
			$view = new ModuleView($_POST["view"]);
			$module = new Module($view->Module);
		}

		$module_permission = $module->UserAccessLevel;
		$module_page = ADMIN_ROOT.$module->Route."/";

		function drawNestedLevel($items,$depth) {
			global $module, $module_page, $module_permission, $view;

			foreach ($items as $item) {
				$expanded = !empty($_COOKIE["bigtree_admin"]["nested_views"][$view->ID][$item["id"]]) ? true : false;
				$children = $view->getData("position DESC, id ASC", "both", $item["id"]);

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

				$entry_permission = ($module_permission == "p") ? "p" : Auth::user()->getCachedAccessLevel($module, $item, $view->Table);
?>
<li id="row_<?=$item["id"]?>" class="<?=$status_class?>">
	<span class="depth" style="width: <?=($depth * 24)?>px;">
		<?php if ($module_permission == "p") { ?>
		<span class="icon_sort js-hook-sort"></span>
		<?php } ?>
	</span>
	<?php
				$x = 0;
				$depth_minus = ceil((24 * $depth + 1) / count($view->Fields));
				foreach ($view->Fields as $key => $field) {
					$x++;
					$value = $item["column$x"];
					if ($x == 1) {
						$field["width"] -= 20;
					}
	?>
	<section class="view_column<?php if ($x == 1 && !count($children)) { ?> disabled<?php } ?>" style="width: <?=($field["width"] - $depth_minus)?>px;"><?=$value?></section>
	<?php
				}
	?>
	<section class="view_status status_<?=$status_class?>"><?=Text::translate($status)?></section>
	<?php
				foreach ($view->Actions as $action => $data) {
					if ($data == "on") {
						if ($entry_permission == "n" || !$entry_permission || (($action == "delete" || $action == "approve" || $action == "feature" || $action == "archive") && $entry_permission != "p")) {
							if ($action == "delete" && $item["pending_owner"] == Auth::user()->ID) {
								$class = "icon_delete js-hook-delete";
							} else {
								$class = "icon_disabled js-hook-disabled";
							}
						} else {
							$class = $view->generateActionClass($action, $item);
						}
						
						if ($action == "preview") {
							$link = rtrim($view->PreviewURL,"/")."/".$item["id"].'/" target="_preview';
						} elseif ($action == "edit") {
							$link = $view->EditURL.$item["id"]."/";
						} else {
							$link = "#".$item["id"];
						}
	?>
	<section class="view_action action_<?=$action?>"><a href="<?=$link?>" class="<?=$class?>"></a></section>
	<?php
					} else {
						$data = json_decode($data,true);
						$link = $module_page.$data["route"]."/".$item["id"]."/";

						if ($data["function"]) {
							$link = call_user_func($data["function"],$item);
						}
	?>
	<section class="view_action"><a href="<?=$link?>" class="<?=$data["class"]?>"></a></section>
	<?php
					}
				}

				if (count($children)) {
					if ($expanded) {
						echo "<ul>";
					} else {
						echo '<ul style="display: none;">';
					}

					drawNestedLevel($children, $depth + 1);

					echo "</ul>";
				}
	?>
</li>
<?php
			}
		}

		// If we're allowing null, we're going to search by empty rather than 0
		$table_description = SQL::describeTable($view->Table);
		if ($table_description["columns"][$view->Settings["nesting_column"]]["allow_null"]) {
			$default_parent = "";
		} else {
			$default_parent = "0";
		}

		// Draw the table
		$data = $view->getData("position DESC, id ASC", "both", $default_parent);
		drawNestedLevel($data, 1);
?>
<script>
	$("#nested_container").addClass("nested_table");
	<?php if ($module_permission == "p") { ?>
	BigTree.localCreateSortable("#table_data");
	<?php } ?>
</script>
<?php
	}
