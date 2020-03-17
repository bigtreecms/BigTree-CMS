<?php
	namespace BigTree;
	use DateTime;
	
	if (!$this->Value && isset($this->Settings["default_now"]) && $this->Settings["default_now"]) {
		$this->Value = Auth::user()->convertTimestampTo("now", Router::$Config["date_format"]." h:i a");
	} elseif ($this->Value && $this->Value != "0000-00-00 00:00:00") {
		$this->Value = Auth::user()->convertTimestampTo($this->Value, Router::$Config["date_format"]." h:i a");
	} else {
		$this->Value = "";
	}
?>
<field-type-datetime name="<?=$this->Key?>" :required="<?=$this->Required?>" value="<?=$this->Value?>"></field-type-datetime>
