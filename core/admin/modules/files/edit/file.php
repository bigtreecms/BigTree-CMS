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

	$metadata = $cms->getSetting("bigtree-file-metadata-fields");

	if ($file["is_image"]) {
		$meta_fields = $metadata["image"];
	} elseif ($file["is_video"]) {
		$meta_fields = $metadata["video"];
	} else {
		$meta_fields = $metadata["file"];
	}
?>
<form class="container" method="post" action="<?=ADMIN_ROOT?>files/update/file/" enctype="multipart/form-data">
	<?php $admin->drawCSRFToken(); ?>
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
				BigTreeAdmin::drawField([
					"title" => "Replace File",
					"subtitle" => "(leave empty to preserve current file)",
					"type" => "upload",
					"key" => "file",
					"id" => "field_file",
					"value" => $file["file"],
					"options" => [
						"image" => $file["is_image"] ? "on" : "",
						"preview_prefix" => "list-preview/"
					]
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
						"value" => $file["metadata"][$meta["id"]]
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