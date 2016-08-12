<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */

	define("BIGTREE_CALLOUT_RESOURCES",true);
	
	if (isset($_POST["resources"])) {
		$bigtree["resources"] = json_decode(base64_decode($_POST["resources"]),true);
	}
	
	if (isset($_POST["type"])) {
		$bigtree["resources"]["type"] = $_POST["type"];
	}
	
	if (isset($_POST["key"])) {
		$bigtree["callout_key"] = htmlspecialchars($_POST["key"]);
	}

	$bigtree["resources"] = Link::decode($bigtree["resources"]);
	$bigtree["callout_count"] = intval($_POST["count"]);

	$callout = new Callout($bigtree["resources"]["type"]);
	$bigtree["callout"] = $callout->Array; // Backwards compatibility with fields that might read the callout array
	
	if ($callout->Description) {
?>
<p class="callout_description"><?=Text::htmlEncode($callout->Description)?></p>
<?php
	}
?>
<p class="error_message" style="display: none;"><?=Text::translate("Errors found! Please fix the highlighted fields before submitting.")?></p>
<div class="form_fields">
	<?php
		if (count($callout->Fields)) {

			Field::$Namespace = uniqid("callout_field_");

			$bigtree["field_types"] = FieldType::reference(false,"callouts");	
			$bigtree["tabindex"] = 1000 * intval($_POST["tab_depth"]);	
			$bigtree["html_fields"] = array();
			$bigtree["simple_html_fields"] = array();			

			foreach ($callout->Fields as $field) {
				$field = array(
					"type" => $field["type"],
					"title" => $field["title"],
					"subtitle" => $field["subtitle"],
					"key" => $bigtree["callout_key"]."[".$bigtree["callout_count"]."][".$field["id"]."]",
					"value" => isset($bigtree["resources"][$field["id"]]) ? $bigtree["resources"][$field["id"]] : "",
					"tabindex" => $bigtree["tabindex"],
					"options" => $field["options"]
				);

				if (empty($field["options"]["directory"])) {
					$field["options"]["directory"] = "files/callouts/";
				}
		
				$field = new Field($field);
				$field->draw();
			}
		} else {
			echo '<p>'.Text::translate("There are no resources for the selected callout.").'</p>';
		}
	?>
</div>
<input type="hidden" name="<?=$bigtree["callout_key"]?>[<?=$bigtree["callout_count"]?>][display_default]" class="display_default" value="<?=$callout->DisplayDefault?>" />
<input type="hidden" name="<?=$bigtree["callout_key"]?>[<?=$bigtree["callout_count"]?>][display_field]" class="display_field" value="<?=$bigtree["callout_key"]?>[<?=$bigtree["callout_count"]?>][<?=$callout->DisplayField?>]" />
<?php
	// Only re-run if we're loading a new callout type
	if (isset($_POST["type"])) {
?>
<script>	
	BigTreeCustomControls($("#callout_resources"));
</script>

<?php
	}

	$bigtree["html_editor_width"] = 440;
	$bigtree["html_editor_height"] = 200;
	
	include Router::getIncludePath("admin/layouts/_html-field-loader.php");
	include Router::getIncludePath("admin/layouts/_ajax-ready-loader.php");
?>	