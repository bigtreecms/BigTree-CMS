<?php
	namespace BigTree;
	
	/**
	 * @global PaymentGateway\Provider $gateway
	 */
?>
<div class="container">
	<form method="post" action="<?=ADMIN_ROOT?>developer/payment-gateway/authorize/update/" class="module">
		<?php CSRF::drawPOSTToken(); ?>
		<section>
			<div class="alert">
				<p><?=Text::translate("To enable usage of Authorize.Net as your payment gateway, enter your access information below.")?></p>
			</div>
			<fieldset>
				<label for="authorize_field_login"><?=Text::translate("API Login")?></label>
				<input id="authorize_field_login" type="text" name="authorize-api-login" value="<?=htmlspecialchars($gateway->Settings["authorize-api-login"])?>" />
			</fieldset>
			<fieldset>
				<label for="authorize_field_transaction"><?=Text::translate("Transaction Key")?></label>
				<input id="authorize_field_transaction" type="text" name="authorize-transaction-key" value="<?=htmlspecialchars($gateway->Settings["authorize-transaction-key"])?>" />
			</fieldset>
			<fieldset>
				<label for="authorize_field_environment"><?=Text::translate("Processing Environment")?></label>
				<select id="authorize_field_environment" name="authorize-environment">
					<option value="live"><?=Text::translate("Live")?></option>
					<option value="test"<?php if ($gateway->Settings["authorize-environment"] == "test") { ?> selected="selected"<?php } ?>><?=Text::translate("Test")?></option>
				</select>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />
		</footer>
	</form>
</div>