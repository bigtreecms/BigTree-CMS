<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	// Generate the file manager restrictions
	$button_options = htmlspecialchars(json_encode(["currentlyKey" => $this->Key."[managed]", "type" => "video"]));
?>
<div class="image_field video_field">
	<div class="contain">
		<input<?php if ($this->Required) { ?> class="required"<?php } ?> type="url" tabindex="<?=$this->TabIndex?>" name="<?=$this->Key?>[new]" placeholder="<?=Text::translate("YouTube or Vimeo URL", true)?>" />
		<?php
			if (empty($bigtree["form"]["embedded"])) {
		?>
		<span class="or"><?=Text::translate("OR")?></span>
		<a href="#<?=$this->ID?>" data-options="<?=$button_options?>" class="button resource_browser_button"><span class="icon_video"></span><?=Text::translate("Browse")?></a>
		<?php
			}
		?>
	</div>
	<div class="currently" id="<?=$this->ID?>"<?php if (empty($this->Value)) { ?> style="display: none;"<?php } ?>>
		<a href="#" class="remove_resource"></a>
		<div class="currently_wrapper">
			<?php
				if ($this->Value) {
					if ($this->Value["service"] == "YouTube") {
						echo '<iframe src="https://youtube.com/embed/'.$this->Value["id"].'?autoplay=false&showinfo=false&showrel=false&showcontrols=false"></iframe>';
					} elseif ($this->Value["service"] == "Vimeo") {
						echo '<iframe src="https://player.vimeo.com/video/'.$this->Value["id"].'?autoplay=false&showinfo=false&showrel=false&showcontrols=false"></iframe>';
					}
				}
			?>
		</div>
		<label><?=Text::translate("CURRENT")?></label>
		<input type="hidden" name="<?=$this->Key?>[existing]" value="<?=Text::htmlEncode(json_encode($this->Value))?>" />
	</div>
</div>