<?
	if (!count($_POST["send_to"]) || !$_POST["subject"] || !$_POST["message"]) {
		$_SESSION["saved_message"] = $_POST;
		BigTree::redirect("../new/");
	}
	
	$admin->createMessage($_POST["subject"],$_POST["message"],$_POST["send_to"]);
	
	$admin->growl("Message Center","Sent Message");
	BigTree::redirect("../");
?>