<?php
	namespace BigTree;

	/**
	 * @global Module $module
	 * @global string $module_permission (set in ajax file)
	 * @global ModuleView $view
	 */
	
	$query = isset($_GET["search"]) ? htmlspecialchars($_GET["search"]) : "";
?>
<script>
	BigTree.localSearch = function() {
		// If a search has been entered, revert to draggable
		$("#table_data").load("<?=ADMIN_ROOT?>ajax/auto-modules/views/nested/", { view: <?=$view->ID?>, search: $("#search").val() });
	};
	BigTree.localCreateSortable = function(element) {
		$(element).sortable({
			axis: "y",
			containment: "parent",
			handle: ".js-hook-sort",
			items: "> li",
			placeholder: "ui-sortable-placeholder",
			update: function(ev,ui) {
				$.secureAjax("<?=ADMIN_ROOT?>ajax/auto-modules/views/order/", { type: "POST", data: { view: "<?=$view->ID?>", sort: ui.item.parent().sortable("serialize") } });
			}
		});
	};
	BigTree.localMouseUp = function() {
		// Re-open the section we collapsed while dragging
		for (var i = 0; i < BigTree.localPreviouslyExpanded.length; i++) {
			BigTree.localPreviouslyExpanded[i].addClass("expanded").children("ul").show();
		}
		$("body").off("mouseup",BigTree.localMouseUp);
	}
</script>
<div class="table auto_modules nested_table" id="nested_container">
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
	<ul id="table_data">
		<?php include Router::getIncludePath("admin/ajax/auto-modules/views/nested.php") ?>
	</ul>
</div>

<?php include Router::getIncludePath("admin/auto-modules/views/_common-js.php") ?>
<script>
	$("#table_data").on("click",".view_column:first-of-type",function() {
		// Make sure we haven't searched
		if (!$(this).parents("div").hasClass("nested_table") || $(this).hasClass("disabled")) {
			return;
		}
		// Change expanded state
		var li = $(this).parent();
		var ul = li.toggleClass("expanded").children("ul").toggle();
		$.secureAjax("<?=ADMIN_ROOT?>ajax/auto-modules/views/set-nest-state/", { type: "POST", data: { view: <?=$view->ID?>, id: li.attr("id").replace("row_",""), expanded: li.hasClass("expanded") } });
		<?php if ($module_permission == "p") { ?>
		BigTree.localCreateSortable(ul);
		<?php } ?>
	}).on("mousedown",".js-hook-sort",function() {
		// We're going to collapse the section so we can drag it easier.
		BigTree.localPreviouslyExpanded = [];
		var li = $(this).parent().parent();
		if (li.hasClass("expanded")) {
			BigTree.localPreviouslyExpanded[0] = li;
			li.removeClass("expanded").children("ul").hide();
			li.find(".expanded").each(function(index,el) {
				BigTree.localPreviouslyExpanded[BigTree.localPreviouslyExpanded.length] = $(el);
				$(el).removeClass("expanded").children("ul").hide();
			});
		}
		$("body").on("mouseup",BigTree.localMouseUp);
	});
</script>