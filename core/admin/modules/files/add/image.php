<?php
	$bigtree["js"][] = "dropzone.js";
	$bigtree["breadcrumb"][] = ["link" => "#", "title" => "Add Images"];
	$permission = $admin->getResourceFolderPermission($bigtree["commands"][0]);

	if ($permission != "p") {
		$admin->stop("You do not have permission to create content in this folder.");
	}
?>
<div class="container file_manager_wrapper">	
	<section>
		<form action="<?=ADMIN_ROOT?>ajax/files/dropzone-upload/?1<?php $admin->drawCSRFTokenGET(); ?>" class="dropzone" id="file_manager_dropzone">
			<p>Drag and drop files into this zone or click to manually upload.</p>
		</form>
	</section>

	<footer>
		<a href="<?=ADMIN_ROOT?>files/process/image/<?=intval($bigtree["commands"][0])?>/" class="blue button">Continue</a>
	</footer>	
</div>

<script>
	Dropzone.options.fileManagerDropzone = {
		acceptedFiles: ".jpg, .jpeg, .gif, .png"
	};
</script>