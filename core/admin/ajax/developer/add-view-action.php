<input class="custom_control" type="checkbox" checked="checked" name="actions[<?=htmlspecialchars($_POST["route"])?>]" value="<?=htmlspecialchars(json_encode($_POST))?>" />
<a class="action active" href="#">
	<span class="<?=htmlspecialchars($_POST["class"])?>"></span>
</a>
<div class="handle"><span class="edit"></span></div>