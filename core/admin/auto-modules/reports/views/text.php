<?php
	namespace BigTree;

	/**
	 * @global array $items
	 * @global Module $module
	 * @global ModuleReport $report
	 * @global ModuleView $view
	 */
?>
<div class="table auto_modules">
	<div class="table_summary">
		<h2><?=Text::translate("Filtered Data")?></h2>
	</div>
	<header>
		<?php
			$x = 0;
			foreach ($view->Fields as $key => $field) {
				$x++;
		?>
		<span class="view_column" style="width: <?=$field["width"]?>px;"><?=$field["title"]?></span>
		<?php
			}
		?>
		<span class="view_status"><?=Text::translate("Status")?></span>		
		<span class="view_action" style="width: <?=(count($view->Actions) * 40)?>px;"><?php if (count($view->Actions) > 1) { echo Text::translate("Actions"); } ?></span>
	</header>
	<ul id="sort_table">
		<?php
			foreach ($callout_list as $item) {
				// Get the status
				if (PendingChange::existsForEntry($view->Table, $item["id"])) {
					$status_class = "pending";
					$status = "Changed";
				} else {
					$status_class = "published";
					$status = "Published";
				}

				$entry_permission = Auth::user()->getAccessLevel($module, $item, $view->Table);

				if ($entry_permission && $entry_permission != "n") {
		?>
		<li id="row_<?=$item["id"]?>" class="<?=$status_class?>">
			<?php foreach ($view->Fields as $key => $field) { ?>
			<section class="view_column" style="width: <?=$field["width"]?>px;"><?=$item[$key]?></section>
			<?php } ?>
			<section class="view_status status_<?=$status_class?>"><?=Text::translate($status)?></section>
			<?php
				foreach ($view->Actions as $action => $data) {
					if ($data == "on") {
						if (($action == "delete" || $action == "approve" || $action == "feature" || $action == "archive") && $entry_permission != "p") {
							if ($action == "delete") {
								$class = "icon_delete";
							} else {
								$class = "icon_disabled";
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
			<section class="view_action action_<?=$action?>"><a href="<?=$link?>" class="<?=$class?>" title="<?=ucwords($action)?>"></a></section>
			<?php
					} else {
						$data = json_decode($data,true);
						$link = MODULE_ROOT.$data["route"]."/".$item["id"]."/";
						
						if ($data["function"]) {
							$link = call_user_func($data["function"],$item);
						}
			?>
			<section class="view_action"><a href="<?=$link?>" class="<?=$data["class"]?>" title="<?=Text::htmlEncode($data["name"])?>"></a></section>
			<?php
					}
				}
			?>
		</li>
		<?php
				}
			}
		?>
	</ul>
</div>
<?php include Router::getIncludePath("admin/auto-modules/views/_common-js.php") ?>