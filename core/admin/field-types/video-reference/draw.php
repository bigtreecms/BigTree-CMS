<?php
	namespace BigTree;
	
	$resource = null;
	
	if ($this->Value) {
		if (Resource::exists($this->Value)) {
			$resource = new Resource($this->Value);
		}
	}
	
	// Generate the file manager restrictions
	$button_options = htmlspecialchars(json_encode(["currentlyKey" => $this->Key, "type" => "video"]));
?>
<div class="image_field<?php if ($this->Settings["validation"] == "required") { ?> reference_required<?php } ?>">
	<a href="#<?=$this->ID?>" data-options="<?=$button_options?>" class="button resource_browser_button"><span class="icon_video"></span><?=Text::translate("Browse")?></a>
	<br class="clear" />
	<div class="currently" id="<?=$this->ID?>"<?php if (is_null($resource)) { ?> style="display: none;"<?php } ?>>
		<a href="#" class="remove_resource"></a>
		<div class="currently_wrapper">
			<?php
				if ($this->Value) {
					if (strtolower($resource->Locaation) == "youtube") {
						echo '<iframe src="https://youtube.com/embed/'.$resource->VideoData["id"].'?autoplay=false&showinfo=false&showrel=false&showcontrols=false"></iframe>';
					} elseif (strtolower($resource->Location) == "vimeo") {
						echo '<iframe src="https://player.vimeo.com/video/'.$resource->VideoData["id"].'?autoplay=false&showinfo=false&showrel=false&showcontrols=false"></iframe>';
					}
				}
			?>
		</div>
		<label><?=Text::translate("CURRENT")?></label>
		<input type="hidden" name="<?=$this->Key?>" value="<?=$this->Value?>" />
	</div>
</div>