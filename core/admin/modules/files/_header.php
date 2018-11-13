<?php
	$recurse_folders = function($current, $parent = 0, $depth = 0) {
		global $folder, $recurse_folders;

		$folders = SQL::fetchAll("SELECT id, name FROM bigtree_resource_folders WHERE parent = ? ORDER BY name ASC", $parent);

		foreach ($folders as $child) {
			if ($child["id"] != $folder["id"]) {
				echo '<option data-depth="'.$depth.'" value="'.$child["id"].'"';

				if ($child["id"] == $current) {
					echo ' selected';
				}

				echo '>'.$child["name"].'</option>';

				$recurse_folders($current, $child["id"], $depth + 1);
			}
		}
	};
