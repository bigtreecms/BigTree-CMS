<?php
	$bigtree["breadcrumb"][] = ["link" => "#", "title" => "Add Video"];
	$permission = $admin->getResourceFolderPermission($bigtree["commands"][0]);

	if ($permission != "p") {
		$admin->stop("You do not have permission to create content in this folder.");
	}
?>
<form action="<?=ADMIN_ROOT?>files/process/video/" method="post" class="container">
	<?php $admin->drawCSRFToken(); ?>
	<input type="hidden" name="folder" value="<?=intval($bigtree["commands"][0])?>">

	<section>
		<fieldset>
			<label for="file_manager_field_video">Video URL <small>Supported Services: YouTube, Vimeo</small></label>
			<input type="url" name="video" id="file_manager_field_video">
		</fieldset>
	</section>

	<footer>
		<input type="submit" class="button blue" value="Continue">
	</footer>
</form>