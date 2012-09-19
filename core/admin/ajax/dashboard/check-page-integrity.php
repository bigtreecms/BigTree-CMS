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
	
	foreach ($htmlerrors as $key => $val) {
		foreach ($val as $type => $errors) {
			if ($type == "img") {
				$ti = "Image";
			} else {
				$ti = "Link";
			}
			foreach ($errors as $error) {
				echo '<li><section class="integrity_errors"><span class="icon_small icon_small_warning"></span> Broken '.$ti.': '.$error.' on page &ldquo;'.$page["nav_title"].'&rdquo; &mdash; <a href="'.ADMIN_ROOT.'pages/edit/'.$id.'/" target="_blank">Click Here To Edit</a></section></li>';
			}
		}
	}
?>