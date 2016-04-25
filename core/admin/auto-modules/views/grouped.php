<?php
	namespace BigTree;
	
	$perm = $admin->getAccessLevel($bigtree["view"]["module"]);
	$search = isset($_GET["search"]) ? htmlspecialchars($_GET["search"]) : "";
?>
<div class="table">
	<summary>
		<input type="search" class="form_search" id="search" placeholder="<?=Text::translate("Search", true)?>" value="<?=$search?>" />
		<span class="form_search_icon"></span>
	</summary>
	<article class="table" id="table_contents">
		<?php include Router::getIncludePath("admin/ajax/auto-modules/views/grouped.php") ?>
	</article>
</div>

<?php include Router::getIncludePath("admin/auto-modules/views/_common-js.php") ?>
<script>
	BigTree.localSearch = function() {
		$("#table_contents").load("<?=ADMIN_ROOT?>ajax/auto-modules/views/grouped/", { view: <?=$bigtree["view"]["id"]?>, search: $("#search").val() }, BigTree.localRefreshSort);
	};

	BigTree.localRefreshSort = function() {
		<?php if ($permission == "p" && $draggable) { ?>
		$("#table_contents ul").each(function() {
			if ($("#search").val() == "") {
				$(this).sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: $.proxy(function() {
					$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/order/", { type: "POST", data: { view: "<?=$bigtree["view"]["id"]?>", table_name: $(this).attr("id"), sort: $(this).sortable("serialize") } });
				},this) });
			}
		});
		<?php } ?>
	};
	
	BigTree.localRefreshSort();
</script>