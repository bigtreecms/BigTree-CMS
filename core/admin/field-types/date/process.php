<?php
	namespace BigTree;
	
	if ($this->Input) {
		$this->Output = date("Y-m-d", strtotime($this->Input));
	}
