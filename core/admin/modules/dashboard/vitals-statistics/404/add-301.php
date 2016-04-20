<?php
	namespace BigTree;
?>
<form method="post" action="<?=ADMIN_ROOT?>dashboard/vitals-statistics/404/create-301/" id="create_301_form">
	<div class="container">
		<section>
			<fieldset>
				<label><?=Text::translate("From")?> <small>(<?=Text::translate("can be a full URL or just the piece after your domain")?>)</small></label>
				<input type="text" name="from" class="required" />
			</fieldset>
			<fieldset>
				<label><?=Text::translate("To")?> <small>(<?=Text::translate("a full URL — include http://")?>)</small></label>
				<input type="text" name="to" class="required" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="blue" value="<?=Text::translate("Create")?>" />
		</footer>
	</div>
</form>

<script>
	BigTreeFormValidator("#create_301_form",false);
</script>