<?php
	namespace BigTree;
	
	$checked = ($this->HasValue && $this->Value) || (!$this->HasValue && !empty($this->Settings["default_checked"]));
	$value_attribute = !empty($this->Settings["custom_value"]) ? $this->Settings["custom_value"] : "on";
?>
<field-type-checkbox name="<?=$this->Key?>" :checked="<?=$checked?>" :required="<?=$this->Required?>"
					 value="<?=Text::htmlEncode($value_attribute)?>"></field-type-checkbox>
