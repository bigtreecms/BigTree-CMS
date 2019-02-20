<?php
	namespace BigTree;
	
	/**
	 * @global ResourceFolder $folder
	 */
	
	$bigtree["breadcrumb"][] = ["link" => "#", "title" => "Create New Folder"];
?>
<form method="post" action="<?=ADMIN_ROOT?>files/process/folder/" class="container">
	<?php CSRF::drawPOSTToken(); ?>
	<input type="hidden" name="folder" value="<?=$folder->ID?>">
	
	<section>
		<fieldset>
			<label for="field_folder_name"><?=Text::translate("Folder Name")?></label>
			<input type="text" name="name" id="field_folder_name">
		</fieldset>
	</section>

	<footer>
		<input type="submit" class="button blue" value="<?=Text::translate("Create Folder", true)?>">
	</footer>	
</form>