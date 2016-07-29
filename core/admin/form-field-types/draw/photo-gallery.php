<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$photos = is_array($this->Value) ? $this->Value : array();
	$current = 0;

	// If we're using a preset, the prefix may be there
	if ($this->Settings["preset"]) {
		if (!isset($bigtree["media_settings"])) {
			$bigtree["media_settings"] = Setting::value("bigtree-internal-media-settings");
		}
		$preset = $bigtree["media_settings"]["presets"][$this->Settings["preset"]];
		if (!empty($preset["preview_prefix"])) {
			$this->Settings["preview_prefix"] = $preset["preview_prefix"];
		}
	}

	// Get min width/height designations
	$min_width = $this->Settings["min_width"] ? intval($this->Settings["min_width"]) : 0;
	$min_height = $this->Settings["min_height"] ? intval($this->Settings["min_height"]) : 0;

	$button_options = htmlspecialchars(json_encode(array(
		"minWidth" => $min_width,
		"minHeight" => $min_height
	)));
?>
<div class="photo_gallery_widget" id="<?=$this->ID?>">
	<ul>
		<?php
			foreach ($photos as $photo) {

				if ($this->Settings["preview_prefix"]) {
					$preview_image = FileSystem::getPrefixedFile($photo["image"],$this->Settings["preview_prefix"]);
				} else {
					$preview_image = $photo["image"];
				}
		?>
		<li>
			<figure>
				<img src="<?=$preview_image?>" alt="" />
			</figure>
			<input type="hidden" name="<?=$this->Key?>[<?=$current?>][image]" value="<?=$photo["image"]?>" />
			<input type="hidden" name="<?=$this->Key?>[<?=$current?>][caption]" value="<?=$photo["caption"]?>" class="caption" />
			<?php if (!$this->Settings["disable_captions"]) { ?>
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
		<input type="file" accept="image/*" tabindex="<?=$this->TabIndex?>" name="<?=$this->Key?>[<?=$current?>][image]" data-min-width="<?=$min_width?>" data-min-height="<?=$min_height?>" />
		<?php if (!defined("BIGTREE_FRONT_END_EDITOR") && !$bigtree["form"]["embedded"]) { ?>
		<span class="or">OR</span>
		<a href="#<?=$this->ID?>" data-options="<?=$button_options?>" class="button form_image_browser"><span class="icon_images"></span>Browse</a>
		<?php } ?>
	</footer>
</div>
<script>
	BigTreePhotoGallery({
		container: "<?=$this->ID?>",
		key: "<?=$this->Key?>",
		count: <?=$current?>
		<?php if ($this->Settings["disable_captions"]) { ?>,disableCaptions: true<?php } ?>
	});
</script>