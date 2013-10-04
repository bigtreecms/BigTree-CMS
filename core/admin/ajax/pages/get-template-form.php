<?
	$template_id = $bigtree["current_page"]["template"];
	if (isset($_POST["page"])) {
		$template_id = $_POST["template"];
		$bigtree["current_page"] = $cms->getPendingPage($_POST["page"]);
		$bigtree["resources"] = $bigtree["current_page"]["resources"];
		$bigtree["callouts"] = $bigtree["current_page"]["callouts"];
	} elseif (isset($_POST["template"])) {
		$template_id = $_POST["template"];
		$bigtree["resources"] = array();
		$bigtree["callouts"] = array();
	} elseif (!isset($bigtree["resources"]) && !isset($bigtree["callouts"])) {
		$bigtree["resources"] = array();
		$bigtree["callouts"] = array();
	}

	$bigtree["template"] = $cms->getTemplate($template_id);

	if (!$bigtree["template"]["image"]) {
		$image = ADMIN_ROOT."images/templates/page.png";
	} else {
		$image = ADMIN_ROOT."images/templates/".$bigtree["template"]["image"];
	}
?>
<div class="alert template_message">
	<img src="<?=$image?>" alt="" width="32" height="32" />
	<label>Template</label>
	<p><? if ($template_id == "") { ?>External Link<? } elseif ($template_id == "!") { ?>Redirect Lower<? } else { ?><?=$bigtree["template"]["name"]?><? } ?></p>
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
	// We alias $bigtree["entry"] to $bigtree["resources"] so that information is in the same place for field types.
	$bigtree["entry"] = &$bigtree["resources"];

	if (is_array($bigtree["template"]["resources"]) && count($bigtree["template"]["resources"])) {
		foreach ($bigtree["template"]["resources"] as $resource) {
			$field = array();
			// Leaving some variable settings for backwards compatibility — removing in 5.0
			$field["title"] = $title = $resource["title"];
			$field["subtitle"] = $subtitle = $resource["subtitle"];
			$field["key"] = $key = "resources[".$resource["id"]."]";
			$field["value"] = $value = isset($bigtree["resources"][$resource["id"]]) ? $bigtree["resources"][$resource["id"]] : "";
			$field["id"] = uniqid("field_");
			$field["tabindex"] = $tabindex = $bigtree["tabindex"];
			$field["options"] = $options = $resource;
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
	
	if ($bigtree["template"]["callouts_enabled"]) {
		// We're going to loop through the callout array so we don't have to do stupid is_array crap anymore.
		function _localDrawCalloutLevel($keys,$level) {
			foreach ($level as $key => $value) {
				if (is_array($value)) {
					_localDrawCalloutLevel(array_merge($keys,array($key)),$value);
				} else {
?>
<input type="hidden" name="callouts[<?=implode("][",$keys)?>][<?=$key?>]" value="<?=htmlspecialchars(htmlspecialchars_decode($value))?>" />
<?
				}
			}
		}
?>
<div class="sub_section" id="bigtree_callouts">
	<label>Callouts</label>
	<div class="contain">
		<?
			$x = 0;
			foreach ($bigtree["callouts"] as $callout) {
				$description = "";
				$type = $admin->getCallout($callout["type"]);
				$callout_resources = array();
				// Loop through the resources and set the key to the id.
				foreach ($type["resources"] as $r) {
					$callout_resources[$r["id"]] = $r;
				}
		?>
		<article>
			<input type="hidden" class="callout_data" value="<?=base64_encode(json_encode($callout))?>" />
			<?
				_localDrawCalloutLevel(array($x),$callout);
			?>
			<h4><?=htmlspecialchars(htmlspecialchars_decode($callout["display_title"]))?><input type="hidden" name="callouts[<?=$x?>][display_title]" value="<?=htmlspecialchars(htmlspecialchars_decode($callout["display_title"]))?>" /></h4>
			<p><?=$type["name"]?></p>
			<div class="bottom">
				<span class="icon_drag"></span>
				<a href="#" class="icon_edit"></a>
				<a href="#" class="icon_delete"></a>
			</div>
		</article>
		<?
				$x++;
			}
		?>
	</div>
	<a href="#" class="add_callout button"><span class="icon_small icon_small_add"></span>Add Callout</a>
</div>
<script>
	BigTreePages.calloutCount = <?=count($bigtree["callouts"])?>;
</script>
<?
	}
?>
<script>
	<?
		foreach ($bigtree["datepickers"] as $id) {
	?>
	$("#<?=$id?>").datepicker({ duration: 200, showAnim: "slideDown" });
	<?
		}
		
		foreach ($bigtree["timepickers"] as $id) {
	?>
	$("#<?=$id?>").timepicker({ duration: 200, showAnim: "slideDown", ampm: true, hourGrid: 6,	minuteGrid: 10 });
	<?
		}
		
		foreach ($bigtree["datetimepickers"] as $id) {
	?>
	$("#<?=$id?>").datetimepicker({ duration: 200, showAnim: "slideDown", ampm: true, hourGrid: 6, minuteGrid: 10 });
	<?
		}
	?>

	BigTree.TinyMCEFields = <?=json_encode($bigtree["tinymce_fields"])?>;
</script>