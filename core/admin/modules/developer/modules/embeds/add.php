<?php
	namespace BigTree;
	
	$form = new ModuleEmbedForm;
	$module = new Module($_GET["module"]);
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/embeds/create/<?=$module->ID?>/" class="module">
		<?php include Router::getIncludePath("admin/modules/developer/modules/embeds/_form.php") ?>
		<section class="sub" id="field_area">
			<p><?=Text::translate("Please choose a table to populate this area.")?></p>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Create", true)?>" />
		</footer>
	</form>
</div>

<?php include Router::getIncludePath("admin/modules/developer/modules/forms/_footer.php") ?>