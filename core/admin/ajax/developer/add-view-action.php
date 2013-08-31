<input class="custom_control" type="checkbox" checked="checked" name="actions[<?=$_POST["route"]?>]" value="<?=htmlspecialchars(json_encode($_POST))?>" />
<a class="action active" href="#">
	<span class="<?=$_POST["class"]?>"></span>
</a>
<div class="handle"><span class="edit"></span></div>