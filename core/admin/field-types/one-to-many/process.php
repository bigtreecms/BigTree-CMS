<?php
	if (is_array($field["input"])) {
		$field["output"] = array_values($field["input"]);
	} else {
		$field["output"] = [];
	}
