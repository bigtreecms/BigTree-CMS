<?php
	$items = is_array($field["value"]) ? $field["value"] : array();
	$max = !empty($field["settings"]["max"]) ? $field["settings"]["max"] : 0;
	$current = 0;

	// Strip out columns as we'll pass options separately
	$settings = $field["settings"];
	unset($settings["columns"]);
?>
<div class="photo_gallery_widget" id="<?=$field["id"]?>">
	<ul>
		<?php
			foreach ($items as $item) {
				if ($item["type"] == "video") {
					$type = $item["video"]["service"];
				} else {
					$type = "image";
				}

				if ($field["settings"]["preview_prefix"]) {
					$preview_image = BigTree::prefixFile($item["image"], $field["settings"]["preview_prefix"]);
				} else {
					$preview_image = $item["image"];
				}
		?>
		<li>
			<figure<?php if ($item["type"] == "video") { ?> class="media_gallery_type_<?=$item["video"]["service"]?>"<?php } ?>>
				<img src="<?=$preview_image?>" alt="" />
			</figure>
			<input type="hidden" class="bigtree_matrix_data" value="<?=base64_encode(json_encode($item))?>" />
			<?php BigTreeAdmin::drawArrayLevel(array($current), $item, $field) ?>
			<a href="#" class="icon_edit" data-type="<?=$type?>"></a>
			<a href="#" class="icon_delete"></a>
		</li>
		<?php
				$current++;
			}
		?>
	</ul>
	<footer class="media_gallery_footer">
		<?php
			if (!$field["settings"]["disable_photos"]) {
		?>
		<a href="#" class="add_item button" data-type="photo"><span class="icon_small icon_small_picture"></span>Add Photo</a>
		<?php
			}

			if (!$field["settings"]["disable_youtube"]) {
		?>
		<a href="#" class="add_item button" data-type="youtube"><span class="icon_small icon_small_youtube"></span>Add YouTube Video</a>
		<?php
			}

			if (!$field["settings"]["disable_vimeo"]) {
		?>
		<a href="#" class="add_item button" data-type="vimeo"><span class="icon_small icon_small_vimeo"></span>Add Vimeo Video</a>
		<?php
			}

			if ($field["settings"]["enable_manual"]) {
		?>
		<a href="#" class="add_item button" data-type="local"><span class="icon_small icon_small_video"></span>Add Local Video</a>
		<?php
			}

			if ($max) {
		?>
		<small class="max">LIMIT <?=$max?></small>
		<?php
			}
		?>
	</footer>

	<script>
		BigTree.hookReady(function() {
			BigTreeMediaGallery({
				selector: "#<?=$field["id"]?>",
				key: "<?=$field["key"]?>",
				columns: <?=json_encode($field["settings"]["columns"])?>,
				settings: <?=json_encode($settings)?>,
				max: <?=$max?>
			});
		});
	</script>
</div>