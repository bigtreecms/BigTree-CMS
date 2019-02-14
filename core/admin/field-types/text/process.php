<?php
	namespace BigTree;
	
	if (is_array($this->Input)) {
		foreach ($this->Input as &$v) {
			$v = Text::htmlEncode($v);
		}
		
		if ($this->Settings["sub_type"] == "phone") {
			$this->Output = $this->Input["phone_1"]."-".$this->Input["phone_2"]."-".$this->Input["phone_3"];
		} elseif ($this->Settings["sub_type"] == "address" || $this->Settings["sub_type"] == "name") {
			$this->Output = $this->Input;
		}
	} else {
		$this->Output = Text::htmlEncode($this->Input);
	}
