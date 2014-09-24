<? if ($_POST["id"]) { ?>
<input type="hidden" name="id" value="<?=htmlspecialchars($_POST["id"])?>" />
<? } ?>
<fieldset>
	<label>Name</label>
	<input type="text" name="name" value="<?=BigTree::safeEncode($_POST["name"])?>" />
</fieldset>
<?
	$data = $_POST;
	define("BIGTREE_CREATING_PRESET",true);
	include BigTree::path("admin/ajax/developer/field-options/_image-options.php");
?>