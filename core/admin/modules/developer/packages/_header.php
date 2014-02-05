<?
	BigTree::globalizeArray($_SESSION["bigtree_admin"]["developer"]["package"],"strip_tags","htmlspecialchars");
	$available_licenses = array(
		"Closed Source" => array(
			"Free For Personal Use" => "",
			"Proprietary" => "",
		),
		"Open Source" => array(
			"LGPL v2.1" => "http://opensource.org/licenses/LGPL-2.1",
			"LGPL v3" => "http://opensource.org/licenses/LGPL-3.0",
			"GPL v2" => "http://opensource.org/licenses/GPL-2.0",
			"GPL v3" => "http://opensource.org/licenses/GPL-3.0",
			"MIT" => "http://opensource.org/licenses/MIT",
			"BSD 2-Clause" => "http://opensource.org/licenses/BSD-2-Clause",
			"BSD 3-Clause" => "http://opensource.org/licenses/BSD-3-Clause",
			"Apache 2.0" => "http://opensource.org/licenses/Apache-2.0",
			"MPL 2.0" => "http://opensource.org/licenses/MPL-2.0",
		)
	);
?>