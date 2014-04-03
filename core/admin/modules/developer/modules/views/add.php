<?
	$id = htmlspecialchars($_GET["module"]);
	$table = isset($_GET["table"]) ? $_GET["table"] : "";
	$title = isset($_GET["title"]) ? htmlspecialchars($_GET["title"]) : "";
	
	$module = $admin->getModule($id);

	if (isset($_SESSION["bigtree_admin"]["developer"]["saved_view"])) {
		BigTree::globalizeArray($_SESSION["bigtree_admin"]["developer"]["saved_view"],array("htmlspecialchars"));
		unset($_SESSION["bigtree_admin"]["developer"]["saved_view"]);
	} else {
		// Stop notices
		$description = $type = $preview_url = "";
		$options = array();
	}
?>
<div class="container">

	<form method="post" action="<?=DEVELOPER_ROOT?>modules/views/create/<?=$id?>/" class="module">
		<?
			if (isset($_GET["new_module"]) || isset($new_module)) {
		?>
		<input type="hidden" name="new_module" value="true" />
		<?
			}
			include BigTree::path("admin/modules/developer/modules/views/_form.php");
		?>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>

<? include BigTree::path("admin/modules/developer/modules/views/_js.php") ?>