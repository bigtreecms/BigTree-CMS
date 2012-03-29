<?	
	$id = $_GET["id"];
	if ($_GET["external"]) {
		$external = true;
	} else {
		$external = false;
	}
	
	$page = $cms->getPage($id);
	$template = $cms->getTemplate($page["template"]);
	$resources = $page["resources"];
	$htmlerrors = array();
	foreach ($template["resources"] as $resource) {
		$rid = $resource["id"];
		$data = false;
		if ($resource["type"] == "html") {
			$data = $resources[$rid];
			$htmlerrors[] = $admin->checkHTML($cms->getLink($id),$data,$external);
		}
	}
	$errorhtml = "";
	$errors = 0;
	
	foreach ($htmlerrors as $key => $val) {
		foreach ($val as $tkey => $type) {
			$x = 0;
			foreach ($type as $ti => $types) {
				if ($ti == "img")
					$ti = "Image";
				else
					$ti = "Link";
				foreach ($types as $error) {
					$errorhtml .= '<li><section class="integrity_errors">Broken '.$ti.': '.$error.' &mdash; <a href="'.$admin_root.'pages/edit/'.$id.'/" target="_blank">'.$page["nav_title"].'</a></section></li>';
					$x++;
					$errors++;
				}
			}
		}
	}
	
	echo $errorhtml;
?>