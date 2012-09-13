<?
	include BigTree::path("admin/modules/developer/payment-gateway/_common.php");
	$breadcrumb[] = array("title" => "First Data / LinkPoint", "link" => "#");
?>
<h1><span class="linkpoint"></span>First Data / LinkPoint</h1>
<div class="form_container">
	<header><h2>First Data / LinkPoint Settings</h2></header>
	<aside>Your current upload service is: <strong><?=$currently?></strong></aside>
	<form method="post" action="update/" class="module" enctype="multipart/form-data">
		<section>
			<div class="alert">
				<p>To enable usage of First Data / LinkPoint as your payment gateway, enter your access information below.</p>
			</div>
			<fieldset>
				<label>Store ID</label>
				<input type="text" name="linkpoint-store" value="<?=htmlspecialchars($gateway["settings"]["linkpoint-store"])?>" />
			</fieldset>
			<fieldset>
				<label>Certificate <small>(.pem file)</small></label>
				<input type="file" name="linkpoint-certificate" />
				<div class="currently_file">
					<strong>Currently:</strong> <?=htmlspecialchars($gateway["settings"]["linkpoint-certificate"])?>
				</div>
			</fieldset>
			<fieldset>
				<label>Processing Environment</label>
				<select name="linkpoint-environment">
					<option value="live">Live</option>
					<option value="test"<? if ($gateway["settings"]["linkpoint-environment"] == "test") { ?> selected="selected"<? } ?>>Test</option>
				</select>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>