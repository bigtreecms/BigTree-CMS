<?php
	if ($field["input"]) {
		$field["output"] = $admin->convertTimestampFromUser($field["input"], "Y-m-d");
	}
