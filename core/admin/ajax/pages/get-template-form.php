<?
	if (isset($_POST["template"])) {
		$template = $_POST["template"];
	} else {
		$template = $default_template;
	}
	
	if (isset($_POST["page"])) {
		$page = $cms->getPendingPage($_POST["page"]);
		$resources = $page["resources"];
		$callouts = $page["callouts"];
	} elseif (!$resources && !$callouts) {
		$resources = array();
		$callouts = array();
	}

	$tdata = $cms->getTemplate($template);

	if (!$tdata["image"]) {
		$image = $admin_root."images/templates/page.png";
	} else {
		$image = $admin_root."images/templates/".$tdata["image"];
	}
		
	$htmls = array();
	$small_htmls = array();
?>
<div class="alert template_message">
	<img src="<?=$image?>" alt="" />
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
			$value = $resources[$key];
			$options["directory"] = "files/pages/";
			$currently_key = "resources[currently_$key]";
			$key = "resources[$key]";
			
			// Setup Validation Classes
			$label_validation_class = "";
			$input_validation_class = "";
			if ($options["validation"]) {
				if (strpos($options["validation"],"required") !== false) {
					$label_validation_class = ' class="required"';
				}
				$input_validation_class = ' class="'.$options["validation"].'"';
			}
			
			if ($options["wrapper"]) {
				echo $options["wrapper"];
			}
			
			include BigTree::path("admin/form-field-types/draw/$type.php");
			
			if ($options["wrapper"]) {
				$parts = explode(" ",$options["wrapper"]);
				echo "</".substr($parts[0],1).">";
			}
		
			$tabindex++;
		}
	} else {
		echo '<p>There are no resources for your selected template.</p>';
	}

	$mce_width = 898;
	$mce_height = 365;
	
	if (count($htmls)) {
		include BigTree::path("admin/layouts/_tinymce_specific.php");
	}
	if (count($small_htmls)) {
		include BigTree::path("admin/layouts/_tinymce_block_small.php");
	}
	if (count($simplehtmls)) {
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
		?>
		<li>
			<input type="hidden" class="callout_data" value="<?=base64_encode(json_encode($callout))?>" />
			<?
				foreach ($callout as $r => $v) {
					if ($r != "type" && !$description) {
						$description = $v;
					}
			?>
			<input type="hidden" name="callouts[<?=$x?>][<?=$r?>]" value="<?=htmlspecialchars($v)?>" />
			<?
				}
			?>
			<h4><span class="icon_sort"></span><?=$description?></h4>
			<p><?=$type["name"]?></p>
			<div class="bottom">
				<a href="#" class="icon_edit_small"></a>
				<a href="#" class="icon_delete_small"></a>
			</div>
		</li>
		<?
				$x++;
			}
		?>
	</ul>
	<a href="#" class="add_callout button"><span class="icon_small icon_small_add"></span>Add Callout</a>
</div>
<script type="text/javascript">
	var callout_count = <?=count($callouts)?>;
</script>
<?
	}
?>
<script type="text/javascript">
	<? if (is_array($dates)) { foreach ($dates as $id) { ?>
	$(document.getElementById("<?=$id?>")).datepicker({ durration: 200, showAnim: "slideDown" });
	<? } } ?>
	
	<? if (is_array($times)) { foreach ($times as $id) { ?>
	$(document.getElementById("<?=$id?>")).timepicker({ durration: 200, showAnim: "slideDown", ampm: true, hourGrid: 6,	minuteGrid: 10 });
	<? } } ?>
	
	<? if ($_POST["template"]) { ?>
	BigTreeCustomControls();
	<? } ?>
</script>