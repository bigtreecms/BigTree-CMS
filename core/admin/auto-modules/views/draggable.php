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
			foreach ($view["fields"] as $key => $field) {
				$x++;
		?>
		<span class="view_column" style="width: <?=$field["width"]?>px;"><?=$field["title"]?></span>
		<?
			}
		?>
		<span class="view_status">Status</span>		
		<?
			foreach ($view["actions"] as $action => $data) {
				if ($data != "on") {
					$data = json_decode($data,true);
					$action = $data["name"];
				}
		?>
		<span class="view_action"><?=$action?></span>
		<?
			}
		?>
	</header>
	<ul id="sort_table">
		<? include BigTree::path("admin/ajax/auto-modules/views/draggable.php") ?>
	</ul>
</div>

<? include BigTree::path("admin/auto-modules/views/_common-js.php") ?>
<script>
	function _local_search() {
		$("#sort_table").load("<?=ADMIN_ROOT?>ajax/auto-modules/views/draggable/", { view: <?=$view["id"]?>, search: $("#search").val() }, _local_createSortable);
	}
	
	function _local_createSortable() {
		<? if ($permission == "p") { ?>
		if ($("#search").val() == "") {
			$("#sort_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: function() {
				$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/order/", { type: "POST", data: { view: "<?=$view["id"]?>", sort: $("#sort_table").sortable("serialize") } });
			}});
		}
		<? } ?>
	}
	
	_local_createSortable();
</script>