<?
	// Stop notices
	$id = $name = $type = $locked = $encrypted = $description = "";
	if (isset($_SESSION["bigtree_admin"]["developer"]["setting_data"])) {
		BigTree::globalizeArray($_SESSION["bigtree_admin"]["developer"]["setting_data"]);
		unset($_SESSION["bigtree_admin"]["developer"]["setting_data"]);
	}
	
	if (isset($_SESSION["bigtree_admin"]["developer"]["error"])) {
		$e = $_SESSION["bigtree_admin"]["developer"]["error"];
		unset($_SESSION["bigtree_admin"]["developer"]["error"]);
	} else {
		$e = false;
	}
?>
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