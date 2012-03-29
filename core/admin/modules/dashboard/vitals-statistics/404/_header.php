<?
	if (!$admin->Level) {
		die();
	}
	
	$breadcrumb = array(
		array("link" => "dashboard/", "title" => "Dashboard"),
		array("link" => "dashboard/vitals-statistics/", "title" => "Vitals &amp; Statistics"),
		array("link" => "dashboard/vitals-statistics/404/", "title" => "404 Report")
	);
?>