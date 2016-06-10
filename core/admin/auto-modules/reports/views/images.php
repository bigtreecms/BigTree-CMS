<?php
	namespace BigTree;

	/**
	 * @global array $items
	 * @global Module $module
	 * @global ModuleReport $report
	 * @global ModuleView $view
	 */
?>
<div class="table auto_modules image_list">
	<summary>
		<h2><?=Text::translate("Filtered Data")?></h2>
	</summary>
	<section>
		<ul id="image_list">
			<?php
				foreach ($callout_list as $item) {
					if ($view->Settings["prefix"]) {
						$preview_image = FileSystem::getPrefixedFile($item[$view->Settings["image"]],$view->Settings["prefix"]);
					} else {
						$preview_image = $item[$view->Settings["image"]];
					}

					$entry_permission = $module->getUserAccessLevelForEntry($item, $view->Table);

					if ($entry_permission && $entry_permission != "n") {
			?>
			<li id="row_<?=$item["id"]?>" class="non_draggable">
				<a class="image<?php if (empty($view->Actions["edit"])) { ?> image_disabled<?php } ?>" href="<?=$view->EditURL.$item["id"]?>/"><img src="<?=$preview_image?>" alt="" /></a>
				<?php
					foreach ($view->Actions as $action => $data) {
						if ($action != "edit") {
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
				?>
			</li>
			<?php
					}
				}
			?>
		</ul>
	</section>
</div>
<?php include Router::getIncludePath("admin/auto-modules/views/_common-js.php") ?>