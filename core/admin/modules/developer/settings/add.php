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
<div class="container">
	<form class="module" method="post" action="<?=DEVELOPER_ROOT?>settings/create/">
		<? include BigTree::path("admin/modules/developer/settings/_form-content.php") ?>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>
<script>
	new BigTreeFormValidator("form.module");
</script>
<?
	$bigtree["html_fields"] = array("setting_description");
	include BigTree::path("admin/layouts/_html-field-loader.php");
	
	unset($module);
?>