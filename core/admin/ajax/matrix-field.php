<?php
	namespace BigTree;

	// Draw field types as callout resources
	define("BIGTREE_CALLOUT_RESOURCES",true);

	$bigtree["matrix_count"] = intval($_POST["count"]);
	$bigtree["matrix_key"] = htmlspecialchars($_POST["key"]);
	$bigtree["matrix_columns"] = $_POST["columns"];
	$bigtree["resources"] = array();

	if (isset($_POST["data"])) {
		$bigtree["resources"] = Link::decode(json_decode(base64_decode($_POST["data"]),true));
	}
?>
<div id="matrix_resources" class="callout_fields">
	<p class="error_message" style="display: none;"><?=Text::translate("Errors found! Please fix the highlighted fields before submitting.")?></p>
	<div class="form_fields">
		<?php
			if (count($bigtree["matrix_columns"])) {

				$bigtree["field_types"] = FieldType::reference(false,"callouts");
				$bigtree["tabindex"] = 1000 * intval($_POST["tab_depth"]);
				$bigtree["html_fields"] = array();
				$bigtree["simple_html_fields"] = array();

				Field::$Namespace = uniqid("matrix_field_");
				
				foreach ($bigtree["matrix_columns"] as $resource) {
					$options = @json_decode($resource["options"],true);
					
					$field = new Field(array(
						"type" => $resource["type"],
						"title" => htmlspecialchars($resource["title"]),
						"subtitle" => htmlspecialchars($resource["subtitle"]),
						"key" => $bigtree["matrix_key"]."[".$bigtree["matrix_count"]."][".$resource["id"]."]",
						"value" => isset($bigtree["resources"][$resource["id"]]) ? $bigtree["resources"][$resource["id"]] : "",
						"tabindex" => $bigtree["tabindex"],
						"options" => is_array($options) ? $options : array()
					));

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
	$bigtree["html_editor_width"] = 440;
	$bigtree["html_editor_height"] = 200;

	include Router::getIncludePath("admin/layouts/_html-field-loader.php");
	include Router::getIncludePath("admin/layouts/_ajax-ready-loader.php");
?>