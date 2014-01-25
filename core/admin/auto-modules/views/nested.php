<?
	$search = isset($_GET["search"]) ? htmlspecialchars($_GET["search"]) : "";
?>
<script>
	BigTree.localSearch = function() {
		// If a search has been entered, revert to draggable
		$("#table_data").load("<?=ADMIN_ROOT?>ajax/auto-modules/views/nested/", { view: <?=$bigtree["view"]["id"]?>, search: $("#search").val() });
	};
	BigTree.localCreateSortable = function(element) {
		$(element).sortable({
			axis: "y",
			containment: "parent",
			handle: ".icon_sort",
			items: "> li",
			placeholder: "ui-sortable-placeholder",
			update: function(ev,ui) {
				$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/order/", { type: "POST", data: { view: "<?=$bigtree["view"]["id"]?>", sort: ui.item.parent().sortable("serialize") } });
			}
		});
	};
	BigTree.localMouseUp = function() {
		// Re-open the section we collapsed while dragging
		for (i = 0; i < BigTree.localPreviouslyExpanded.length; i++) {
			BigTree.localPreviouslyExpanded[i].addClass("expanded").children("ul").show();
		}
		$("body").off("mouseup",BigTree.localMouseUp);
	}
</script>
<div class="table auto_modules nested_table" id="nested_container">
	<summary>
		<input type="search" class="form_search" id="search" placeholder="Search" value="<?=$search?>" />
		<span class="form_search_icon"></span>
	</summary>
	<header>
		<?
			$x = 0;
			foreach ($bigtree["view"]["fields"] as $key => $field) {
				$x++;
		?>
		<span class="view_column" style="width: <?=$field["width"]?>px;"><?=$field["title"]?></span>
		<?
			}
		?>
		<span class="view_status">Status</span>		
		<span class="view_action" style="width: <?=(count($bigtree["view"]["actions"]) * 40)?>px;"><? if (count($bigtree["view"]["actions"]) > 1) { ?>Actions<? } ?></span>
	</header>
	<ul id="table_data">
		<? include BigTree::path("admin/ajax/auto-modules/views/nested.php") ?>
	</ul>
</div>

<? include BigTree::path("admin/auto-modules/views/_common-js.php") ?>
<script>
	$("#table_data").on("click",".view_column:first-of-type",function() {
		// Make sure we haven't searched
		if (!$(this).parents("div").hasClass("nested_table") || $(this).hasClass("disabled")) {
			return;
		}
		// Change expanded state
		li = $(this).parent();
		var ul = li.toggleClass("expanded").children("ul").toggle();
		$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/set-nest-state/", { type: "POST", data: { view: <?=$bigtree["view"]["id"]?>, id: li.attr("id").replace("row_",""), expanded: li.hasClass("expanded") } });
		<? if ($permission == "p") { ?>
		BigTree.localCreateSortable(ul);
		<? } ?>
	}).on("mousedown",".icon_sort",function() {
		// We're going to collapse the section so we can drag it easier.
		BigTree.localPreviouslyExpanded = [];
		li = $(this).parent().parent();
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