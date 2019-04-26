<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 * @global callable $recurse_folders
	 */
	
	$file_id = intval($bigtree["commands"][0]);
	
	if (!Resource::exists($file_id)) {
		Auth::stop("Invalid file.");
	}
	
	$file = new Resource($file_id);

	if ($file->UserAccessLevel != "p") {
		Auth::stop("Access denied.");
	}

	// Create custom breadcrumb
	$bigtree["breadcrumb"] = [
		["link" => "files", "title" => "Files"]
	];
	
	$folder = new ResourceFolder($file->Folder);
	$breadcrumb = $folder->Breadcrumb;

	foreach ($breadcrumb as $piece) {
		$bigtree["breadcrumb"][] = ["link" => "files/folder/".$piece["id"], "title" => $piece["name"]];
	}
	
	$bigtree["breadcrumb"][] = ["link" => "#", "title" => "Edit File"];

	$metadata = DB::get("config", "file-metadata");

	if ($file->IsImage) {
		$meta_fields = $metadata["image"];
	} elseif ($file->IsVideo) {
		$meta_fields = $metadata["video"];
	} else {
		$meta_fields = $metadata["file"];
	}
	
	Field::$Namespace = "file_field_";
?>
<form class="container" method="post" action="<?=ADMIN_ROOT?>files/update/file/" enctype="multipart/form-data">
	<?php
		if (Auth::user()->Level > 1) {
	?>
	<div class="developer_buttons">
		<a href="<?=ADMIN_ROOT?>developer/files/" title="<?=Text::translate("Edit Metadata Settings", true)?>">
			<?=Text::translate("Edit Metadata Settings")?>
			<span class="icon_small icon_small_edit_yellow"></span>
		</a>
		<a href="<?=ADMIN_ROOT?>developer/audit/?table=bigtree_resources&entry=<?=$file->ID?><?php CSRF::drawGETToken(); ?>" title="<?=Text::translate("View File Audit Trail", true)?>">
			<?=Text::translate("View File Audit Trail")?>
			<span class="icon_small icon_small_trail"></span>
		</a>
	</div>
	<?php
		}

		CSRF::drawPOSTToken();
	?>
	<input type="hidden" name="id" value="<?=$file->ID?>">

	<section>
		<?php
			if (Auth::user()->Level) {
		?>
		<fieldset>
			<label for="field_folder_parent"><?=Text::translate("Parent Folder")?></label>
			<select id="field_folder_parent" name="folder">
				<option value="0"<?php if (!$file->Folder) { ?> selected<?php } ?>>&mdash;</option>
				<?php $recurse_folders($file->Folder); ?>
			</select>
		</fieldset>
		<?php
			}
		?>

		<fieldset>
			<label for="field_file_name"><?=Text::translate("Resource Name <small>(used for search)</small>")?></label>
			<input id="field_file_name" type="text" name="name" value="<?=$file->Name?>">
		</fieldset>

		<?php
			if (!$file->IsVideo) {
				$field = [
					"title" => Text::translate("Replace File"),
					"type" => "upload",
					"key" => "file",
					"value" => $file->File,
					"settings" => [
						"directory" => "files/resources/",
						"disable_remove" => true
					]
				];

				if ($file->IsImage) {
					$media_settings = DB::get("config", "media-settings");
					
					$field["type"] = "image";
					$field["settings"] = $media_settings["presets"]["default"];
					$field["settings"]["directory"] = "files/resources/";
					$field["settings"]["preview_prefix"] = "list-preview/";
					$field["settings"]["preview_files_square"] = true;

					// Figure out what the minimum size should be based on the current one
					$field["settings"]["min_height"] = 0;
					$field["settings"]["min_width"] = 0;

					if (is_array($file->Crops)) {
						foreach ($file->Crops as $prefix => $data) {
							if ($data["width"] > $field["settings"]["min_width"]) {
								$field_settings["min_width"] = $data["width"];
							}
	
							if ($data["height"] > $field["settings"]["min_height"]) {
								$field_settings["min_height"] = $data["height"];
							}
						}
					}

					$subtitle = Text::translate("(leave empty to preserve current file — replacing the current file requires a minimum image size of :width:x:height:)", false,
												[":width" => $field["settings"]["min_width"], ":height:" => $field["settings"]["min_height"]]);
				} else {
					$subtitle = Text::translate("(leave empty to preserve current file)");
				}
				
				$field["subtitle"] = $subtitle;
				$field = new Field($field);
				$field->draw();
			}

			if (is_array($meta_fields) && count($meta_fields)) {
				echo "<hr>";
				
				Field::$Namespace = "file_meta_";
				$tabindex = 1;

				foreach ($meta_fields as $meta) {
					$tabindex++;

					$field = new Field([
						"type" => $meta["type"],
						"title" => $meta["title"],
						"subtitle" => $meta["subtitle"],
						"key" => "metadata[".$meta["id"]."]",
						"tabindex" => $tabindex,
						"settings" => $meta["settings"],
						"has_value" => isset($file->Metadata[$meta["id"]]),
						"value" => isset($file->Metadata[$meta["id"]]) ? $file->Metadata[$meta["id"]] : ""
					]);

					$field->draw();
				}
			}
		?>
	</section>

	<footer>
		<input type="submit" class="button blue" value="<?=Text::translate("Update File")?>">
	</footer>
</form>

<?php include Router::getIncludePath("admin/layouts/_html-field-loader.php"); ?>

<script>
	BigTreeFormValidator("form.container");
</script>