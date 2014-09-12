<fieldset>
	<label>Name</label>
	<input type="text" name="name" value="<?=BigTree::safeEncode($_POST["name"])?>" />
</fieldset>
<?
	$data = $_POST;
	include BigTree::path("admin/ajax/developer/field-options/_image-options.php");
?>