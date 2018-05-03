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
<form class="container" method="post" action="<?=ADMIN_ROOT?>files/update/file/">
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
			<label>File Name <small>(used for search)</small></label>
			<input type="text" name="name" value="<?=$file["name"]?>">
		</fieldset>

		<?php
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
						"settings" => json_decode($meta["options"], true),
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