<?
	$breadcrumb[] = array("title" => "Modules", "link" => "developer/modules/");
	$breadcrumb[] = array("title" => "Designer", "link" => "developer/modules/designer/");
	$section_root = $developer_root."modules/";
	
	if (is_array($_SESSION["developer"]["saved_module"])) {
		foreach ($_SESSION["developer"]["saved_module"] as $key => $val) {
			if (substr($key,0,1) != "_") {
				$$key = $val;
			}
			unset($_SESSION["developer"]["saved_module"][$key]);
		}
	}
	
	$e = $_SESSION["developer"]["designer_errors"];
	unset($_SESSION["developer"]["designer_errors"]);
?>