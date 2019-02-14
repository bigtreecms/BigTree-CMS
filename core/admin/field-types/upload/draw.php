<?php
	namespace BigTree;
?>
<div class="upload_field" id="<?=$this->ID?>">
	<div class="contain">
		<input<?php if ($this->Required) { ?> class="required"<?php } ?> type="file" tabindex="<?=$this->TabIndex?>" name="<?=$this->Key?>"<?php if (!empty($this->Settings["valid_extensions"])) { ?> accept="<?=BigTree::safeEncode($this->Settings["valid_extensions"])?>"<?php } ?> />
		<?php
			if ($this->Value) {
				$pathinfo = pathinfo($this->Value);
		?>
		<div class="currently_file">
			<input type="hidden" name="<?=$this->Key?>" value="<?=$this->Value?>" />
			<strong><?=Text::translate("Currently:")?></strong> <a href="<?=$this->Value?>" target="_blank"><?=$pathinfo["basename"]?></a> <a href="#" class="remove_resource"><?=Text::translate("Remove")?></a>
		</div>
		<?php
			}
		?>
	</div>
</div>