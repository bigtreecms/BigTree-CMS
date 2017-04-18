<?php
	namespace BigTree;
	
	/**
	 * @global PaymentGateway\Provider $gateway
	 */
?>
<div class="container">
	<form method="post" action="<?=ADMIN_ROOT?>developer/payment-gateway/payflow/update/" class="module">
		<?php CSRF::drawPOSTToken(); ?>
		<section>
			<div class="alert">
				<p><?=Text::translate("To enable usage of PayPal Payflow Gateway as your payment gateway, enter your access information below.")?></p>
			</div>
			<fieldset>
				<label for="payflow_field_partner"><?=Text::translate("Partner <small>(normally PayPal)</small>")?></label>
				<input id="payflow_field_partner" type="text" name="payflow-partner" value="<?=htmlspecialchars($gateway->Settings["payflow-partner"])?>" />
			</fieldset>
			<fieldset>
				<label for="payflow_field_vendor"><?=Text::translate("Vendor <small>(if you only have a username, enter your username here as well)</small>")?></label>
				<input id="payflow_field_vendor" type="text" name="payflow-vendor" value="<?=htmlspecialchars($gateway->Settings["payflow-vendor"])?>" />
			</fieldset>
			<fieldset>
				<label for="payflow_field_username"><?=Text::translate("Username")?></label>
				<input id="payflow_field_username" type="text" name="payflow-username" value="<?=htmlspecialchars($gateway->Settings["payflow-username"])?>" />
			</fieldset>
			<fieldset>
				<label for="payflow_field_password"><?=Text::translate("Password")?></label>
				<input id="payflow_field_password" type="text" name="payflow-password" value="<?=htmlspecialchars($gateway->Settings["payflow-password"])?>" />
			</fieldset>
			<fieldset>
				<label for="payflow_field_environment"><?=Text::translate("Processing Environment")?></label>
				<select id="payflow_field_environment" name="payflow-environment">
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