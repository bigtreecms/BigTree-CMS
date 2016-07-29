<?php
	$date = DateTime::createFromFormat($bigtree["config"]["date_format"]." h:i a", $this->Input);
	
	// Fallback to SQL standards for existing values
	if (!$date) {
		$date = DateTime::createFromFormat("Y-m-d H:i:s", $this->Input);
	}
	
	if ($date) {
		$this->Output = $date->format("Y-m-d H:i:s");
	} else {
		$this->Output = "";
	}
	