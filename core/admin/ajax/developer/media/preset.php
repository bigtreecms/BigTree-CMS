<?php if (!empty($_POST["id"])) { ?>
<input type="hidden" name="id" value="<?=htmlspecialchars($_POST["id"])?>" />
<?php } ?>
<fieldset>
	<label>Name</label>
	<input type="text" name="name" value="<?=BigTree::safeEncode($_POST["name"] ?? "")?>" required />
</fieldset>
<?php
	$settings = $_POST;
	$image_options_prefix = null;
	define("BIGTREE_CREATING_PRESET",true);
	include BigTree::path("admin/field-types/_image-options.php");
?>