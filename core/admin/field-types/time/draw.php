<?php
	namespace BigTree;
	
	if ($this->Value) {
		$this->Value = Auth::user()->convertTimestampTo($this->Value ?: "now", "h:i a");
	}
?>
<field-type-time name="<?=$this->Key?>" :required="<?=$this->Required?>" value="<?=$this->Value?>"></field-type-time>
