<?php
	namespace BigTree;

	/**
	 * @global array $bigtree
	 */

	$form = new ModuleForm(end($bigtree["commands"]));
	$module = new Module($form->Module);
	$table = $form->Table;

	if (!SQL::tableExists($form->Table)) {
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<h3><?=Text::translate("Error")?></h3>
		</div>
		<p><?=Text::translate("The table for this form (:table:) no longer exists.", false, array(":table:" => $form->Table))?></p>
	</section>
	<footer>
		<a href="javascript:history.go(-1);" class="button">Back</a>
		<a href="<?=DEVELOPER_ROOT?>modules/interfaces/delete/<?=$form->ID?>/?module=<?=$module->ID?>" class="button red"><?=Text::translate("Delete Form")?></a>
	</footer>
</div>
<?php
	} else {
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/forms/update/<?=$form->ID?>/" class="module">
		<?php
			if ($_GET["return"] == "front") {
		?>
		<input type="hidden" name="return_page" value="<?=htmlspecialchars($_SERVER["HTTP_REFERER"])?>" />
		<?php
			}
			
			include Router::getIncludePath("admin/modules/developer/modules/forms/_form.php");
		?>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />
		</footer>
	</form>
</div>
<?php
	}
?>