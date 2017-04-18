<?php
	namespace BigTree;
	
	/**
	 * @global PaymentGateway\Provider $gateway
	 */
?>
<div class="container">
	<form method="post" action="<?=ADMIN_ROOT?>developer/payment-gateway/paypal/update/" class="module">
		<?php CSRF::drawPOSTToken(); ?>
		<section>
			<div class="alert">
				<p><?=Text::translate("To enable usage of PayPal Payments Pro as your payment gateway, enter your access information below.")?></p>
			</div>
			<fieldset>
				<label for="paypal_field_username"><?=Text::translate("API User")?></label>
				<input id="paypal_field_username" type="text" name="paypal-username" value="<?=htmlspecialchars($gateway->Settings["paypal-username"])?>" />
			</fieldset>
			<fieldset>
				<label for="paypal_field_password"><?=Text::translate("API Password")?></label>
				<input id="paypal_field_password" type="text" name="paypal-password" value="<?=htmlspecialchars($gateway->Settings["paypal-password"])?>" />
			</fieldset>
			<fieldset>
				<label for="paypal_field_signature"><?=Text::translate("API Signature")?></label>
				<input id="paypal_field_signature" type="text" name="paypal-signature" value="<?=htmlspecialchars($gateway->Settings["paypal-signature"])?>" />
			</fieldset>
			<fieldset>
				<label for="paypal_field_environment"><?=Text::translate("Processing Environment")?></label>
				<select id="paypal_field_environment" name="paypal-environment">
					<option value="live"><?=Text::translate("Live")?></option>
					<option value="test"<?php if ($gateway->Settings["paypal-environment"] == "test") { ?> selected="selected"<?php } ?>><?=Text::translate("Test")?></option>
				</select>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />
		</footer>
	</form>
</div>