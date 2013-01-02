<?
	$bigtree["layout"] = "front-end";
	// Check for a page lock
	$force = isset($_GET["force"]) ? $_GET["force"] : false;
	$lock_id = $admin->lockCheck("bigtree_pages",$page["id"],"admin/modules/pages/front-end-locked.php",$force);
	
	// Grab template information
	$template_data = $cms->getTemplate($page["template"]);
	
	$resources = $page["resources"];
	
	$bigtree["html_fields"] = array();
	$bigtree["simple_html_fields"] = array();
	$bigtree["timepickers"] = array();
	$bigtree["datepickers"] = array();
	$bigtree["datetimepickers"] = array();
	$tabindex = 1;
?>
<h2>Edit Page Content</h2>
<form class="bigtree_dialog_form" method="post" action="<?=ADMIN_ROOT?>pages/front-end-update/" enctype="multipart/form-data">
	<input type="hidden" name="page" value="<?=$page["id"]?>" />
	<div class="overflow">
		<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>
		<?
			foreach ($template_data["resources"] as $options) {
				$no_file_browser = true;
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
				
				include BigTree::path("admin/form-field-types/draw/$type.php");
		
				$tabindex++;
			}
		
			$mce_width = 760;
			$mce_height = 365;
			
			//$no_inline = true;
			if (count($bigtree["html_fields"]) || count($bigtree["simple_html_fields"])) {
				$bigtree["js"][] = "tiny_mce/tiny_mce.js";
				if (count($bigtree["html_fields"])) {
					include BigTree::path("admin/layouts/_tinymce_specific.php");
				}
				if (count($bigtree["simple_html_fields"])) {
					include BigTree::path("admin/layouts/_tinymce_specific_simple.php");
				}
			}
			
			if (!count($template_data["resources"])) {
		?>
		<p>This page has no editable content.</p>
		<?
			}
		?>
	</div>
	<footer>
		<a class="button bigtree_dialog_close" href="#">Cancel</a>
		<input type="submit" class="button<? if ($access_level != "p") { ?> blue<? } ?>" name="ptype" value="Save &amp; Preview" />
		<? if ($access_level == "p") { ?>
		<input type="submit" class="button blue" name="ptype" value="Save &amp; Publish" />
		<? } ?>
	</footer>
</form>
		
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
	?>

	BigTreeCustomControls();
	BigTreeFormValidator(".bigtree_dialog_form");
	
	$(".bigtree_dialog_close").click(function() {
		parent.BigTreeBar.cancel();
		
		return false;
	});
	
	var page = "<?=$page["id"]?>";
	lockTimer = setInterval("$.ajax('<?=ADMIN_ROOT?>ajax/pages/refresh-lock/', { type: 'POST', data: { id: '<?=$lock_id?>' } });",60000);
</script>