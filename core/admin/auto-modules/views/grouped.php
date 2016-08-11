<?php
	namespace BigTree;

	/**
	 * @global bool $draggable (set in ajax file)
	 * @global string $module_permission (set in ajax file)
	 * @global ModuleView $view
	 */

	$query = isset($_GET["search"]) ? htmlspecialchars($_GET["search"]) : "";
?>
<div class="table">
	<div class="table_summary">
		<input type="search" class="form_search" id="search" placeholder="<?=Text::translate("Search", true)?>" value="<?=$query?>" />
		<span class="form_search_icon"></span>
	</div>
	<article class="table" id="table_contents">
		<?php include Router::getIncludePath("admin/ajax/auto-modules/views/grouped.php") ?>
	</article>
</div>

<?php include Router::getIncludePath("admin/auto-modules/views/_common-js.php") ?>
<script>
	BigTree.localSearch = function() {
		$("#table_contents").load("<?=ADMIN_ROOT?>ajax/auto-modules/views/grouped/", { view: <?=$view->ID?>, search: $("#search").val() }, BigTree.localRefreshSort);
	};

	BigTree.localRefreshSort = function() {
		<?php if ($module_permission == "p" && $draggable) { ?>
		$("#table_contents").find("ul").each(function() {
			if ($("#search").val() == "") {
				$(this).sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: $.proxy(function() {
					$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/order/", { type: "POST", data: { view: "<?=$view->ID?>", table_name: $(this).attr("id"), sort: $(this).sortable("serialize") } });
				},this) });
			}
		});
		<?php } ?>
	};
	
	BigTree.localRefreshSort();
</script>