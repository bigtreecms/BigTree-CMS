<div class="table">
	<summary>
		<input type="search" class="form_search" id="search" placeholder="Search" />
	</summary>
	<article class="table auto_modules" id="table_contents">
		<? include BigTree::path("admin/ajax/auto-modules/views/grouped.php") ?>
	</article>
</div>

<? include BigTree::path("admin/auto-modules/views/_common-js.php") ?>
<script type="text/javascript">
	function reSearch() {
		$("#table_contents").load("<?=$admin_root?>ajax/auto-modules/views/grouped/", { view: <?=$view["id"]?>, search: $("#search").val() }, _local_refreshSort);
	}

	function _local_refreshSort() {
		<? if ($perm == "p" && $o["draggable"]) { ?>
		$("#table_contents ul").each(function() {
			if ($("#search").val() == "") {
				$(this).sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: $.proxy(function() {
					$.ajax("<?=$admin_root?>ajax/auto-modules/views/order/?view=<?=$view["id"]?>&table_name=" + $(this).attr("id") + "&sort=" + escape($(this).sortable("serialize")));
				},this) });
			}
		});
		<? } ?>
	}
	
	_local_refreshSort();
</script>