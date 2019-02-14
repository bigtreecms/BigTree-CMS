<?php
	namespace BigTree;
	
	if ($this->Input) {
		$this->Output = date(strtotime($this->Input), "Y-m-d");
	}
