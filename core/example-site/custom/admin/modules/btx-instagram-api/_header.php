<?
	include BigTree::path("inc/modules/btx-instagram-api.php");
	
	$mroot = $admin_root . "btx-instagram-api/";
	
	$btxInstagramAPI = new BTXInstagramAPI;
	
	$view["title"] = "Instagram API";
	$view["icon"] = "instagram";
	
	if ($module["group"]) {
		$mgroup = $admin->getModuleGroup($module["group"]);
		$other = $admin->getModulesByGroup($module["group"]);
		if (count($other) > 1) {
			$subnav = array();
			foreach ($other as $more) {
				$subnav[] = array("title" => $more["name"], "link" => $more["route"]."/");
			}
		}
	}
	
	$breadcrumb = array(
		array("link" => "modules/", "title" => "Modules"),
		array("link" => "modules/".$mgroup["route"], "title" => $mgroup["name"]),
		array("link" => "btx-instagram-api/", "title" => "Instagram API")
	);
	
	$actions = array(
		array(
			"name" => "API Settings",
			"class" => "server",
			"route" => ""
		)
	);
?>
<style>
	h1 span.instagram { background: url(<?=$admin_root?>images/modules/btx-instagram-api-icon.png) no-repeat center; height: 30px; margin: 1px 8px 0 0; width: 30px; }
	pre { background: #f6f6f6; border: 1px solid #ddd; border-radius: 5px; color: #333; font-size: 13px; line-height: 1.5; padding: 18px 20px 15px; margin: 10px 0 15px; }
	#instagram_api hr { clear: both; margin-top: 25px; margin-bottom: 25px; }
	#instagram_api h2 { float: none; }
</style>