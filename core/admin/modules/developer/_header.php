<?
	$module_title = "Developer";
	$developer_root = $admin_root."developer/";
	$admin->requireLevel(2);
	$autoModule = new BigTreeAutoModule;
	
	$subnav = array(
		array("title" => "Templates", "link" => "developer/templates/"),
		array("title" => "Modules", "link" => "developer/modules/"),
		array("title" => "Callouts", "link" => "developer/callouts/"),
		array("title" => "Field Types", "link" => "developer/field-types/"),
		array("title" => "Feeds", "link" => "developer/feeds/"),
		array("title" => "Settings", "link" => "developer/settings/"),
		array("title" => "Upload Service", "link" => "developer/upload-service/"),
		array("title" => "Site Status", "link" => "developer/status/"),
	);
	
	$breadcrumb = array(
		array("title" => "Developer", "link" => "developer/")
	);
?>
<div class="developer">