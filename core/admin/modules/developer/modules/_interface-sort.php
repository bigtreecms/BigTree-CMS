<?php
	namespace BigTree;
	
	/**
	 * @global int $id
	 */
	
	Extension::initializeCache();
	$interfaces = ModuleInterface::allByModule($id, "title ASC", true);
	
	$interface_list = [
		"form" => ["name" => "Forms", "items" => []],
		"view" => ["name" => "Views", "items" => []],
		"report" => ["name" => "Reports", "items" => []]
	];
	
	// Sort interfaces into relevant sections
	foreach ($interfaces as $interface) {
		if (strpos($interface["type"], "*") === false) {
			if ($interface["type"] == "form") {
				$interface["title"] = "Add/Edit ".$interface["title"];
				$interface["edit_url"] = "forms/edit/".$interface["id"]."/";
			} elseif ($interface["type"] == "view") {
				// Views need special treatment for adding their style icon
				$settings = json_decode($interface["settings"], true);
				if ($settings["type"] != "images" && $settings["type"] != "images-grouped") {
					$interface["show_style"] = true;
				}
				
				$interface["title"] = "View ".$interface["title"];
				$interface["edit_url"] = "views/edit/".$interface["id"]."/";
			} elseif ($interface["type"] == "report") {
				$interface["edit_url"] = "reports/edit/".$interface["id"]."/";
			}
			$interface_list[$interface["type"]]["items"][] = $interface;
		} else {
			list($extension, $type) = explode("*", $interface["type"]);
			$interface["edit_url"] = "interfaces/build/$extension/$type/?id=".$interface["id"];
			
			if (isset($interface_list[$interface["type"]])) {
				$interface_list[$interface["type"]]["items"][] = $interface;
			} else {
				$interface_list[$interface["type"]] = [
					"name" => ModuleInterface::$Plugins[$extension][$type]["name"],
					"items" => [$interface]
				];
			}
		}
	}
	