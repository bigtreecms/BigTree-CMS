<?php
	namespace BigTree;
	
	/**
	 * @global string $callout_key
	 * @global array $content
	 */

	define("BIGTREE_CALLOUT_RESOURCES", true);
	
	if (isset($_POST["resources"])) {
		$content = json_decode(base64_decode($_POST["resources"]), true);
	}
	
	if (isset($_POST["type"])) {
		$content["type"] = $_POST["type"];
	}
	
	if (isset($_POST["key"])) {
		$callout_key = htmlspecialchars($_POST["key"]);
	}

	$content = Link::decode($content);
	$callout = new Callout($content["type"]);
	$callout_count = intval($_POST["count"]);
	
	if ($_POST["type"] != $_POST["original_type"]) {
		if (Callout::exists($_POST["original_type"])) {
			$original_callout = new Callout($_POST["original_type"]);
			$forced_recrops = Field::rectifyTypeChange($content, $callout->Fields, $original_callout->Fields);
		} else {
			$content = [];
		}
	} else {
		$forced_recrops = [];
	}
	
	if ($callout->Description) {
?>
<p class="callout_description"><?=Text::htmlEncode($callout->Description)?></p>
<?php
	}
?>
<p class="error_message" style="display: none;"><?=Text::translate("Errors found! Please fix the highlighted fields before submitting.")?></p>
<div class="form_fields">
	<?php
		// Run hooks for modifying the field array
		$callout->Fields = Extension::runHooks("fields", "callout", $callout->Fields, [
			"callout" => $callout,
			"step" => "draw"
		]);
		
		if (count($callout->Fields)) {
			Field::$Namespace = uniqid("callout_field_");
			Field::$GlobalTabIndex = 1000 * intval($_POST["tab_depth"]);

			foreach ($callout->Fields as $field) {
				$field = [
					"type" => $field["type"],
					"title" => $field["title"],
					"subtitle" => $field["subtitle"],
					"key" => $callout_key."[".$callout_count."][".$field["id"]."]",
					"has_value" => isset($content[$field["id"]]),
					"value" => isset($content[$field["id"]]) ? $content[$field["id"]] : "",
					"settings" => $field["settings"],
					"forced_recrop" => isset($forced_recrops[$resource["id"]]) ? true : false
				];

				if (empty($field["settings"]["directory"])) {
					$field["settings"]["directory"] = "files/callouts/";
				}
		
				$field = new Field($field);
				$field->draw();
			}
		} else {
			echo '<p>'.Text::translate("There are no resources for the selected callout.").'</p>';
		}
	?>
</div>
<input type="hidden" name="<?=$callout_key?>[<?=$callout_count?>][display_default]" class="display_default" value="<?=$callout->DisplayDefault?>" />
<input type="hidden" name="<?=$callout_key?>[<?=$callout_count?>][display_field]" class="display_field" value="<?=$callout_key?>[<?=$callout_count?>][<?=$callout->DisplayField?>]" />
<?php
	// Only re-run if we're loading a new callout type
	if (isset($_POST["type"])) {
?>
<script>	
	BigTreeCustomControls($("#callout_resources"));
</script>

<?php
	}
	
	include Router::getIncludePath("admin/layouts/_html-field-loader.php");
	include Router::getIncludePath("admin/layouts/_ajax-ready-loader.php");
?>	