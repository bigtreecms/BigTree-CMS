<?
	$breadcrumb[] = array("title" => "Upload Service", "link" => "developer/upload-service/");
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
<h1><span class="icon_developer_upload_service"></span>Local Storage</h1>
<div class="form_container">
	<header><h2>Local Storage Settings</h2></header>
	<aside>Your current upload service is: <strong><?=$csl[$current_service["service"]]?></strong></aside>
	<form method="post" action="update/" class="module">
		<section>
			<p>To enable usage of Local Storage click the update button below.  Items currently located on a different storage provider will continue to function and will not be moved.</p>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>