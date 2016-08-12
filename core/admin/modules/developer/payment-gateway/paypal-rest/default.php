<?php
	namespace BigTree;
	
	/**
	 * @global PaymentGateway\Provider $gateway
	 */
?>
<div class="container">
	<form method="post" action="<?=ADMIN_ROOT?>developer/payment-gateway/paypal-rest/update/" class="module">
		<section>
			<div class="alert">
				<p><?=Text::translate("To enable usage of PayPal REST API as your payment gateway, enter your access information below.")?></p>
			</div>
			<fieldset>
				<label for="paypal_field_client"><?=Text::translate("Client ID")?></label>
				<input id="paypal_field_client" type="text" name="paypal-rest-client-id" value="<?=htmlspecialchars($gateway->Settings["paypal-rest-client-id"])?>" />
			</fieldset>
			<fieldset>
				<label for="paypal_field_secret"><?=Text::translate("Client Secret")?></label>
				<input id="paypal_field_secret" type="text" name="paypal-rest-client-secret" value="<?=htmlspecialchars($gateway->Settings["paypal-rest-client-secret"])?>" />
			</fieldset>
			<fieldset>
				<label for="paypal_field_environment"><?=Text::translate("Processing Environment")?></label>
				<select id="paypal_field_environment" name="paypal-rest-environment">
					<option value="live"><?=Text::translate("Live")?></option>
					<option value="test"<?php if ($gateway->Settings["paypal-rest-environment"] == "test") { ?> selected="selected"<?php } ?>><?=Text::translate("Test")?></option>
				</select>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />
		</footer>
	</form>
</div>