<?
	$module_title = "Local Storage";
	$breadcrumb[] = array("title" => "Upload Service", "link" => "developer/cload-storage/");
	$breadcrumb[] = array("title" => "Local Storage", "link" => "#");
	
	$current_service = $cms->getSetting("bigtree-internal-upload-service");
	
	$csl = array(
		"local" => "Local Storage",
		"s3" => "Amazon S3",
		"rackspace" => "Rackspace Cloud Files"
	);
	
	if (!$current_service) {
		$current_service = "local";
	}
?>
<h1><span class="local_storage"></span>Local Storage</h1>
<div class="form_container">
	<header><h2>Local Storage Settings</h2></header>
	<aside>Your current upload service is: <strong><?=$csl[$current_service["service"]]?></strong></aside>
	<form method="post" action="update/" class="module">
		<section>
			<p>To enable usage of Local Storage click the update button below.<br />Files currently located on a different storage provider will continue to function and will not be moved.</p>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>