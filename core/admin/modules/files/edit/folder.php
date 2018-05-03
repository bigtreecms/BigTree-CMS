<?php
	// Create custom breadcrumb
	$bigtree["breadcrumb"] = [
		["link" => "files", "title" => "Files"]
	];

	$breadcrumb = $admin->getResourceFolderBreadcrumb($bigtree["commands"][0]);

	foreach ($breadcrumb as $piece) {
		$bigtree["breadcrumb"][] = ["link" => "files/folder/".$piece["id"], "title" => $piece["name"]];
	}

	$permission = $admin->getResourceFolderPermission($bigtree["commands"][0]);

	if ($permission != "p") {
		$admin->stop("You do not have permission to edit this folder.");
	}

	$folder = $admin->getResourceFolder($bigtree["commands"][0]);
?>
<form method="post" action="<?=ADMIN_ROOT?>files/update/folder/" class="container">
	<?php $admin->drawCSRFToken(); ?>
	<input type="hidden" name="folder" value="<?=intval($bigtree["commands"][0])?>">
	
	<section>
		<?php
			if ($admin->Level) {
		?>
		<fieldset>
			<label for="field_folder_parent">Parent Folder</label>
			<select id="field_folder_parent" name="parent">
				<option value="0"<?php if (!$folder["parent"]) { ?> selected<?php } ?>>&mdash;</option>
				<?php $recurse_folders($folder["parent"]); ?>
			</select>
		</fieldset>
		<?php
			}
		?>
		<fieldset>
			<label for="field_folder_name">Folder Name</label>
			<input type="text" name="name" id="field_folder_name" value="<?=$folder["name"]?>">
		</fieldset>
	</section>

	<footer>
		<input type="submit" class="button blue" value="Update Folder">
	</footer>	
</form>