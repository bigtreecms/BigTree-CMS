<?
	$id = end($bigtree["path"]);
	$group = $admin->getModuleGroup($id);
?>
<div class="container">
	<form method="post" action="<?=$developer_root?>modules/groups/update/<?=$id?>/" class="module">
		<summary>
			<h2>Group Details</h2>
		</summary>
		<section>
			<fieldset>
			    <label class="required">Name</label>
			    <input type="text" name="name" value="<?=htmlspecialchars_decode($group["name"])?>" class="required" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>
<script>
	new BigTreeFormValidator("form.module");
</script>