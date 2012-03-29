<?
	$breadcrumb[] = array("title" => "Upload Service", "link" => "developer/upload-service/");
	$breadcrumb[] = array("title" => "Rackspace Cloud Files", "link" => "#");
	
	$current_service = $cms->getSetting("bigtree-internal-upload-service");

	$keys = $cms->getSetting("bigtree-internal-rackspace-keys");
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
<h1><span class="icon_developer_upload_rackspace"></span>Rackspace Cloud Files</h1>
<div class="form_container">
	<header><h2>Rackspace Cloud Files Settings</h2></header>
	<aside>Your current upload service is: <strong><?=$csl[$current_service["service"]]?></strong></aside>
	<form method="post" action="update/" class="module">
		<section>
			<div class="alert">
				<p>To enable usage of Rackspace Cloud Files for all BigTree uploads enter your access keys below.<br />Please note that this change is not retroactive -- only future uploads will be stored on Rackspace Cloud Files.</p>
			</div>	
			<fieldset>
				<label>API Key</label>
				<input type="text" name="api_key" value="<?=$api_key?>" />
			</fieldset>
			<fieldset>
				<label>Username</label>
				<input type="text" name="username" value="<?=$username?>" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>