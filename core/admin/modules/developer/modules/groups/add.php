<?php
	namespace BigTree;
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/groups/create/" class="module">
		<section>
			<fieldset>
				<label class="required"><?=Text::translate("Name")?></label>
				<input type="text" name="name" value="" class="required" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Create", true)?>" />
		</footer>
	</form>
</div>
<script>
	BigTreeFormValidator("form.module");
</script>