<?php
	namespace BigTree;

	/**
	 * @global array $bigtree
	 */

	$view = new ModuleView(end($bigtree["commands"]));
	Globalize::arrayObject($view->Array);

	if (!SQL::tableExists($view->Table)) {
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<h3><?=Text::translate("Error")?></h3>
		</div>
		<p><?=Text::translate("The table for this view (:table:) no longer exists.", false, [":table:" => $view->Table])?></p>
	</section>
	<footer>
		<a href="javascript:history.go(-1);" class="button"><?=Text::translate("Back")?></a>
		<a href="<?=DEVELOPER_ROOT?>modules/interfaces/delete/<?=$view->ID?>/?module=<?=$view->Module?>" class="button red"><?=Text::translate("Delete View")?></a>
	</footer>
</div>
<?php
	} else {
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/views/update/<?=$view->ID?>/" class="module">
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