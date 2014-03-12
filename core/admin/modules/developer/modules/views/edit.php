<?	
	$view = BigTreeAutoModule::getView(end($bigtree["path"]));
	BigTree::globalizeArray($view);
	$module = $admin->getModule($module);

	if (!BigTree::tableExists($table)) {
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<h3>Error</h3>
		</div>
		<p>The table for this view (<?=$table?>) no longer exists.</p>
	</section>
	<footer>
		<a href="javascript:history.go(-1);" class="button">Back</a>
		<a href="<?=DEVELOPER_ROOT?>modules/views/delete/<?=$view["id"]?>/?module=<?=$module["id"]?>" class="button red">Delete View</a>
	</footer>
</div>
<?
	} else {
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/views/update/<?=end($bigtree["path"])?>/" class="module">
		<?
			if ($_GET["return"] == "front") {
		?>
		<input type="hidden" name="return_page" value="<?=htmlspecialchars($_SERVER["HTTP_REFERER"])?>" />
		<?
			}
			include BigTree::path("admin/modules/developer/modules/views/_form.php");
		?>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>
<?
		include BigTree::path("admin/modules/developer/modules/views/_js.php");
	}
?>