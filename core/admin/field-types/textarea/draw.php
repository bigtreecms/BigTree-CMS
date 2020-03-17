<?php
	namespace BigTree;
	
	$max_length = isset($this->Settings["max_length"]) ? intval($this->Settings["max_length"]) : false;
?>
<field-type-text name="<?=$this->Key?>" value="<?=htmlspecialchars($this->Value)?>" maxlength="<?=$max_length?>"
				 title="<?=$this->Title?>" subtitle="<?=$this->Subtitle?>" required="<?=$this->Required?>"
				 type="textarea" feeds_display_title="<?=$this->FeedsDisplayTitle?>">
</field-type-text>