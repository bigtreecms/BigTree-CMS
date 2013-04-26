<?
	$template_id = $page["template"];
	if (isset($_POST["page"])) {
		$template_id = $_POST["template"];
		$page = $cms->getPendingPage($_POST["page"]);
		$resources = $page["resources"];
		$callouts = $page["callouts"];
	} elseif (!isset($resources) && !isset($callouts)) {
		$resources = array();
		$callouts = array();
	}

	$template = $cms->getTemplate($template_id);

	if (!$template["image"]) {
		$image = ADMIN_ROOT."images/templates/page.png";
	} else {
		$image = ADMIN_ROOT."images/templates/".$template["image"];
	}
?>
<div class="alert template_message">
	<img src="<?=$image?>" alt="" width="32" height="32" />
	<label>Template</label>
	<p><? if ($template_id == "") { ?>External Link<? } elseif ($template_id == "!") { ?>Redirect Lower<? } else { ?><?=$template["name"]?><? } ?></p>
</div>
<?
	if ($_SESSION["bigtree_admin"]["post_max_hit"]) {
		unset($_SESSION["bigtree_admin"]["post_max_hit"]);
?>
<p class="warning_message">The file(s) uploaded exceeded the web server's maximum upload size. If you uploaded multiple files, try uploading one at a time.</p>
<?
	}
?>
<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>
<?
	$bigtree["datepickers"] = array();
	$bigtree["timepickers"] = array();
	$bigtree["datetimepickers"] = array();
	$bigtree["html_fields"] = array();
	$bigtree["simple_html_fields"] = array();
	$bigtree["tabindex"] = 1;
	if (is_array($template["resources"]) && count($template["resources"])) {
		foreach ($template["resources"] as $resource) {
			$field = array();
			// Leaving some variable settings for backwards compatibility — removing in 5.0
			$field["title"] = $title = $resource["title"];
			$field["subtitle"] = $subtitle = $resource["subtitle"];
			$field["key"] = $key = "resources[".$resource["id"]."]";
			$field["value"] = $value = isset($resources[$resource["id"]]) ? $resources[$resource["id"]] : "";
			$field["current_value_key"] = $currently_key = "resources[__curent-value__".$resource["id"]."]";
			$field["id"] = uniqid("field_",true);
			$field["tabindex"] = $bigtree["tabindex"];
			$field["options"] = $resource;
			$field["options"]["directory"] = "files/pages/"; // File uploads go to /files/pages/

			// Setup Validation Classes
			$label_validation_class = "";
			$field["required"] = false;
			if (isset($resource["validation"]) && $resource["validation"]) {
				if (strpos($resource["validation"],"required") !== false) {
					$label_validation_class = ' class="required"';
					$field["required"] = true;
				}
			}
			$field_type_path = BigTree::path("admin/form-field-types/draw/".$resource["type"].".php");
			
			if (file_exists($field_type_path)) {
?>
<fieldset>
	<?
		if ($field["title"] && $resource["type"] != "checkbox") {
	?>
	<label<?=$label_validation_class?>><?=$field["title"]?><? if ($field["subtitle"]) { ?> <small><?=$field["subtitle"]?></small><? } ?></label>
	<?
		}
		include $field_type_path;
	?>
</fieldset>
<?
				$bigtree["tabindex"]++;
			}
		}
	} else {
		echo '<p>There are no resources for the selected template.</p>';
	}

	$mce_width = 898;
	$mce_height = 365;
	
	if (count($bigtree["html_fields"])) {
		include BigTree::path("admin/layouts/_tinymce_specific.php");
	}
	if (count($bigtree["simple_html_fields"])) {
		include BigTree::path("admin/layouts/_tinymce_specific_simple.php");
	}
	$bigtree["tinymce_fields"] = array_merge($bigtree["html_fields"],$bigtree["simple_html_fields"]);
	
	if ($template["callouts_enabled"]) {
?>
<div class="sub_section" id="bigtree_callouts">
	<label>Callouts</label>
	<ul>
		<?
			$x = 0;
			foreach ($callouts as $callout) {
				$description = "";
				$type = $cms->getCallout($callout["type"]);
				$temp_resources = json_decode($type["resources"],true);
				$callout_resources = array();
				// Loop through the resources and set the key to the id.
				foreach ($temp_resources as $r) {
					$callout_resources[$r["id"]] = $r;
				}
		?>
		<li>
			<input type="hidden" class="callout_data" value="<?=base64_encode(json_encode($callout))?>" />
			<?
				$description = $callout["display_title"];
				foreach ($callout as $r => $v) {
					if ($callout_resources[$r]["type"] == "upload") {
			?>
			<input type="file" name="callouts[<?=$x?>][<?=$r?>]" style="display:none;" class="custom_control" />
			<input type="hidden" name="callouts[<?=$x?>][currently_<?=$r?>]" value="<?=htmlspecialchars(htmlspecialchars_decode($v))?>" />
			<?
					} else {
						if (is_array($v)) {
							$v = json_encode($v,true);
						}
			?>
			<input type="hidden" name="callouts[<?=$x?>][<?=$r?>]" value="<?=htmlspecialchars(htmlspecialchars_decode($v))?>" />
			<?
					}
				}
			?>
			<h4><?=$description?><input type="hidden" name="callouts[<?=$x?>][display_title]" value="<?=$description?>" /></h4>
			<p><?=$type["name"]?></p>
			<div class="bottom">
				<span class="icon_drag"></span>
				<a href="#" class="icon_edit"></a>
				<a href="#" class="icon_delete"></a>
			</div>
		</li>
		<?
				$x++;
			}
		?>
	</ul>
	<a href="#" class="add_callout button"><span class="icon_small icon_small_add"></span>Add Callout</a>
</div>
<script>
	BigTreePages.calloutCount = <?=count($callouts)?>;
</script>
<?
	}
?>
<script>
	<?
		foreach ($bigtree["datepickers"] as $id) {
	?>
	$(document.getElementById("<?=$id?>")).datepicker({ duration: 200, showAnim: "slideDown" });
	<?
		}
		
		foreach ($bigtree["timepickers"] as $id) {
	?>
	$(document.getElementById("<?=$id?>")).timepicker({ duration: 200, showAnim: "slideDown", ampm: true, hourGrid: 6,	minuteGrid: 10 });
	<?
		}
		
		foreach ($bigtree["datetimepickers"] as $id) {
	?>
	$(document.getElementById("<?=$id?>")).datetimepicker({ duration: 200, showAnim: "slideDown", ampm: true, hourGrid: 6, minuteGrid: 10 });
	<?
		}
		
		if (isset($_POST["template"])) {
	?>
	BigTreeCustomControls();
	<?
		}
	?>

	BigTree.TinyMCEFields = <?=json_encode($bigtree["tinymce_fields"])?>;
</script>