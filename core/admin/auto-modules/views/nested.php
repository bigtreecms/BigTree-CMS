<?
	$search = isset($_GET["search"]) ? htmlspecialchars($_GET["search"]) : "";
?>
<div class="table auto_modules">
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
	<ul id="sort_table">
		<? include BigTree::path("admin/ajax/auto-modules/views/nested.php") ?>
	</ul>
</div>

<? include BigTree::path("admin/auto-modules/views/_common-js.php") ?>
<script>
	BigTree.localSearch = function() {
		// If a search has been entered, revert to draggable
		v = $("#search").val();
		if (v) {
			$("#sort_table").load("<?=ADMIN_ROOT?>ajax/auto-modules/views/draggable/", { view: <?=$bigtree["view"]["id"]?>, search: v });
		} else {
			$("#sort_table").load("<?=ADMIN_ROOT?>ajax/auto-modules/views/nested/", { view: <?=$bigtree["view"]["id"]?> });	
		}
	};

	BigTree.localCreateSortable = function() {
		<? if ($permission == "p") { ?>
		if ($("#search").val() == "") {
			$("#sort_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: function() {
				$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/order/", { type: "POST", data: { view: "<?=$bigtree["view"]["id"]?>", sort: $("#sort_table").sortable("serialize") } });
			}});
		}
		<? } ?>
	};
	
	BigTree.localCreateSortable();
</script>