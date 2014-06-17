<?
	$admin->requireLevel(1);
	$item = $admin->getSetting(end($bigtree["path"]));
	$value = $cms->getSetting(end($bigtree["path"]));
	if ($item["encrypted"]) {
		$value = "";
	}

	if (!$item || $item["system"] || ($item["locked"] && $admin->Level < 2)) {
?>
<div class="container">
	<section>
		<h3>Error</h3>
		<p>The setting you are trying to edit no longer exists or you do not have permission to edit it.</p>
	</section>
</div>
<?
		$admin->stop();
	}

	$cached_types = $admin->getCachedFieldTypes();
	$bigtree["field_types"] = $cached_types["settings"];
?>
<div class="container">
	<summary><h2><?=$item["name"]?></h2></summary>
	<form class="module" action="<?=ADMIN_ROOT?>settings/update/" method="post" enctype="multipart/form-data">
		<input type="hidden" name="MAX_FILE_SIZE" value="<?=BigTree::uploadMaxFileSize()?>" />
		<input type="hidden" name="_bigtree_post_check" value="success" />
		<input type="hidden" name="id" value="<?=htmlspecialchars(end($bigtree["path"]))?>" />
		<section>
			<?
				if ($item["encrypted"]) {
			?>
			<div class="alert">
				<span></span>
				<p>This setting is encrypted. The current value cannot be shown.</p>
			</div>
			<?
				}
		
				if ($_SESSION["bigtree_admin"]["post_max_hit"]) {
					unset($_SESSION["bigtree_admin"]["post_max_hit"]);
			?>
			<p class="warning_message">The file(s) uploaded exceeded the web server's maximum upload size. If you uploaded multiple files, try uploading one at a time.</p>
			<?
				}

				echo $item["description"];
			?>
			<div class="form_fields">
				<?			
					// Setup field related nonsense.
					$bigtree["datepickers"] = array();
					$bigtree["timepickers"] = array();
					$bigtree["datetimepickers"] = array();
					$bigtree["html_fields"] = array();
					$bigtree["simple_html_fields"] = array();
					
					$options = json_decode($item["options"],true);
	
					$field = array();
					// Leaving some variable settings for backwards compatibility — removing in 5.0
					$field["title"] = $title = "";
					$field["value"] = $value;
					$field["key"] = $key = $item["id"];
					$field["options"] = $options;
					$field["required"] = $required;
					$field["id"] = uniqid("field_");
					$field["tabindex"] = "1";
	
					// Setup Validation Classes
					$label_validation_class = "";
					$field["required"] = false;
					if (isset($options["validation"]) && $options["validation"]) {
						if (strpos($options["validation"],"required") !== false) {
							$label_validation_class = ' class="required"';
							$field["required"] = true;
						}
					}
	
					// Draw the field type
					$field_type_path = BigTree::path("admin/form-field-types/draw/".$item["type"].".php");
					if (file_exists($field_type_path)) {
						if ($bigtree["field_types"][$item["type"]]["self_draw"]) {
							include $field_type_path;
						} else {
				?>
				<fieldset>
					<?
							if ($field["title"] && $item["type"] != "checkbox") {
					?>
					<label<?=$label_validation_class?>><?=$field["title"]?><? if ($field["subtitle"]) { ?> <small><?=$field["subtitle"]?></small><? } ?></label>
					<?
							}
							include $field_type_path;
							$bigtree["tabindex"]++;
					?>
				</fieldset>
				<?
						}
					}	
				?>
			</div>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />		
		</footer>
	</form>
</div>
<?
	$bigtree["html_editor_width"] = 898;
	$bigtree["html_editor_height"] = 365;
	include BigTree::path("admin/layouts/_html-field-loader.php");
?>
<script>
	new BigTreeFormValidator("form.module");
	
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
</script>