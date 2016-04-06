<?php
	use BigTree\FileSystem;
	
	// Prevent path manipulation shenanigans
	$type = FileSystem::getSafePath($_POST["type"]);
	$table = $_POST["table"];
	$options = json_decode(str_replace(array("\r","\n"),array('\r','\n'),$_POST["data"]),true);
	$filter = isset($options["filter"]) ? $options["filter"] : "";
?>
<div style="width: 450px;">
	<fieldset>
		<label>Filter Function <small>(function name only, <a href="http://www.bigtreecms.org/docs/dev-guide/modules/advanced-techniques/view-filters/" target="_blank">learn more</a>)</small></label>
		<input type="text" name="filter" value="<?=htmlspecialchars($filter)?>" />
	</fieldset>
	<?php
		if (strpos($type,"*") !== false) {
			list($extension,$view_type) = explode("*",$type);
			$path = SERVER_ROOT."extensions/$extension/plugins/view-types/$view_type/options.php";
		} else {
			$path = BigTree::path("admin/ajax/developer/view-options/$type.php");
		}
		if (file_exists($path)) {
			include $path;
		}
	?>
</div>

<script>
	BigTree.localTable = false;
	
	$(".table_select").change(function() {
		var x = 0;
		BigTree.localTable = $(this).val();
		
		$(this).parents("fieldset").nextAll("fieldset").each(function() {
			var div = $(this).find("div");
			if (div.length && div.attr("data-name")) {
				if (div.hasClass("sort_by")) {
					div.load("<?=ADMIN_ROOT?>ajax/developer/load-table-columns/?sort=true&table=" + BigTree.localTable + "&field=" + div.attr("data-name"), BigTreeCustomControls);
				} else {
					div.load("<?=ADMIN_ROOT?>ajax/developer/load-table-columns/?table=" + BigTree.localTable + "&field=" + div.attr("data-name"), BigTreeCustomControls);
				}
			}
		});
	});
</script>