<?
	$admin->processCrops(json_decode($_POST["crop_info"],true));
	BigTree::redirect($_POST["return_page"]);
?>