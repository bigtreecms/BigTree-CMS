<?
	$breadcrumb[] = array("title" => "Add Setting", "link" => "#");
	
	if (is_array($_SESSION["bigtree"]["developer"]["setting_data"])) {
		BigTree::globalizeArray($_SESSION["bigtree"]["developer"]["setting_data"]);
	}
	
	$e = $_SESSION["bigtree"]["developer"]["error"];
	unset($_SESSION["bigtree"]["developer"]["error"]);
	unset($_SESSION["bigtree"]["developer"]["setting_data"]);
?>
<h1><span class="icon_developer_settings"></span>Add Setting</h1>
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
	$htmls = array("setting_description");
	include BigTree::path("admin/layouts/_tinymce.php");
	include BigTree::path("admin/layouts/_tinymce_specific.php");
	
	unset($module);
?>