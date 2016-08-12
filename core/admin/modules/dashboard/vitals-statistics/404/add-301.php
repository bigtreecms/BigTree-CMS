<?php
	namespace BigTree;
?>
<form method="post" action="<?=ADMIN_ROOT?>dashboard/vitals-statistics/404/create-301/" id="create_301_form">
	<div class="container">
		<section>
			<fieldset>
				<label for="redirect_field_from"><?=Text::translate("From")?> <small>(<?=Text::translate("can be a full URL or just the piece after your domain")?>)</small></label>
				<input id="redirect_field_from" type="text" name="from" class="required" />
			</fieldset>
			<fieldset>
				<label for="redirect_field_to"><?=Text::translate("To")?> <small>(<?=Text::translate("a full URL — include http://")?>)</small></label>
				<input id="redirect_field_to" type="text" name="to" class="required" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="blue" value="<?=Text::translate("Create", true)?>" />
		</footer>
	</div>
</form>

<script>
	BigTreeFormValidator("#create_301_form",false);
</script>