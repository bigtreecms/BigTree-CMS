<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 * @global callable $recurse_folders
	 */
	
	// Create custom breadcrumb
	$bigtree["breadcrumb"] = [
		["link" => "files", "title" => "Files"]
	];
	
	$folder_id = intval(Router::$Commands[0]);
	
	if (!ResourceFolder::exists($folder_id)) {
		Auth::stop("Folder does not exist.");
	}
	
	$folder = new ResourceFolder($folder_id);
	$breadcrumb = $folder->Breadcrumb;

	foreach ($breadcrumb as $piece) {
		$bigtree["breadcrumb"][] = ["link" => "files/folder/".$piece["id"], "title" => $piece["name"]];
	}

	if ($folder->UserAccessLevel != "p") {
		Auth::stop("You do not have permission to edit this folder.");
	}
?>
<form method="post" action="<?=ADMIN_ROOT?>files/update/folder/" class="container">
	<?php CSRF::drawPOSTToken(); ?>
	<input type="hidden" name="folder" value="<?=$folder->ID?>">
	
	<section>
		<?php
			if (Auth::user()->Level) {
		?>
		<fieldset>
			<label for="field_folder_parent"><?=Text::translate("Parent Folder")?></label>
			<select id="field_folder_parent" name="parent">
				<option value="0"<?php if (!$folder->Parent) { ?> selected<?php } ?>>&mdash;</option>
				<?php $recurse_folders($folder->Parent); ?>
			</select>
		</fieldset>
		<?php
			}
		?>
		<fieldset>
			<label for="field_folder_name"><?=Text::translate("Folder Name")?></label>
			<input type="text" name="name" id="field_folder_name" value="<?=$folder->Name?>">
		</fieldset>
	</section>

	<footer>
		<input type="submit" class="button blue" value="<?=Text::translate("Update Folder", true)?>">
	</footer>	
</form>