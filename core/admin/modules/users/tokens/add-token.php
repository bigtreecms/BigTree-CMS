<?
	$breadcrumb[] = array("link" => "users/tokens/add-token/","title" => "Add API Token");
	$admin->requireLevel(2);
	$users = $admin->getUsers();
?>
<h1><span class="users"></span>Add API Token</h1>
<? include BigTree::path("admin/modules/users/_nav.php") ?>
<div class="form_container">
	<form class="module" action="<?=$admin_root?>users/tokens/create/" method="post">
		<section>
			<fieldset>
				<label>Associated User</label>
				<select name="user">
					<? foreach ($users as $item) { ?>
					<option value="<?=$item["id"]?>"><?=htmlspecialchars($item["name"])?></option>
					<? } ?>
				</select>
			</fieldset>
			<fieldset>
				<label>Access Level</label>
				<select name="read_only">
					<option value="on">Read Only</option>
					<option value="">Full Access</option>
				</select>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>