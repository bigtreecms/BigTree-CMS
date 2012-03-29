<?
	$layout = "wonders";
	
	include "../templates/routed/wonders/_header.php";
	$currentWonder = $wondersMod->getCurrent();
	include "../templates/routed/wonders/_detail.php";
?>