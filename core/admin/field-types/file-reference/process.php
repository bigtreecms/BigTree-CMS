<?php
	$field["output"] = $field["input"];

	if (is_numeric($field["input"])) {
		BigTreeAdmin::trackResource($field["input"]);
	}
