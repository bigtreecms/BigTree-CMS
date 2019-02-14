<?php
	namespace BigTree;
	
	// Generate the file manager restrictions
	$button_options = htmlspecialchars(json_encode(["currentlyKey" => $this->Key, "type" => "file"]));
	$name = $link = "";
	
	if ($this->Value) {
		$resource = new Resource($this->Value);
		$name = $resource->Name;
	}
?>
<div class="upload_field<?php if ($this->Settings["validation"] == "required") { ?> reference_required<?php } ?>">
	<a href="#<?=$this->ID?>" data-options="<?=$button_options?>" class="button resource_browser_button"><span class="icon_folder"></span><?=Text::translate("Browse")?></a>
	<br class="clear" />
	<div class="currently_file" id="<?=$this->ID?>"<?php if (empty($this->Value)) { ?> style="display: none;"<?php } ?>>
		<input type="hidden" name="<?=$this->Key?>" value="<?=$this->Value?>" class="js-input">
		<strong><?=Text::translate("Currently:")?></strong>
		<a href="<?=ADMIN_ROOT?>files/edit/file/<?=$this->Value?>/" target="_blank" class="js-current-file"><?=$name?></a>
		<a href="#" class="remove_resource"><?=Text::translate("Remove")?></a>
	</div>
</div>