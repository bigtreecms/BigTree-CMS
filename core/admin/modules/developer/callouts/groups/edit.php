<?
	$id = end($bigtree["path"]);
	$group = $admin->getCalloutGroup($id);
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>callouts/groups/update/<?=$id?>/" class="module">
		<section>
			<fieldset>
			    <label class="required">Name</label>
			    <input type="text" name="name" value="<?=$group["name"]?>" class="required" />
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