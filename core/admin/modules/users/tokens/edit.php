<?
	$breadcrumb[] = array("link" => "users/tokens/edit/","title" => "Edit API Token");
	$admin->requireLevel(2);
	$item = $admin->getAPITokenById(end($path));
	$users = $admin->getUsers();
	include BigTree::path("admin/modules/users/_nav.php");
?>
<h1><span class="users"></span>Edit API Token</h1>
<div class="form_container">
	<form class="module" action="<?=$admin_root?>users/tokens/update/<?=end($path)?>/" method="post">
		<section>
			<fieldset>
				<label>Token</label>
				<input type="text" name="token" value="<?=htmlspecialchars($item["token"])?>" disabled="disabled" />
			</fieldset>
			<fieldset>
				<label>Associated User</label>
				<select name="user">
					<? foreach ($users as $u) { ?>
					<option value="<?=$u["id"]?>"<? if ($item["user"] == $u["id"]) { ?> selected="selected"<? } ?>><?=htmlspecialchars($u["name"])?></option>
					<? } ?>
				</select>
			</fieldset>
			<fieldset>
				<label>Access Level</label>
				<select name="read_only">
					<option value="on">Read Only</option>
					<option value=""<? if (!$item["read_only"]) { ?> selected="selected"<? } ?>>Full Access</option>
				</select>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />	
		</footer>
	</form>
</div>