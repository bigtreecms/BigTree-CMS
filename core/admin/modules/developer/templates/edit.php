<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$template = new Template(Router::$Command, ["BigTree\Admin", "catch404"]);
	$form_action = "edit";
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>templates/update/" enctype="multipart/form-data" class="module">
		<input type="hidden" name="id" value="<?=$template->ID?>" />
		<?php include Router::getIncludePath("admin/modules/developer/templates/_form-content.php") ?>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />
		</footer>
	</form>
</div>

<?php include Router::getIncludePath("admin/modules/developer/templates/_common-js.php") ?>