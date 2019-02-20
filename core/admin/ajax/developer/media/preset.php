<?php
	namespace BigTree;

	if ($_POST["id"]) {
?>
<input type="hidden" name="id" value="<?=htmlspecialchars($_POST["id"])?>" />
<?php
	}
?>
<fieldset>
	<label for="preset_field_name"><?=Text::translate("Name")?></label>
	<input id="preset_field_name" type="text" name="name" value="<?=Text::htmlEncode($_POST["name"])?>" />
</fieldset>
<?php
	$settings = $_POST;
	define("BIGTREE_CREATING_PRESET",true);
	include Router::getIncludePath("admin/field-types/_image-options.php");
?>