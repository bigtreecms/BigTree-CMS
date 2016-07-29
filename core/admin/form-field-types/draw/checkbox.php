<?php
	namespace BigTree;
?>
<fieldset>
	<input<?php if ($this->Required) { ?> class="required"<?php } ?> type="checkbox" tabindex="<?=$this->TabIndex?>" name="<?=$this->Key?>" id="<?=$this->ID?>" <?php if ($this->Value) { ?>checked="checked" <?php } ?><?php if ($this->Settings["custom_value"]) { ?> value="<?=Text::htmlEncode($this->Settings["custom_value"])?>"<?php } ?> />
	<?php if ($this->Title) { ?>
	<label<?php if ($this->Required) { ?> class="required"<?php } ?> class="for_checkbox" for="<?=$this->ID?>">
		<?=$this->Title?><?php if ($this->Subtitle) { ?> <small><?=$this->Subtitle?></small><?php } ?>
	</label>
	<?php } ?>
</fieldset>