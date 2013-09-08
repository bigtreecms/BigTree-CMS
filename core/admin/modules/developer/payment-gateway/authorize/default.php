<div class="container">
	<form method="post" action="<?=ADMIN_ROOT?>developer/payment-gateway/authorize/update/" class="module">
		<section>
			<div class="alert">
				<p>To enable usage of Authorize.Net as your payment gateway, enter your access information below.</p>
			</div>
			<fieldset>
				<label>API Login</label>
				<input type="text" name="authorize-api-login" value="<?=htmlspecialchars($gateway->Settings["authorize-api-login"])?>" />
			</fieldset>
			<fieldset>
				<label>Transaction Key</label>
				<input type="text" name="authorize-transaction-key" value="<?=htmlspecialchars($gateway->Settings["authorize-transaction-key"])?>" />
			</fieldset>
			<fieldset>
				<label>Processing Environment</label>
				<select name="authorize-environment">
					<option value="live">Live</option>
					<option value="test"<? if ($gateway->Settings["authorize-environment"] == "test") { ?> selected="selected"<? } ?>>Test</option>
				</select>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>