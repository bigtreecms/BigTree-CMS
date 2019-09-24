<?php
	namespace BigTree;
	
	$type = "full";
	
	if (!empty($this->Settings["simple"]) ||
		(isset($this->Settings["simple_by_permission"]) && $this->Settings["simple_by_permission"] > Auth::user()->Level)
	) {
		$type = "simple";
	}
?>
<field-type-html title="<?=$this->Title?>" subtitle="<?=$this->Subtitle?>"
				 name="<?=$this->Key?>" type="<?=$type?>" required="<?=$this->Required?>"
				 value="<?=Text::htmlEncode($this->Value)?>"></field-type-html>