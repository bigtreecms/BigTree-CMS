<?php
	namespace BigTree;
	
	// Prevent path manipulation shenanigans
	$type = FileSystem::getSafePath($_POST["type"]);
	$table = $_POST["table"];
	$settings = json_decode(str_replace(array("\r", "\n"), array('\r', '\n'), $_POST["data"]), true);
	$filter = isset($settings["filter"]) ? $settings["filter"] : "";
?>
<div style="width: 450px;">
	<fieldset>
		<label for="settings_field_filter_function"><?=Text::translate('Filter Function <small>(function name only, <a href=":doc_link:" target="_blank">learn more</a>)</small>', false, array(":doc_link:" => "http://www.bigtreecms.org/docs/dev-guide/modules/advanced-techniques/view-filters/"))?></label>
		<input id="settings_field_filter_function" type="text" name="filter" value="<?=htmlspecialchars($filter)?>" />
	</fieldset>
	<?php
		if (strpos($type,"*") !== false) {
			list($extension,$view_type) = explode("*",$type);
			$path = SERVER_ROOT."extensions/$extension/plugins/view-types/$view_type/settings.php";
		} else {
			$path = Router::getIncludePath("admin/ajax/developer/view-settings/$type.php");
		}
		
		if (file_exists($path)) {
			include $path;
		}
	?>
</div>

<script>
	(function() {
		var Table = false;
		
		$(".table_select").change(function() {
			Table = $(this).val();
			
			$(this).parents("fieldset").nextAll("fieldset").each(function() {
				var div = $(this).find("div");
				
				if (div.length && div.attr("data-name")) {
					if (div.hasClass("sort_by")) {
						div.load("<?=ADMIN_ROOT?>ajax/developer/load-table-columns/?sort=true&table=" + Table + "&field=" + div.attr("data-name"), BigTreeCustomControls);
					} else {
						div.load("<?=ADMIN_ROOT?>ajax/developer/load-table-columns/?table=" + Table + "&field=" + div.attr("data-name"), BigTreeCustomControls);
					}
				}
			});
		});
	})();
</script>