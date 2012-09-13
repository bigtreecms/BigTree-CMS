<?
	include BigTree::path("admin/modules/developer/payment-gateway/_common.php");
	$breadcrumb[] = array("title" => "Authorize.Net", "link" => "#");
?>
<h1><span class="authorize"></span>Authorize.Net</h1>
<div class="form_container">
	<header><h2>Authorize.Net Settings</h2></header>
	<aside>Your current upload service is: <strong><?=$currently?></strong></aside>
	<form method="post" action="update/" class="module">
		<section>
			<div class="alert">
				<p>To enable usage of Authorize.Net as your payment gateway, enter your access information below.</p>
			</div>
			<fieldset>
				<label>API Login</label>
				<input type="text" name="authorize-api-login" value="<?=htmlspecialchars($gateway["settings"]["authorize-api-login"])?>" />
			</fieldset>
			<fieldset>
				<label>Transaction Key</label>
				<input type="text" name="authorize-transaction-key" value="<?=htmlspecialchars($gateway["settings"]["authorize-transaction-key"])?>" />
			</fieldset>
			<fieldset>
				<label>Processing Environment</label>
				<select name="authorize-environment">
					<option value="live">Live</option>
					<option value="test"<? if ($gateway["settings"]["authorize-environment"] == "test") { ?> selected="selected"<? } ?>>Test</option>
				</select>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>