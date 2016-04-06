<?php
	namespace BigTree;

	if ($_POST["id"]) {
?>
<input type="hidden" name="id" value="<?=htmlspecialchars($_POST["id"])?>" />
<?php
	}
?>
<fieldset>
	<label>Name</label>
	<input type="text" name="name" value="<?=BigTree::safeEncode($_POST["name"])?>" />
</fieldset>
<?php
	$data = $_POST;
	define("BIGTREE_CREATING_PRESET",true);
	Router::includeFile("admin/ajax/developer/field-options/_image-options.php");
?>