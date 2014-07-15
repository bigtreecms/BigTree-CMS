<?
	define("BIGTREE_FRONT_END_EDITOR",true);
	$bigtree["layout"] = "front-end";
	// Check for a page lock
	$force = isset($_GET["force"]) ? $_GET["force"] : false;
	$admin->lockCheck("bigtree_pages",$bigtree["current_page"]["id"],"admin/modules/pages/front-end-locked.php",$force);
	
	$bigtree["template"] = $cms->getTemplate($bigtree["current_page"]["template"]);
	$bigtree["resources"] = $bigtree["current_page"]["resources"];
	$bigtree["html_fields"] = array();
	$bigtree["simple_html_fields"] = array();
	$bigtree["timepickers"] = array();
	$bigtree["datepickers"] = array();
	$bigtree["datetimepickers"] = array();
	$bigtree["tabindex"] = 1;

	$cached_types = $admin->getCachedFieldTypes();
	$bigtree["field_types"] = $cached_types["templates"];
?>
<h2>Edit Page Content</h2>
<form class="bigtree_dialog_form" method="post" action="<?=ADMIN_ROOT?>pages/front-end-update/" enctype="multipart/form-data">
	<input type="hidden" name="page" value="<?=$bigtree["current_page"]["id"]?>" />
	<input type="hidden" name="_bigtree_post_check" value="success" />
	<div class="overflow">
		<?
			if ($_SESSION["bigtree_admin"]["post_max_hit"]) {
				unset($_SESSION["bigtree_admin"]["post_max_hit"]);
		?>
		<p class="warning_message">The file(s) uploaded exceeded the web server's maximum upload size. If you uploaded multiple files, try uploading one at a time.</p>
		<?
			}
		?>
		<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>
		<div class="form_fields">
			<?
				if (is_array($bigtree["template"]["resources"]) && count($bigtree["template"]["resources"])) {
					foreach ($bigtree["template"]["resources"] as $resource) {
						$field = array();
						// Leaving some variable settings for backwards compatibility — removing in 5.0
						$field["type"] = $resource["type"];
						$field["title"] = $title = $resource["title"];
						$field["subtitle"] = $subtitle = $resource["subtitle"];
						$field["key"] = $key = "resources[".$resource["id"]."]";
						$field["value"] = $value = isset($bigtree["resources"][$resource["id"]]) ? $bigtree["resources"][$resource["id"]] : "";
						$field["id"] = uniqid("field_");
						$field["tabindex"] = $bigtree["tabindex"];
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
							if ($bigtree["field_types"][$resource["type"]]["self_draw"]) {
								include $field_type_path;
							} else {
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
							$bigtree["last_resource_type"] = $field["type"];
						}
					}
				} else {
					echo '<p>There are no resources for the selected template.</p>';
				}
			?>
		</div>
	</div>
	<footer>
		<a class="button bigtree_dialog_close" href="#">Cancel</a>
		<input type="submit" class="button<? if ($bigtree["access_level"] != "p") { ?> blue<? } ?>" name="ptype" value="Save &amp; Preview" />
		<? if ($bigtree["access_level"] == "p") { ?>
		<input type="submit" class="button blue" name="ptype" value="Save &amp; Publish" />
		<? } ?>
	</footer>
</form>
<?
	$bigtree["html_editor_width"] = 760;
	$bigtree["html_editor_height"] = 365;			
	include BigTree::path("admin/layouts/_html-field-loader.php");
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

	BigTreeCustomControls();
	BigTreeFormValidator(".bigtree_dialog_form");
	
	$(".bigtree_dialog_close").click(function() {
		parent.BigTreeBar.cancel();
		
		return false;
	});
	
	BigTree.localLockTimer = setInterval("$.ajax('<?=ADMIN_ROOT?>ajax/refresh-lock/', { type: 'POST', data: { table: 'bigtree_pages', id: '<?=$bigtree["current_page"]["id"]?>' } });",60000);
</script>