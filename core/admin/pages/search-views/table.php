<?php
	namespace BigTree;

	/**
	 * @global array $items
	 * @global Module $module
	 * @global ModuleView $view
	 */

	$module_link = ADMIN_ROOT.$module->Route."/";

	// Figure out the column width
	$action_width = count($view->Actions) * 40;
	$available = 896 - $action_width;
	$per_column_width = floor($available / count($view->Fields));
	
	foreach ($view->Fields as $key => $field) {
		$view->Fields[$key]["width"] = $per_column_width - 20;
	}

	// Run any parsers on the data
	$items = $view->parseData($items);
?>
<div class="table" style="margin: 0;">
	<summary><h2><?=Text::translate("Search Results")?></h2></summary>
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
		<span class="view_action" style="width: <?=(count($view->Actions) * 40)?>px;"><?=Text::translate("Actions")?></span>
	</header>
	<ul id="results_table_<?=$view->ID?>">
		<?php foreach ($items as $item) { ?>
		<li id="row_<?=$item["id"]?>"<?php if ($item["bigtree_pending"]) { ?> class="pending"<?php } ?><?php if ($item["bigtree_changes"]) { ?> class="changes"<?php } ?>>
		<?php
			$x = 0;
			foreach ($view->Fields as $key => $field) {
				$x++;
				$value = strip_tags($item[$key]);
		?>
		<section class="view_column" style="width: <?=$field["width"]?>px;">
			<?=$value?>
		</section>
		<?php
			}
	
			foreach ($view->Actions as $action => $data) {
				$class = $view->generateActionClass($action, $item);

				if ($data == "on") {
		?>
		<section class="view_action action_<?=$action?>"><a href="#<?=$item["id"]?>" class="<?=$class?>"></a></section>
		<?php
				} else {
					$data = json_decode($data, true);
					$link = $module_link.$data["route"]."/".$item["id"]."/";

					if ($data["function"]) {
						$link = call_user_func($data["function"],$item);
					}
		?>
		<section class="view_action"><a href="<?=$link?>" class="<?=$data["class"]?>"></a></section>
		<?php
				}
			}
		?>
	</li>
	<?php } ?>
	</ul>
</div>

<script>
	$("#results_table_<?=$view->ID?> .icon_edit").click(function() {
		document.location.href = "<?=$view->EditURL?>" + $(this).attr("href").substr(1) + "/";
		return false;
	});
			
	$("#results_table_<?=$view->ID?> .icon_delete").click(function() {
		BigTreeDialog({
			title: "<?=Text::translate("Delete Item")?>",
			content: '<p class="confirm"><?=Text::translate("Are you sure you want to delete this item?")?>',
			icon: "delete",
			alternateSaveText: "<?=Text::translate("OK")?>",
			callback: $.proxy(function() {
				$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/delete/?view=<?=$view->ID?>&id=" + $(this).attr("href").substr(1));
				$(this).parents("li").remove();
			},this)
		});
		
		return false;
	});
	$("#results_table_<?=$view->ID?> .icon_approve").click(function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/approve/?view=<?=$view->ID?>&id=" + $(this).attr("href").substr(1));
		$(this).toggleClass("icon_approve_on");
		return false;
	});
	$("#results_table_<?=$view->ID?> .icon_feature").click(function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/feature/?view=<?=$view->ID?>&id=" + $(this).attr("href").substr(1));
		$(this).toggleClass("icon_feature_on");
		return false;
	});
	$("#results_table_<?=$view->ID?> .icon_archive").click(function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/archive/?view=<?=$view->ID?>&id=" + $(this).attr("href").substr(1));
		$(this).toggleClass("icon_archive_on");
		return false;
	});
</script>