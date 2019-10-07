<?php
	namespace BigTree;
?>
<div class="upload_field" id="<?=$this->ID?>">
	<div class="contain">
		<input<?php if ($this->Required) { ?> class="required"<?php } ?> type="file" tabindex="<?=$this->TabIndex?>" name="<?=$this->Key?>"<?php if (!empty($this->Settings["valid_extensions"])) { ?> accept="<?=Text::htmlEncode($this->Settings["valid_extensions"])?>"<?php } ?> />
		<?php
			if ($this->Value) {
				$pathinfo = pathinfo($this->Value);
		?>
		<div class="currently_file">
			<input type="hidden" name="<?=$this->Key?>" value="<?=$this->Value?>" />
			<strong><?=Text::translate("Currently:")?></strong> <a href="<?=$this->Value?>" target="_blank"><?=$pathinfo["basename"]?></a>
			<?php
				if (empty($this->Settings["disable_remove"])) {
			?>
			<a href="#" class="remove_resource"><?=Text::translate("Remove")?></a>
			<?php
				}
			?>
		</div>
		<?php
			}
		?>
	</div>
</div>