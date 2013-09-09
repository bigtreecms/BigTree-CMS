<div class="container">
	<form method="post" action="<?=ADMIN_ROOT?>developer/payment-gateway/paypal-rest/update/" class="module">
		<section>
			<div class="alert">
				<p>To enable usage of PayPal REST API as your payment gateway, enter your access information below.</p>
			</div>
			<fieldset>
				<label>Client ID</label>
				<input type="text" name="paypal-rest-client-id" value="<?=htmlspecialchars($gateway->Settings["paypal-rest-client-id"])?>" />
			</fieldset>
			<fieldset>
				<label>Client Secret</label>
				<input type="text" name="paypal-rest-client-secret" value="<?=htmlspecialchars($gateway->Settings["paypal-rest-client-secret"])?>" />
			</fieldset>
			<fieldset>
				<label>Processing Environment</label>
				<select name="paypal-rest-environment">
					<option value="live">Live</option>
					<option value="test"<? if ($gateway->Settings["paypal-rest-environment"] == "test") { ?> selected="selected"<? } ?>>Test</option>
				</select>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>