<?php
	$bigtree["breadcrumb"][] = ["link" => "#", "title" => "Create New Folder"];
	$permission = $admin->getResourceFolderPermission($bigtree["commands"][0]);

	if ($permission != "p") {
		$admin->stop("You do not have permission to create content in this folder.");
	}
?>
<form method="post" action="<?=ADMIN_ROOT?>files/process/folder/" class="container">
	<?php $admin->drawCSRFToken(); ?>
	<input type="hidden" name="folder" value="<?=intval($bigtree["commands"][0])?>">
	
	<section>
		<fieldset>
			<label for="field_folder_name">Folder Name</label>
			<input type="text" name="name" id="field_folder_name">
		</fieldset>
	</section>

	<footer>
		<input type="submit" class="button blue" value="Create Folder">
	</footer>	
</form>