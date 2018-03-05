<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */

	// If we're using a preset, the prefix may be there
	if (!empty($this->Settings["preset"])) {
		if (!isset($bigtree["media_settings"])) {
			$bigtree["media_settings"] = Setting::value("bigtree-internal-media-settings");
		}
		$preset = $bigtree["media_settings"]["presets"][$this->Settings["preset"]];
		if (!empty($preset["preview_prefix"])) {
			$this->Settings["preview_prefix"] = $preset["preview_prefix"];
		}
		if (!empty($preset["min_width"])) {
			$this->Settings["min_width"] = $preset["min_width"];
		}
		if (!empty($preset["min_height"])) {
			$this->Settings["min_height"] = $preset["min_height"];
		}
	}

	// Get min width/height designations
	$min_width = $this->Settings["min_width"] ? intval($this->Settings["min_width"]) : 0;
	$min_height = $this->Settings["min_height"] ? intval($this->Settings["min_height"]) : 0;
?>
<div class="<?php if (empty($this->Settings["image"])) { ?>upload_field<?php } else { ?>image_field<?php } ?>">
	<input<?php if ($this->Required) { ?> class="required"<?php } ?> type="file" tabindex="<?=$this->TabIndex?>" name="<?=$this->Key?>" data-min-width="<?=$min_width?>" data-min-height="<?=$min_height?>" />
	<?php
		if (!isset($this->Settings["image"]) || !$this->Settings["image"]) {
			if ($this->Value) {
				$pathinfo = pathinfo($this->Value);
	?>
	<div class="currently_file">
		<input type="hidden" name="<?=$this->Key?>" value="<?=$this->Value?>" />
		<strong>Currently:</strong> <a href="<?=$this->Value?>" target="_blank"><?=$pathinfo["basename"]?></a> <a href="#" class="remove_resource">Remove</a>
	</div>
	<?php
			}
		} else {
			if ($this->Value) {
				if ($this->Settings["preview_prefix"]) {
					$preview_image = FileSystem::getPrefixedFile($this->Value,$this->Settings["preview_prefix"]);
				} else {
					$preview_image = $this->Value;
				}
			} else {
				$preview_image = false;
			}
			
			// Generate the file manager restrictions
			$button_options = htmlspecialchars(json_encode(array(
				"minWidth" => $min_width,
				"minHeight" => $min_height,
				"currentlyKey" => $this->Key
			)));
			
			if (!defined("BIGTREE_FRONT_END_EDITOR") && !$bigtree["form"]["embedded"]) {
	?>
	<span class="or">OR</span>
	<a href="#<?=$this->ID?>" data-options="<?=$button_options?>" class="button form_image_browser"><span class="icon_images"></span>Browse</a>
	<?php
			}
	?>
	<br class="clear" />
	<div class="currently" id="<?=$this->ID?>"<?php if (!$this->Value) { ?> style="display: none;"<?php } ?>>
		<a href="#" class="remove_resource"></a>
		<div class="currently_wrapper">
			<?php if ($preview_image) { ?>
			<a href="<?=$this->Value?>" target="_blank"><img src="<?=$preview_image?>" alt="" /></a>
			<?php } ?>
		</div>
		<label>CURRENT</label>
		<input type="hidden" name="<?=$this->Key?>" value="<?=$this->Value?>" />
	</div>
	<?php
		}
	?>
</div>