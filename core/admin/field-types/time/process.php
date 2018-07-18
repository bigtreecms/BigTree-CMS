<?php
	if ($field["input"]) {
		$field["output"] = $admin->convertTimestampFromUser($field["input"], "H:i:s");
	}
	