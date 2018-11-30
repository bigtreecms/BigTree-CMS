<?php
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

	if (!empty($_POST["front_end_editor"]) && $_POST["front_end_editor"] != "false") {
		define("BIGTREE_FRONT_END_EDITOR", true);
	}

	foreach ($bigtree["resources"] as &$val) {
		if (is_array($val)) {
			$val = BigTree::untranslateArray($val);
		} elseif (is_array(json_decode($val,true))) {
			$val = BigTree::untranslateArray(json_decode($val,true));
		} else {
			$val = $cms->replaceInternalPageLinks($val);
		}
	}
	unset($val);

	$bigtree["callout_count"] = intval($_POST["count"]);
	$bigtree["callout"] = $admin->getCallout($bigtree["resources"]["type"]);

	if ($_POST["type"] != $_POST["original_type"]) {
		$original_callout = $admin->getCallout($_POST["original_type"]);
		$forced_recrops = $admin->rectifyResourceTypeChange($bigtree["resources"], $bigtree["callout"]["resources"], $original_callout["resources"]);
	} else {
		$forced_recrops = [];
	}

	$cached_types = $admin->getCachedFieldTypes();
	$bigtree["field_types"] = $cached_types["callouts"];
	
	if ($bigtree["callout"]["description"]) {
?>
<p class="callout_description"><?=BigTree::safeEncode($bigtree["callout"]["description"])?></p>
<?php
	}
?>
<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>
<div class="form_fields">
	<?php
		// Run hooks for modifying the field array
		$bigtree["callout"]["resources"] = $admin->runHooks("fields", "callout", $bigtree["callout"]["resources"], [
			"callout" => $bigtree["callout"],
			"step" => "draw"
		]);

		if (count($bigtree["callout"]["resources"])) {
			$cached_types = $admin->getCachedFieldTypes();
			$bigtree["field_types"] = $cached_types["callouts"];
	
			$bigtree["tabindex"] = 1000 * intval($_POST["tab_depth"]);	
			$bigtree["html_fields"] = array();
			$bigtree["simple_html_fields"] = array();
			$bigtree["field_namespace"] = uniqid("callout_field_");
			$bigtree["field_counter"] = 0;

			foreach ($bigtree["callout"]["resources"] as $resource) {
				$field = array(
					"type" => $resource["type"],
					"title" => $resource["title"],
					"subtitle" => $resource["subtitle"],
					"key" => $bigtree["callout_key"]."[".$bigtree["callout_count"]."][".$resource["id"]."]",
					"has_value" => isset($bigtree["resources"][$resource["id"]]),
					"value" => isset($bigtree["resources"][$resource["id"]]) ? $bigtree["resources"][$resource["id"]] : "",
					"tabindex" => $bigtree["tabindex"],
					"settings" => $resource["settings"] ?: $resource["options"],
					"forced_recrop" => isset($forced_recrops[$resource["id"]]) ? true : false
				);

				if (empty($field["settings"]["directory"])) {
					$field["settings"]["directory"] = "files/callouts/";
				}
	
				BigTreeAdmin::drawField($field);
			}
		} else {
			echo '<p>There are no resources for the selected callout.</p>';
		}
	?>
</div>
<input type="hidden" name="<?=$bigtree["callout_key"]?>[<?=$bigtree["callout_count"]?>][display_default]" class="display_default" value="<?=$bigtree["callout"]["display_default"]?>" />
<input type="hidden" name="<?=$bigtree["callout_key"]?>[<?=$bigtree["callout_count"]?>][display_field]" class="display_field" value="<?=$bigtree["callout_key"]?>[<?=$bigtree["callout_count"]?>][<?=$bigtree["callout"]["display_field"]?>]" />
<?php
	// Only re-run if we're loading a new callout type
	if (isset($_POST["type"])) {
?>
<script>	
	BigTreeCustomControls($(".callout_fields"));
</script>

<?php
	}
	$bigtree["html_editor_width"] = 440;
	$bigtree["html_editor_height"] = 200;	
	include BigTree::path("admin/layouts/_html-field-loader.php");
	include BigTree::path("admin/layouts/_ajax-ready-loader.php");
?>	