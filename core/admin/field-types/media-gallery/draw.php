<?php
	namespace BigTree;
	
	$items = is_array($this->Value) ? $this->Value : [];
	$max = !empty($this->Settings["max"]) ? $this->Settings["max"] : 0;
	$current = 0;

	// Strip out columns as we'll pass options separately
	$settings = $this->Settings;
	unset($settings["columns"]);
?>
<div class="photo_gallery_widget" id="<?=$this->ID?>">
	<ul>
		<?php
			foreach ($items as $item) {
				if (empty($item["type"])) {
					$item["type"] = "image";
				}

				if ($item["type"] == "video") {
					$type = $item["video"]["service"];
				} else {
					$type = "image";
				}

				if ($this->Settings["preview_prefix"]) {
					$preview_image = FileSystem::getPrefixedFile($item["image"], $this->Settings["preview_prefix"]);
				} else {
					$preview_image = $item["image"];
				}
		?>
		<li>
			<figure<?php if ($item["type"] == "video") { ?> class="media_gallery_type_<?=$item["video"]["service"]?>"<?php } ?>>
				<img src="<?=$preview_image?>" alt="" />
			</figure>
			<input type="hidden" class="bigtree_matrix_data" value="<?=base64_encode(json_encode($item))?>" />
			<?php $this->drawArrayLevel([$current], $item) ?>
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
			if (empty($this->Settings["disable_photos"])) {
		?>
		<a href="#" class="add_item button" data-type="photo"><span class="icon_small icon_small_picture"></span><?=Text::translate("Add Photo")?></a>
		<?php
			}

			if (empty($this->Settings["disable_youtube"])) {
		?>
		<a href="#" class="add_item button" data-type="youtube"><span class="icon_small icon_small_youtube"></span><?=Text::translate("Add YouTube Video")?></a>
		<?php
			}

			if (empty($this->Settings["disable_vimeo"])) {
		?>
		<a href="#" class="add_item button" data-type="vimeo"><span class="icon_small icon_small_vimeo"></span><?=Text::translate("Add Vimeo Video")?></a>
		<?php
			}

			if (!empty($this->Settings["enable_manual"])) {
		?>
		<a href="#" class="add_item button" data-type="local"><span class="icon_small icon_small_video"></span><?=Text::translate("Add Local Video")?></a>
		<?php
			}

			if ($max) {
		?>
		<small class="max"><?=Text::translate("LIMIT :max:", false, [":max:" => $max])?></small>
		<?php
			}
		?>
	</footer>

	<script>
		BigTree.hookReady(function() {
			BigTreeMediaGallery({
				selector: "#<?=$this->ID?>",
				key: "<?=$this->Key?>",
				columns: <?=json_encode($this->Settings["columns"])?>,
				settings: <?=json_encode($settings)?>,
				max: <?=$max?>
			});
		});
	</script>
</div>