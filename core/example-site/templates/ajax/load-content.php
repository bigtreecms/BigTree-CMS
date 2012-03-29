<?
	include "../templates/routed/wonders/_header.php";
	
	$type = $_GET["type"];
	$wonder = $_GET["wonder"];
	
	$currentWonder = $wondersMod->get($wonder);
	
	if ($type == "wikipedia") {
		include "../templates/routed/wonders/_content/wikipedia.php";
	}
	if ($type == "instagram") {
		include "../templates/routed/wonders/_content/instagram.php";
	}
	if ($type == "twitter") {
		include "../templates/routed/wonders/_content/twitter.php";
	}
	if ($type == "youtube") {
		include "../templates/routed/wonders/_content/youtube.php";
	}
?>