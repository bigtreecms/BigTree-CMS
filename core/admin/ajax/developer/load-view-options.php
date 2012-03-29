<?
	$table = $_POST["table"];
	$t = $_POST["type"];
	$d = json_decode($_POST["data"],true);
?>
<div style="width: 450px;">
	<?
		$path = BigTree::path("admin/ajax/developer/view-options/".$t.".php");
		if (file_exists($path)) {
			include $path;
		}
	?>
</div>

<script type="text/javascript">
	var _local_table;
	
	$(".table_select").change(function() {
		x = 0;
		_local_table = $(this).val();
		
		$(this).parents("fieldset").nextAll("fieldset").each(function() {
			div = $(this).find("div");
			if (div.length) {
				if (div.hasClass("sort_by")) {
					div.load("<?=$admin_root?>ajax/developer/load-table-columns/?sort=true&table=" + _local_table + "&field=" + div.attr("name"));
				} else {
					div.load("<?=$admin_root?>ajax/developer/load-table-columns/?table=" + _local_table + "&field=" + div.attr("name"));
				}
			}
		});
	});
</script>