<?php
	namespace BigTree;
?>
<div class="container">
	<form method="post" action="<?=ADMIN_ROOT?>developer/payment-gateway/payflow/update/" class="module">
		<section>
			<div class="alert">
				<p><?=Text::translate("To enable usage of PayPal Payflow Gateway as your payment gateway, enter your access information below.")?></p>
			</div>
			<fieldset>
				<label><?=Text::translate("Partner <small>(normally PayPal)</small>")?></label>
				<input type="text" name="payflow-partner" value="<?=htmlspecialchars($gateway->Settings["payflow-partner"])?>" />
			</fieldset>
			<fieldset>
				<label><?=Text::translate("Vendor <small>(if you only have a username, enter your username here as well)</small>")?></label>
				<input type="text" name="payflow-vendor" value="<?=htmlspecialchars($gateway->Settings["payflow-vendor"])?>" />
			</fieldset>
			<fieldset>
				<label><?=Text::translate("Username")?></label>
				<input type="text" name="payflow-username" value="<?=htmlspecialchars($gateway->Settings["payflow-username"])?>" />
			</fieldset>
			<fieldset>
				<label><?=Text::translate("Password")?></label>
				<input type="text" name="payflow-password" value="<?=htmlspecialchars($gateway->Settings["payflow-password"])?>" />
			</fieldset>
			<fieldset>
				<label><?=Text::translate("Processing Environment")?></label>
				<select name="payflow-environment">
					<option value="live"><?=Text::translate("Live")?></option>
					<option value="test"<?php if ($gateway->Settings["payflow-environment"] == "test") { ?> selected="selected"<?php } ?>><?=Text::translate("Test")?></option>
				</select>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />
		</footer>
	</form>
</div>