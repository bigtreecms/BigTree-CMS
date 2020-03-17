<?php
	namespace BigTree;
	
	if (!$this->Value && isset($this->Settings["default_today"]) && $this->Settings["default_today"]) {
		$this->Value = date(Router::$Config["date_format"]);
	} elseif ($this->Value && $this->Value != "0000-00-00" && $this->Value != "0000-00-00 00:00:00") {
		$this->Value = date(Router::$Config["date_format"], strtotime($this->Value));
	} else {
		$this->Value = "";
	}
?>
<field-type-date name="<?=$this->Key?>" :required="<?=$this->Required?>" value="<?=$this->Value?>"></field-type-date>