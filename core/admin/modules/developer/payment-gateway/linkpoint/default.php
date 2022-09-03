<div class="container">
	<form method="post" action="<?=ADMIN_ROOT?>developer/payment-gateway/linkpoint/update/" class="module" enctype="multipart/form-data">
		<?php $admin->drawCSRFToken() ?>
		<section>
			<div class="alert">
				<p>To enable usage of First Data / LinkPoint as your payment gateway, enter your access information below.</p>
			</div>
			<fieldset>
				<label>Store ID</label>
				<input type="text" name="linkpoint-store" value="<?=htmlspecialchars($gateway->Settings["linkpoint-store"] ?? "")?>" />
			</fieldset>
			<fieldset>
				<label>Certificate <small>(.pem file)</small></label>
				<input type="file" name="linkpoint-certificate" />
				<?php
					if (!empty($gateway->Settings["linkpoint-certificate"])) {
				?>
				<div class="currently_file">
					<strong>Currently:</strong> <?=htmlspecialchars($gateway->Settings["linkpoint-certificate"])?>
				</div>
				<?php
					}
				?>
			</fieldset>
			<fieldset>
				<label>Processing Environment</label>
				<select name="linkpoint-environment">
					<option value="live">Live</option>
					<option value="test"<?php if (!empty($gateway->Settings["linkpoint-environment"]) && $gateway->Settings["linkpoint-environment"] == "test") { ?> selected="selected"<?php } ?>>Test</option>
				</select>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>