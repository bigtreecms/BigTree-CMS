<?
	if (!count($_POST["send_to"]) || !$_POST["subject"] || !$_POST["message"]) {
		$_SESSION["saved_message"] = $_POST;
		header("Location: ../new/");
		die();
	}
	
	$admin->createMessage($_POST["subject"],$_POST["message"],$_POST["send_to"]);
	
	$admin->growl("Message Center","Sent Message");
	header("Location: ../");
	die();
?>