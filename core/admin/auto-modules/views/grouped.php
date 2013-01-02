<?
	$m = BigTreeAutoModule::getModuleForView($view);
	$perm = $admin->getAccessLevel($m);
	$search = isset($_GET["search"]) ? htmlspecialchars($_GET["search"]) : "";
?>
<div class="table">
	<summary>
		<input type="search" class="form_search" id="search" placeholder="Search" value="<?=$search?>" />
		<span class="form_search_icon"></span>
	</summary>
	<article class="table" id="table_contents">
		<? include BigTree::path("admin/ajax/auto-modules/views/grouped.php") ?>
	</article>
</div>

<? include BigTree::path("admin/auto-modules/views/_common-js.php") ?>
<script>
	function _local_search() {
		$("#table_contents").load("<?=ADMIN_ROOT?>ajax/auto-modules/views/grouped/", { view: <?=$view["id"]?>, search: $("#search").val() }, _local_refreshSort);
	}

	function _local_refreshSort() {
		<? if ($permission == "p" && $draggable) { ?>
		$("#table_contents ul").each(function() {
			if ($("#search").val() == "") {
				$(this).sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: $.proxy(function() {
					$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/order/", { type: "POST", data: { view: "<?=$view["id"]?>", table_name: $(this).attr("id"), sort: $(this).sortable("serialize") } });
				},this) });
			}
		});
		<? } ?>
	}
	
	_local_refreshSort();
</script>