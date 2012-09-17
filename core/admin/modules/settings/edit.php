<?
	$breadcrumb[] = array("link" => "settings/edit/".end($bigtree["path"])."/", "title" => "Edit Setting");
	
	$item = $admin->getSetting(end($bigtree["path"]));
	if ($item["encrypted"]) {
		$item["value"] = "";
	}
	
	if ($item["system"] || ($item["locked"] && $admin->Level < 2)) {
		die("<p>Unauthorized request.</p>");
	}
?>
<h1><span class="settings"></span>Edit Setting</h1>
<? include BigTree::path("admin/layouts/_tinymce.php"); ?>
<div class="form_container">
	<? if ($item["encrypted"]) { ?>
	<header><p>This setting is encrypted.  The current value cannot be shown.</p></header>
	<? } ?>
	<form class="module" action="<?=ADMIN_ROOT?>settings/update/" method="post">	
		<input type="hidden" name="id" value="<?=htmlspecialchars(end($bigtree["path"]))?>" />
		<section>
			<?
				// Draw the setting description
				echo $item["description"];
				
				// Setup field related nonsense.
				$bigtree["datepickers"] = array();
				$bigtree["timepickers"] = array();
				$bigtree["datetimepickers"] = array();
				$bigtree["html_fields"] = array();
				$bigtree["simple_html_fields"] = array();
				
				$options = json_decode($item["options"],true);
				// Setup Validation Classes
				$label_validation_class = "";
				$input_validation_class = "";
				if (isset($options["validation"]) && $options["validation"]) {
					if (strpos($options["validation"],"required") !== false) {
						$label_validation_class = ' class="required"';
					}
					$input_validation_class = ' class="'.$options["validation"].'"';
				}
				
				$title = $item["name"];
				$value = $item["value"];
				$key = $item["id"];
				
				include BigTree::path("admin/form-field-types/draw/".$item["type"].".php");
			?>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />		
		</footer>
	</form>
</div>
<?
	if (count($bigtree["html_fields"]) || count($bigtree["simple_html_fields"])) {
		$mce_width = 898;
		$mce_height = 365;
		include BigTree::path("admin/layouts/_tinymce.php"); 
				
		if (count($bigtree["html_fields"])) {
			include BigTree::path("admin/layouts/_tinymce_specific.php");
		}
		if (count($bigtree["simple_html_fields"])) {
			include BigTree::path("admin/layouts/_tinymce_specific_simple.php");
		}
	}
	
?>
<script type="text/javascript">
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