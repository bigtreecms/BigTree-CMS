<?php
	/**
	 * @global array $bigtree
	 */
	
	$bigtree["many-to-many"][$this->Key] = array(
		"table" => $this->Settings["mtm-connecting-table"],
		"my-id" => $this->Settings["mtm-my-id"],
		"other-id" => $this->Settings["mtm-other-id"],
		"data" => $this->Input
	);	

	// This field doesn't have it's own key to process.
	$this->Ignore = true;
