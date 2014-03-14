<?
	$form = BigTreeAutoModule::getForm(end($bigtree["commands"]));;
	$module = $admin->getModule(BigTreeAutoModule::getModuleForForm($form));

	$table = $form["table"];
	$fields = $form["fields"];
	if (!is_array($form["hooks"])) {
		$form["hooks"] = array("pre" => "","post" => "","publish" => "");
	}

	if (!BigTree::tableExists($table)) {
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<h3>Error</h3>
		</div>
		<p>The table for this form (<?=$table?>) no longer exists.</p>
	</section>
	<footer>
		<a href="javascript:history.go(-1);" class="button">Back</a>
		<a href="<?=DEVELOPER_ROOT?>modules/forms/delete/<?=$form["id"]?>/?module=<?=$module["id"]?>" class="button red">Delete Form</a>
	</footer>
</div>
<?
	} else {
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/forms/update/<?=$form["id"]?>/" class="module">
		<?
			if ($_GET["return"] == "front") {
		?>
		<input type="hidden" name="return_page" value="<?=htmlspecialchars($_SERVER["HTTP_REFERER"])?>" />
		<?
			}
			include BigTree::path("admin/modules/developer/modules/forms/_form.php");
		?>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>
<?
	}
?>