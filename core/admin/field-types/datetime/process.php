<?php
	namespace BigTree;
	use DateTime;
	
	/**
	 * @global array $bigtree
	 */
	
	if ($this->Input) {
		$date = DateTime::createFromFormat($bigtree["config"]["date_format"]." h:i a", Auth::user()->getTimestampFrom($this->Input));
		
		// Fallback to SQL standards for existing values
		if (!$date) {
			$date = DateTime::createFromFormat("Y-m-d H:i:s", Auth::user()->getTimestampFrom($this->Input));
		}
	
		if ($date) {
			$this->Output = $date->format("Y-m-d H:i:s");
		} else {
			$this->Output = "";
		}
	}
	