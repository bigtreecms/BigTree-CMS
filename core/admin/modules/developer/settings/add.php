<?
	$breadcrumb[] = array("title" => "Add Setting", "link" => "#");
	
	// Stop notices
	$id = $name = $type = $locked = $encrypted = $description = "";
	if (isset($_SESSION["bigtree"]["developer"]["setting_data"])) {
		BigTree::globalizeArray($_SESSION["bigtree"]["developer"]["setting_data"]);
		unset($_SESSION["bigtree"]["developer"]["setting_data"]);
	}
	
	if (isset($_SESSION["bigtree"]["developer"]["error"])) {
		$e = $_SESSION["bigtree"]["developer"]["error"];
		unset($_SESSION["bigtree"]["developer"]["error"]);
	} else {
		$e = false;
	}
?>
<h1><span class="settings"></span>Add Setting</h1>
<? include BigTree::path("admin/modules/developer/settings/_nav.php") ?>

<div class="form_container">
	<form class="module" method="post" action="<?=$section_root?>create/">
		<? include BigTree::path("admin/modules/developer/settings/_form-content.php") ?>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>
<script type="text/javascript">
	new BigTreeFormValidator("form.module");
</script>
<?
	$bigtree["html_fields"] = array("setting_description");
	include BigTree::path("admin/layouts/_tinymce.php");
	include BigTree::path("admin/layouts/_tinymce_specific.php");
	
	unset($module);
?>