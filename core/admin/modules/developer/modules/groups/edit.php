<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$group = new ModuleGroup(end($bigtree["path"]));
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/groups/update/<?=$group->ID?>/" class="module">
		<section>
			<fieldset>
			    <label for="group_field_name" class="required"><?=Text::translate("Name")?></label>
			    <input id="group_field_name" type="text" name="name" value="<?=$group->Name?>" class="required" />
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