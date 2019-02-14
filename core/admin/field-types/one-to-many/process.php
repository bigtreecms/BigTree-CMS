<?php
	if (is_array($this->Input)) {
		$this->Output = array_values($this->Input);
	} else {
		$this->Output = [];
	}
