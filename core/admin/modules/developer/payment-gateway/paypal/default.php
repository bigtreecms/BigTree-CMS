<?
	$module_title = "PayPal";
	include BigTree::path("admin/modules/developer/payment-gateway/_common.php");
	$breadcrumb[] = array("title" => "PayPal Payments Pro", "link" => "#");
?>
<h1><span class="paypal"></span>PayPal Payments Pro</h1>
<div class="form_container">
	<header><h2>PayPal Payments Pro Settings</h2></header>
	<aside>Your current upload service is: <strong><?=$currently?></strong></aside>
	<form method="post" action="update/" class="module">
		<section>
			<div class="alert">
				<p>To enable usage of PayPal Payments Pro as your payment gateway, enter your access information below.</p>
			</div>
			<fieldset>
				<label>API User</label>
				<input type="text" name="paypal-username" value="<?=htmlspecialchars($gateway["settings"]["paypal-username"])?>" />
			</fieldset>
			<fieldset>
				<label>API Password</label>
				<input type="text" name="paypal-password" value="<?=htmlspecialchars($gateway["settings"]["paypal-password"])?>" />
			</fieldset>
			<fieldset>
				<label>API Signature</label>
				<input type="text" name="paypal-signature" value="<?=htmlspecialchars($gateway["settings"]["paypal-signature"])?>" />
			</fieldset>
			<fieldset>
				<label>Processing Environment</label>
				<select name="paypal-environment">
					<option value="live">Live</option>
					<option value="test"<? if ($gateway["settings"]["paypal-environment"] == "test") { ?> selected="selected"<? } ?>>Test</option>
				</select>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>