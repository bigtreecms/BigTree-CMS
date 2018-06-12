<?php
	$photos = is_array($field["value"]) ? $field["value"] : array();
	$max = count($photos);
	$current = 0;

	// If we're using a preset, the prefix may be there
	if ($field["settings"]["preset"]) {
		if (!isset($bigtree["media_settings"])) {
			$bigtree["media_settings"] = $cms->getSetting("bigtree-internal-media-settings");
		}
		$preset = $bigtree["media_settings"]["presets"][$field["settings"]["preset"]];
		if (!empty($preset["preview_prefix"])) {
			$field["settings"]["preview_prefix"] = $preset["preview_prefix"];
		}
	}

	// Get min width/height designations
	$min_width = $field["settings"]["min_width"] ? intval($field["settings"]["min_width"]) : 0;
	$min_height = $field["settings"]["min_height"] ? intval($field["settings"]["min_height"]) : 0;

	$button_options = htmlspecialchars(json_encode(array(
		"minWidth" => $min_width,
		"minHeight" => $min_height,
		"type" => "image"
	)));
?>
<div class="photo_gallery_widget" id="<?=$field["id"]?>">
	<ul>
		<?php
			foreach ($photos as $photo) {

				if ($field["settings"]["preview_prefix"]) {
					$preview_image = BigTree::prefixFile($photo["image"],$field["settings"]["preview_prefix"]);
				} else {
					$preview_image = $photo["image"];
				}
		?>
		<li>
			<figure>
				<img src="<?=$preview_image?>" alt="" />
			</figure>
			<input type="hidden" name="<?=$field["key"]?>[<?=$current?>][image]" value="<?=$photo["image"]?>" />
			<input type="hidden" name="<?=$field["key"]?>[<?=$current?>][caption]" value="<?=$photo["caption"]?>" class="caption" />
			<?php if (!$field["settings"]["disable_captions"]) { ?>
			<a href="#" class="icon_edit"></a>
			<?php } ?>
			<a href="#" class="icon_delete"></a>
		</li>
		<?php
				$current++;
			}
		?>
	</ul>
	<footer class="image_field">
		<input type="file" accept="image/*" tabindex="<?=$field["tabindex"]?>" name="<?=$field["key"]?>[<?=$current?>][image]" data-min-width="<?=$min_width?>" data-min-height="<?=$min_height?>" />
		<?php if (!defined("BIGTREE_FRONT_END_EDITOR") && !$bigtree["form"]["embedded"]) { ?>
		<span class="or">OR</span>
		<a href="#<?=$field["id"]?>" data-options="<?=$button_options?>" class="button resource_browser_button"><span class="icon_images"></span>Browse</a>
		<?php } ?>
	</footer>
</div>
<script>
	BigTreePhotoGallery({
		container: "<?=$field["id"]?>",
		key: "<?=$field["key"]?>",
		count: <?=$current?>
		<?php if ($field["settings"]["disable_captions"]) { ?>,disableCaptions: true<?php } ?>
	});
</script>