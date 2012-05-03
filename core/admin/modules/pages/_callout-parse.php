<?
	$callouts = array();
	if (count($_POST["callouts"])) {
		foreach ($_POST["callouts"] as $number => $data) {
			if ($data["type"] != "") {
				// Super big hack to get file data in the right place
				if (!is_array($file_data)) {
					$file_data = array();
				}
				if (is_array($_FILES["callouts"]["name"][$number])) {
					foreach ($_FILES["callouts"]["name"][$number] as $key => $val) {
						$file_data["name"][$key] = $val;
					}
				}
				if (is_array($_FILES["callouts"]["tmp_name"][$number])) {
					foreach ($_FILES["callouts"]["tmp_name"][$number] as $key => $val) {
						$file_data["tmp_name"][$key] = $val;
					}
				}
				
				$callout = array();
				$callout_data = $cms->getCallout($data["type"]);
				$callout_resources = json_decode($callout_data["resources"],true);
				
				foreach ($callout_resources as $options) {
					$key = $options["id"];
					$type = $options["type"];
					$options["directory"] = "files/pages/";
					
					$tpath = BigTree::path("admin/form-field-types/process/$type.php");
				
					$no_process = false;
					// If we have a customized handler for this data type, run it, otherwise, it's simply the post value.
					if (file_exists($tpath)) {
						include $tpath;
					} else {
						$value = htmlspecialchars($data[$key]);
					}
					$value = $admin->autoIPL($value);
					if (!$no_process)
						$callout[$key] = $value;
				}
				$callout["type"] = $data["type"];
				$callout["display_title"] = $data["display_title"];
				$callouts[] = $callout;
			}
		}
	}
		
	$_POST["callouts"] = $callouts;
?>