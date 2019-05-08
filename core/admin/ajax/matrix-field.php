<?php
	namespace BigTree;

	// Draw field types as callout resources
	if (!defined("BIGTREE_CALLOUT_RESOURCES")) {
		define("BIGTREE_CALLOUT_RESOURCES", true);
	}

	$matrix_count = intval($_POST["count"]);
	$matrix_key = htmlspecialchars($_POST["key"]);
	$matrix_columns = $_POST["columns"];
	$content = [];

	if (isset($_POST["data"])) {
		$content = Link::decode(json_decode(base64_decode($_POST["data"]),true));
	}
?>
<div id="matrix_resources" class="callout_fields">
	<p class="error_message" style="display: none;"><?=Text::translate("Errors found! Please fix the highlighted fields before submitting.")?></p>
	<div class="form_fields">
		<?php
			if (count($matrix_columns)) {
				Field::$GlobalTabIndex = 1000 * intval($_POST["tab_depth"]);
				Field::$Namespace = uniqid("matrix_field_");
				
				foreach ($matrix_columns as $resource) {
					$settings = is_array($resource["settings"]) ? $resource["settings"] : @json_decode($resource["settings"], true);
					
					$field = new Field([
						"type" => $resource["type"],
						"title" => htmlspecialchars($resource["title"]),
						"subtitle" => htmlspecialchars($resource["subtitle"]),
						"key" => $matrix_key."[".$matrix_count."][".$resource["id"]."]",
						"has_value" => isset($content[$resource["id"]]),
						"value" => isset($content[$resource["id"]]) ? $content[$resource["id"]] : "",
						"settings" => is_array($settings) ? $settings : []
					]);

					// Apply custom fieldset class
					if ($resource["display_title"]) {
						$field->FieldsetClass = "matrix_title_field";
					}

					$field->draw();
				}
			} else {
				echo '<p>There are no resources for the selected callout.</p>';
			}
		?>
	</div>
</div>
<?php
	include Router::getIncludePath("admin/layouts/_html-field-loader.php");
	include Router::getIncludePath("admin/layouts/_ajax-ready-loader.php");
?>