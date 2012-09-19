<?	
	$htmlerrors = array();	
	$form = BigTreeAutoModule::getForm($_GET["form"]);	
	$m = new BigTreeModule;
	$m->Table = $form["table"];
	$item = $m->get($_GET["id"]);
	$action = $admin->getModuleActionForForm($form);
	$module = $admin->getModule($action["module"]);
	
	if ($_GET["external"]) {
		$external = true;
	} else {
		$external = false;
	}
	
	foreach ($form["fields"] as $field => $resource) {
		if ($resource["type"] == "html") {
			$htmlerrors[$field] = $admin->checkHTML("",$item[$field],$external);
		} elseif ($resource["type"] == "text") {
			$href = $item[$field];
			if (substr($href,0,4) == "http" && strpos($href,WWW_ROOT) === false) {
				if ($external) {
					if (strpos($href,"#") !== false) {
						$href = substr($href,0,strpos($href,"#")-1);
					}
					if (!$admin->urlExists($href)) {
						$htmlerrors[$field] = array("a" => array($href));
					}
				}
			} elseif (substr($href,0,4) == "http") {
				if (!$admin->urlExists($href)) {
					$htmlerrors[$field] = array("a" => array($href));
				}
			}
		}
	}
	$errorhtml = "";
	
	foreach ($htmlerrors as $field => $error_array) {
		foreach ($error_array as $type => $errors) {
			if ($type == "img") {
				$ti = "Image";
			} else {
				$ti = "Link";
			}
			foreach ($errors as $error) {
				echo '<li><section class="integrity_errors"><span class="icon_small icon_small_warning"></span> Broken '.$ti.': '.$error.' in field &ldquo;'.$form["fields"][$field]["title"].'&rdquo; <a href="'.ADMIN_ROOT.$module["route"]."/".$action["route"]."/".$_GET["id"].'/" target="_blank">Edit</a></section></li>';
			}
		}
	}
?>