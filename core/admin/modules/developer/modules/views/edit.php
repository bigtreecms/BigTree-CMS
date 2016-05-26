<?php
	namespace BigTree;
	
	$view = \BigTreeAutoModule::getView(end($bigtree["path"]));
	Globalize::arrayObject($view);
	$module = $admin->getModule($module);

	if (!SQL::tableExists($table)) {
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<h3><?=Text::translate("Error")?></h3>
		</div>
		<p><?=Text::translate("The table for this view (:table:) no longer exists.", false, array(":table:" => $table))?></p>
	</section>
	<footer>
		<a href="javascript:history.go(-1);" class="button"><?=Text::translate("Back")?></a>
		<a href="<?=DEVELOPER_ROOT?>modules/interfaces/delete/<?=$view["id"]?>/?module=<?=$module["id"]?>" class="button red"><?=Text::translate("Delete View")?></a>
	</footer>
</div>
<?php
	} else {
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/views/update/<?=$view["id"]?>/" class="module">
		<?php
			if ($_GET["return"] == "front") {
		?>
		<input type="hidden" name="return_page" value="<?=htmlspecialchars($_SERVER["HTTP_REFERER"])?>" />
		<?php
			}
			include Router::getIncludePath("admin/modules/developer/modules/views/_form.php");
		?>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />
		</footer>
	</form>
</div>
<?php
		include Router::getIncludePath("admin/modules/developer/modules/views/_js.php");
	}
?>