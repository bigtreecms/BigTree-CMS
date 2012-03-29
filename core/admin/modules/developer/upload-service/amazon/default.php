<?
	$breadcrumb[] = array("title" => "Upload Service", "link" => "developer/upload-service/");
	$breadcrumb[] = array("title" => "Amazon S3", "link" => "#");
	
	$current_service = $cms->getSetting("bigtree-internal-upload-service");
	$keys = $cms->getSetting("bigtree-internal-s3-keys");
	if (is_array($keys)) {
		foreach ($keys as $k => $v) {
			$$k = htmlspecialchars($v);
		}
	}
	
	$csl = array(
		"local" => "Local Storage",
		"s3" => "Amazon S3",
		"rackspace" => "Rackspace Cloud Files"
	);
	
	if (!$current_service) {
		$current_service = "local";
	}
?>
<h1><span class="icon_developer_upload_amazon"></span>Amazon S3</h1>
<div class="form_container">
	<header><h2>Amazon S3 Settings</h2></header>
	<aside>Your current upload service is: <strong><?=$csl[$current_service["service"]]?></strong></aside>
	<form method="post" action="update/" class="module">
		<section>
			<div class="alert">
				<p>To enable usage of Amazon S3 for all BigTree uploads enter your access keys below.<br />Please note that this change is not retroactive -- only future uploads will be stored on Amazon S3.</p>
			</div>	
			<fieldset>
				<label>Access Key ID</label>
				<input type="text" name="access_key_id" value="<?=$access_key_id?>" />
			</fieldset>
			<fieldset>
				<label>Secret Access Key</label>
				<input type="text" name="secret_access_key" value="<?=$secret_access_key?>" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>