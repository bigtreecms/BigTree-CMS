<?php
	namespace BigTree;

	/**
	 * @global Module $module
	 * @global ModuleView $view
	 */

	// If it's an AJAX request, get our data.
	if (isset($_POST["view"])) {
		$view = new ModuleView($_POST["view"]);
		$module = new Module($view->Module);
	}
	
	$module_permission = $module->UserAccessLevel;	
	$module_page = ADMIN_ROOT.$module->Route."/";
	
	// Retrieve our results.
	if (!empty($_POST["search"]) || !empty($_GET["search"])) {
		$query = !empty($_GET["search"]) ? $_GET["search"] : $_POST["search"];

		// Return all results
		$view->Settings["per_page"] = 10000000;
		$search = $view->searchData(1, $query, "column1 ASC", false);
		$items = $search["results"];
	} else {
		$items = $view->getData("position DESC, CAST(id AS UNSIGNED) ASC", "both");
		$query = "";
	}
	
	// We're going to append information to the end of an edit string so that we can return to the same page / set of search results after submitting a form.
	$edit_append = "?view_data=".base64_encode(json_encode(array("view" => $view->ID, "search" => $query)));
	
	foreach ($items as $item) {
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
		} elseif ($item["status"] == "i") {
			$status = "Inactive";
			$status_class = "inactive";
		} else {
			$status = "Published";
			$status_class = "published";
		}

		$entry_permission = ($module_permission == "p") ? "p" : Auth::user()->getCachedAccessLevel($module, $item, $view->Table);
?>
<li id="row_<?=$item["id"]?>" class="<?=$status_class?>">
	<?php
		$x = 0;
		
		foreach ($view->Fields as $key => $field) {
	?>
	<section class="view_column" style="width: <?=$field["width"]?>px;">
		<?php
			// Show sortable indicator if the user is a publisher of the module and hasn't searched
			if (!$x && $module_permission == "p" && !$query) {
				echo '<span class="icon_sort js-hook-sort"></span>';
			}

			$x++;
			echo $item["column$x"];
		?>
	</section>
	<?php
		}
	?>
	<section class="view_status status_<?=$status_class?>"><?=$status?></section>
	<?php
		foreach ($view->Actions as $action => $data) {
			// "on" indicates it's a native non-custom action
			if ($data == "on") {
				if (($action == "delete" || $action == "approve" || $action == "feature" || $action == "archive") && $entry_permission != "p") {
					if ($action == "delete" && $item["pending_owner"] == Auth::user()->ID) {
						$class = "icon_delete js-hook-delete";
					} else {
						$class = "icon_disabled js-hook-disabled";
					}
				} else {
					$class = $view->generateActionClass($action, $item);
				}
				
				$action_title = ucwords($action);
				
				if ($action == "archive" && $item["archived"]) {
					$action_title = "Restore";
				} elseif ($action == "feature" && $item["featured"]) {
					$action_title = "Unfeature";
				} elseif ($action == "approve" && $item["approved"]) {
					$action_title = "Unapprove";
				}
				
				if ($action == "preview") {
					$link = rtrim($view->PreviewURL,"/")."/".$item["id"].'/" target="_preview';
				} elseif ($action == "edit") {
					$link = $view->EditURL.$item["id"]."/".$edit_append;
				} else {
					$link = "#".$item["id"];
				}
	?>
	<section class="view_action action_<?=$action?>"><a href="<?=$link?>" class="<?=$class?>" title="<?=Text::translate($action_title, true)?>"></a></section>
	<?php
			} else {
				$data = json_decode($data,true);
				$link = $module_page.$data["route"]."/".$item["id"]."/";
				
				if ($data["function"]) {
					$link = call_user_func($data["function"], $item);
				}
	?>
	<section class="view_action"><a href="<?=$link?>" class="<?=$data["class"]?>" title="<?=Text::translate($data["name"], true)?>"></a></section>
	<?php
			}
		}
	?>
</li>
<?php
	}
	