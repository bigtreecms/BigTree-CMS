<?php
	$file = $admin->getResource($bigtree["commands"][0]);

	if (!$file) {
		$admin->stop("Invalid resource.");
	}

	$permission = $admin->getResourceFolderPermission($file["folder"]);

	if ($permission != "p") {
		$admin->stop("Access denied.");
	}

	// Create custom breadcrumb
	$bigtree["breadcrumb"] = [
		["link" => "files", "title" => "Files"]
	];

	$breadcrumb = $admin->getResourceFolderBreadcrumb($file["folder"]);

	foreach ($breadcrumb as $piece) {
		$bigtree["breadcrumb"][] = ["link" => "files/folder/".$piece["id"], "title" => $piece["name"]];
	}

	$bigtree["breadcrumb"][] = ["link" => "#", "title" => "Edit File"];

	$metadata = BigTreeJSONDB::get("config", "file-metadata");

	if ($file["is_image"]) {
		$meta_fields = $metadata["image"];
	} elseif ($file["is_video"]) {
		$meta_fields = $metadata["video"];
	} else {
		$meta_fields = $metadata["file"];
	}
	
	$bigtree["field_namespace"] = "file_field_";
?>
<form class="container" method="post" action="<?=ADMIN_ROOT?>files/update/file/" enctype="multipart/form-data">
	<?php
		if ($admin->Level > 1) {
	?>
	<div class="developer_buttons">
		<a href="<?=ADMIN_ROOT?>developer/files/" title="Edit Metadata Settings">
			Edit Metadata Settings
			<span class="icon_small icon_small_edit_yellow"></span>
		</a>
		<a href="<?=ADMIN_ROOT?>developer/audit/?table=bigtree_resources&entry=<?=$file["id"]?><?php $admin->drawCSRFTokenGET(); ?>" title="View File Audit Trail">
			View File Audit Trail
			<span class="icon_small icon_small_trail"></span>
		</a>
	</div>
	<?php
		}

		$admin->drawCSRFToken();
	?>
	<input type="hidden" name="id" value="<?=$file["id"]?>">

	<section>
		<?php
			if ($admin->Level) {
		?>
		<fieldset>
			<label for="field_folder_parent">Parent Folder</label>
			<select id="field_folder_parent" name="folder">
				<option value="0"<?php if (!$file["folder"]) { ?> selected<?php } ?>>&mdash;</option>
				<?php $recurse_folders($file["folder"]); ?>
			</select>
		</fieldset>
		<?php
			}
		?>

		<fieldset>
			<label for="field_file_name">Resource Name <small>(used for search)</small></label>
			<input id="field_file_name" type="text" name="name" value="<?=$file["name"]?>">
		</fieldset>

		<?php
			if (!$file["is_video"]) {
				$field_settings = [];

				if ($file["is_image"]) {		
					$settings = BigTreeJSONDB::get("config", "media-settings");
					$field_settings = $settings["presets"]["default"];
					$field_settings["directory"] = "files/resources/";
					$field_settings["image"] = "on";
					$field_settings["preview_prefix"] = "list-preview/";
					$field_settings["preview_files_square"] = true;

					// Figure out what the minimum size should be based on the current one
					$field_settings["min_height"] = 0;
					$field_settings["min_width"] = 0;

					if (is_array($file["crops"])) {
						foreach ($file["crops"] as $prefix => $data) {
							if ($data["width"] > $field_settings["min_width"]) {
								$field_settings["min_width"] = $data["width"];
							}
	
							if ($data["height"] > $field_settings["min_height"]) {
								$field_settings["min_height"] = $data["height"];
							}
						}
					}

					$min_message = " — replacing the current file requires a minimum image size of ".$field_settings["min_width"]."x".$field_settings["min_height"];
				} else {
					$min_message = "";
				}

				BigTreeAdmin::drawField([
					"title" => "Replace File",
					"subtitle" => "(leave empty to preserve current file".$min_message.")",
					"type" => "upload",
					"key" => "file",
					"value" => $file["file"],
					"settings" => $field_settings
				]);
			}

			if (is_array($meta_fields) && count($meta_fields)) {
				echo "<hr>";

				$bigtree["field_namespace"] = "file_meta";
				$tabindex = 1;

				foreach ($meta_fields as $meta) {
					$tabindex++;

					$field = array(
						"type" => $meta["type"],
						"title" => $meta["title"],
						"subtitle" => $meta["subtitle"],
						"key" => "metadata[".$meta["id"]."]",
						"tabindex" => $tabindex,
						"settings" => $meta["settings"] ?: $meta["options"],
						"has_value" => isset($file["metadata"][$meta["id"]]),
						"value" => isset($file["metadata"][$meta["id"]]) ? $file["metadata"][$meta["id"]] : ""
					);

					BigTreeAdmin::drawField($field);
				}
			}
		?>
	</section>

	<footer>
		<input type="submit" class="button blue" value="Update File">
	</footer>
</form>

<?php include BigTree::path("admin/layouts/_html-field-loader.php"); ?>

<script>
	BigTreeFormValidator("form.container");
</script>