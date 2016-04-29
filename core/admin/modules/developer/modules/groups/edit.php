<?php
	$id = end($bigtree["path"]);
	$group = $admin->getModuleGroup($id);
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/groups/update/<?=$id?>/" class="module">
		<section>
			<fieldset>
			    <label class="required">Name</label>
			    <input type="text" name="name" value="<?=$group["name"]?>" class="required" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />
		</footer>
	</form>
</div>
<script>
	BigTreeFormValidator("form.module");
</script>