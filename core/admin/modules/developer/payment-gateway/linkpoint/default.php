<?php
	namespace BigTree;
?>
<div class="container">
	<form method="post" action="<?=ADMIN_ROOT?>developer/payment-gateway/linkpoint/update/" class="module" enctype="multipart/form-data">
		<section>
			<div class="alert">
				<p><?=Text::translate("To enable usage of First Data / LinkPoint as your payment gateway, enter your access information below.")?></p>
			</div>
			<fieldset>
				<label><?=Text::translate("Store ID")?></label>
				<input type="text" name="linkpoint-store" value="<?=htmlspecialchars($gateway->Settings["linkpoint-store"])?>" />
			</fieldset>
			<fieldset>
				<label><?=Text::translate("Certificate <small>(.pem file)</small>")?></label>
				<input type="file" name="linkpoint-certificate" />
				<?php if (!empty($gateway->Settings["linkpoint-certificate"])) { ?>
				<div class="currently_file">
					<strong><?=Text::translate("Currently:")?></strong> <?=htmlspecialchars($gateway->Settings["linkpoint-certificate"])?>
				</div>
				<?php } ?>
			</fieldset>
			<fieldset>
				<label><?=Text::translate("Processing Environment")?></label>
				<select name="linkpoint-environment">
					<option value="live"><?=Text::translate("Live")?></option>
					<option value="test"<?php if ($gateway->Settings["linkpoint-environment"] == "test") { ?> selected="selected"<?php } ?>><?=Text::translate("Test")?></option>
				</select>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />
		</footer>
	</form>
</div>