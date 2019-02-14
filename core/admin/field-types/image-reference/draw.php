<?php
	namespace BigTree;
	
	$preview_image = null;
	
	if ($this->Value && Resource::exists($this->Value)) {
		$resource = new Resource($this->Value);
		$preview_image = FileSystem::getPrefixedFile($resource->File, "list-preview/");
	}
	
	// Generate the file manager restrictions
	$button_options = htmlspecialchars(json_encode([
		"currentlyKey" => $this->Key,
		"type" => "image",
		"minWidth" => intval($this->Settings["min_width"]),
		"minHeight" => intval($this->Settings["min_height"])
	]));
?>
<div class="image_field<?php if ($this->Settings["validation"] == "required") { ?> reference_required<?php } ?>">
	<a href="#<?=$this->ID?>" data-options="<?=$button_options?>" class="button resource_browser_button"><span class="icon_images"></span><?=Text::translate("Browse")?></a>
	
	<br class="clear" />
	
	<div class="currently currently_image_reference" id="<?=$this->ID?>"<?php if (!$this->Value || !$preview_image) { ?> style="display: none;"<?php } ?>>
		<a href="#" class="remove_resource"></a>
		
		<div class="currently_wrapper">
			<?php if ($preview_image) { ?>
			<a href="<?=ADMIN_ROOT?>files/edit/file/<?=$this->Value?>/" target="_blank"><img src="<?=$preview_image?>" alt="" /></a>
			<?php } ?>
		</div>
		
		<label><?=Text::translate("CURRENT")?></label>
		<input type="hidden" name="<?=$this->Key?>" value="<?=$this->Value?>" />
	</div>
</div>