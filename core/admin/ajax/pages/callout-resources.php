<?
	if (isset($_POST["data"])) {
		$resources = json_decode(base64_decode($_POST["data"]),true);
		foreach ($resources as &$val) {
			if (is_array($val)) {
				$val = BigTree::untranslateArray($val);
			} elseif (is_array(json_decode($val,true))) {
				$val = BigTree::untranslateArray(json_decode($val,true));
			} else {
				$val = $cms->replaceInternalPageLinks($val);
			}
		}
		
		$type = $resources["type"];
	}
	
	if (isset($_POST["count"])) {
		$count = $_POST["count"];
	}
	
	$type = isset($_POST["type"]) ? $_POST["type"] : $type;
	
	$callout = $admin->getCallout($type);
	
	if ($callout["description"]) {
?>
<p><?=htmlspecialchars($callout["description"])?></p>
<?
	}
	
	echo '<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>';
	
	$tabindex = 1000;
	
	if (count($callout["resources"])) {
		foreach ($callout["resources"] as $options) {
			$key = "callouts[$count][".$options["id"]."]";
			$type = $options["type"];
			$title = $options["title"];
			$subtitle = $options["subtitle"];
			$options["directory"] = "files/pages/";
			$value = $resources[$options["id"]];
			$currently_key = "callouts[$count][currently_".$options["id"]."]";
			
			// Setup Validation Classes
			$label_validation_class = "";
			$input_validation_class = "";
			if ($options["validation"]) {
				if (strpos($options["validation"],"required") !== false) {
					$label_validation_class = ' class="required"';
				}
				$input_validation_class = ' class="'.$options["validation"].'"';
			}
			
			include BigTree::path("admin/form-field-types/draw/$type.php");
			$tabindex++;
		}
	}
?>
<input type="hidden" name="callouts[<?=$count?>][display_default]" class="display_default" value="<?=$callout["display_default"]?>" />
<input type="hidden" name="callouts[<?=$count?>][display_field]" class="display_field" value="callouts[<?=$count?>][<?=$callout["display_field"]?>]" />
<input type="hidden" name="callouts[<?=$count?>][callout_count]" class="callout_count" value="<?=$count?>" />
<script type="text/javascript">
	BigTreeCustomControls();
	
	if (!tinyMCE) {
		tiny = new Element("script");
		tiny.src = "<?=ADMIN_ROOT?>js/tiny_mce/tiny_mce.js";
		$("body").append(tiny);
	}
</script>
<?
	$mce_width = 400;
	$mce_height = 150;
	
	if (count($htmls)) {
		include BigTree::path("admin/layouts/_tinymce_specific.php");
	}
	if (count($simplehtmls)) {
		include BigTree::path("admin/layouts/_tinymce_specific_simple.php");
	}
?>