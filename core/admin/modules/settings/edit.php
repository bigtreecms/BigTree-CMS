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
	<header>
		<h2><?=$item["name"]?></h2>
	</header>
	<? if ($item["encrypted"]) { ?>
	<aside>This setting is encrypted.  The current value cannot be shown.</aside>
	<? } ?>
	<form class="module" action="<?=ADMIN_ROOT?>settings/update/" method="post">	
		<input type="hidden" name="id" value="<?=htmlspecialchars(end($bigtree["path"]))?>" />
		<section>
			<?
				$bigtree["datepickers"] = array();
				$bigtree["timepickers"] = array();
				$bigtree["html_fields"] = array();
				$bigtree["simple_html_fields"] = array();
				
				echo $item["description"];
				
				$t = $item["type"];
				$title = "";
				$value = $item["value"];
				$key = $item["id"];
				$input_validation_class = "";
				
				include BigTree::path("admin/form-field-types/draw/".$t.".php");
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
	
	if (count($bigtree["datepickers"]) || count($bigtree["timepickers"])) {
?>
<script type="text/javascript">
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
	?>
</script>
<?
	}
?>