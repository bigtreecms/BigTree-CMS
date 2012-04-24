<?
	include BigTree::path("admin/modules/developer/payment-gateway/_common.php");
	$breadcrumb[] = array("title" => "PayPal Payflow Gateway", "link" => "#");
?>
<h1><span class="icon_developer_payment_payflow"></span>PayPal Payflow Gateway</h1>
<div class="form_container">
	<header><h2>PayPal Payflow Gateway Settings</h2></header>
	<aside>Your current upload service is: <strong><?=$currently?></strong></aside>
	<form method="post" action="update/" class="module">
		<section>
			<div class="alert">
				<p>To enable usage of PayPal Payflow Gateway as your payment gateway, enter your access information below.</p>
			</div>
			<fieldset>
				<label>Partner <small>(normally PayPal)</small></label>
				<input type="text" name="payflow-partner" value="<?=htmlspecialchars($gateway["settings"]["payflow-partner"])?>" />
			</fieldset>
			<fieldset>
				<label>Vendor <small>(if you only have a username, enter your username here as well)</small></label>
				<input type="text" name="payflow-vendor" value="<?=htmlspecialchars($gateway["settings"]["payflow-vendor"])?>" />
			</fieldset>
			<fieldset>
				<label>Username</label>
				<input type="text" name="payflow-username" value="<?=htmlspecialchars($gateway["settings"]["payflow-username"])?>" />
			</fieldset>
			<fieldset>
				<label>Password</label>
				<input type="text" name="payflow-password" value="<?=htmlspecialchars($gateway["settings"]["payflow-password"])?>" />
			</fieldset>
			<fieldset>
				<label>Processing Environment</label>
				<select name="payflow-environment">
					<option value="live">Live</option>
					<option value="test"<? if ($gateway["settings"]["payflow-environment"] == "test") { ?> selected="selected"<? } ?>>Test</option>
				</select>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>