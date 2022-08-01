<?php
	if ($field["input"]) {
		if (empty($field["settings"]["ignore_timezones"])) {
			$field["output"] = $admin->convertTimestampFromUser($field["input"], "H:i:s");
		} else {
			$field["output"] = date("H:i:s", strtotime($field["input"]));
		}
	}
