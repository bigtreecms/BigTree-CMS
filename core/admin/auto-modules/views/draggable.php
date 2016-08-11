<?php
	namespace BigTree;

	/**
	 * @global string $module_permission (set in the ajax file)
	 * @global ModuleView $view
	 */
	
	$query = isset($_GET["search"]) ? htmlspecialchars($_GET["search"]) : "";
?>
<div class="table auto_modules">
	<div class="table_summary">
		<input type="search" class="form_search" id="search" placeholder="<?=Text::translate("Search", true)?>" value="<?=$query?>" />
		<span class="form_search_icon"></span>
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
		<?php include Router::getIncludePath("admin/ajax/auto-modules/views/draggable.php") ?>
	</ul>
</div>

<?php include Router::getIncludePath("admin/auto-modules/views/_common-js.php") ?>
<script>
	BigTree.localSearch = function() {
		$("#sort_table").load("<?=ADMIN_ROOT?>ajax/auto-modules/views/draggable/", { view: <?=$view->ID?>, search: $("#search").val() }, BigTree.localCreateSortable);
	};

	BigTree.localCreateSortable = function() {
		<?php if ($module_permission == "p") { ?>
		if ($("#search").val() == "") {
			$("#sort_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: function() {
				$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/order/", { type: "POST", data: { view: "<?=$view->ID?>", sort: $("#sort_table").sortable("serialize") } });
			}});
		}
		<?php } ?>
	};
	
	BigTree.localCreateSortable();
</script>