<div class="container">
	<form method="post" action="<?=ADMIN_ROOT?>developer/payment-gateway/paypal/update/" class="module">
		<section>
			<div class="alert">
				<p>To enable usage of PayPal Payments Pro as your payment gateway, enter your access information below.</p>
			</div>
			<fieldset>
				<label>API User</label>
				<input type="text" name="paypal-username" value="<?=htmlspecialchars($gateway->Settings["paypal-username"])?>" />
			</fieldset>
			<fieldset>
				<label>API Password</label>
				<input type="text" name="paypal-password" value="<?=htmlspecialchars($gateway->Settings["paypal-password"])?>" />
			</fieldset>
			<fieldset>
				<label>API Signature</label>
				<input type="text" name="paypal-signature" value="<?=htmlspecialchars($gateway->Settings["paypal-signature"])?>" />
			</fieldset>
			<fieldset>
				<label>Processing Environment</label>
				<select name="paypal-environment">
					<option value="live">Live</option>
					<option value="test"<? if ($gateway->Settings["paypal-environment"] == "test") { ?> selected="selected"<? } ?>>Test</option>
				</select>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>