<div class="container">
	<form method="post" action="<?=ADMIN_ROOT?>developer/payment-gateway/payflow/update/" class="module">
		<section>
			<div class="alert">
				<p>To enable usage of PayPal Payflow Gateway as your payment gateway, enter your access information below.</p>
			</div>
			<fieldset>
				<label>Partner <small>(normally PayPal)</small></label>
				<input type="text" name="payflow-partner" value="<?=htmlspecialchars($gateway->Settings["payflow-partner"])?>" />
			</fieldset>
			<fieldset>
				<label>Vendor <small>(if you only have a username, enter your username here as well)</small></label>
				<input type="text" name="payflow-vendor" value="<?=htmlspecialchars($gateway->Settings["payflow-vendor"])?>" />
			</fieldset>
			<fieldset>
				<label>Username</label>
				<input type="text" name="payflow-username" value="<?=htmlspecialchars($gateway->Settings["payflow-username"])?>" />
			</fieldset>
			<fieldset>
				<label>Password</label>
				<input type="text" name="payflow-password" value="<?=htmlspecialchars($gateway->Settings["payflow-password"])?>" />
			</fieldset>
			<fieldset>
				<label>Processing Environment</label>
				<select name="payflow-environment">
					<option value="live">Live</option>
					<option value="test"<? if ($gateway->Settings["payflow-environment"] == "test") { ?> selected="selected"<? } ?>>Test</option>
				</select>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>