<?
	if (isset($_POST["template"])) {
		$template = $_POST["template"];
	} else {
		$template = $page["template"];
	}
	
	if (isset($_POST["page"])) {
		$page = $cms->getPendingPage($_POST["page"]);
		$resources = $page["resources"];
		$callouts = $page["callouts"];
	} elseif (!isset($resources) && !isset($callouts)) {
		$resources = array();
		$callouts = array();
	}

	$tdata = $cms->getTemplate($template);

	if (!$tdata["image"]) {
		$image = ADMIN_ROOT."images/templates/page.png";
	} else {
		$image = ADMIN_ROOT."images/templates/".$tdata["image"];
	}
	
	$bigtree["datepickers"] = array();
	$bigtree["timepickers"] = array();
	$bigtree["datetimepickers"] = array();
	$bigtree["html_fields"] = array();
	$bigtree["simple_html_fields"] = array();
?>
<div class="alert template_message">
	<img src="<?=$image?>" alt="" width="32" height="32" />
	<label>Template</label>
	<p><? if ($template == "") { ?>External Link<? } elseif ($template == "!") { ?>Redirect Lower<? } else { ?><?=str_replace("Module - ","",$tdata["name"])?><? } ?></p>
</div>
<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>
<?
	$tabindex = 1;
	if (is_array($tdata["resources"]) && count($tdata["resources"])) {
		foreach ($tdata["resources"] as $options) {
			$key = $options["id"];
			$type = $options["type"];
			$title = $options["title"];
			$subtitle = $options["subtitle"];
			if (isset($resources[$key])) {
				$value = $resources[$key];
			} else {
				$value = "";
			}
			$options["directory"] = "files/pages/";
			$currently_key = "resources[currently_$key]";
			$key = "resources[$key]";
			
			// Setup Validation Classes
			$label_validation_class = "";
			$input_validation_class = "";
			if (isset($options["validation"]) && $options["validation"]) {
				if (strpos($options["validation"],"required") !== false) {
					$label_validation_class = ' class="required"';
				}
				$input_validation_class = ' class="'.$options["validation"].'"';
			}
			
			include BigTree::path("admin/form-field-types/draw/$type.php");
		
			$tabindex++;
		}
	} else {
		echo '<p>There are no resources for your selected template.</p>';
	}

	$mce_width = 898;
	$mce_height = 365;
	
	if (count($bigtree["html_fields"])) {
		include BigTree::path("admin/layouts/_tinymce_specific.php");
	}
	if (count($bigtree["simple_html_fields"])) {
		include BigTree::path("admin/layouts/_tinymce_specific_simple.php");
	}
	
	if ($tdata["callouts_enabled"]) {
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
	var callout_count = <?=count($callouts)?>;
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
</script>