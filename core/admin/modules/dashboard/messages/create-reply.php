<?php
	namespace BigTree;
	
	if (!count($_POST["send_to"]) || !$_POST["subject"] || !$_POST["message"]) {
		$_SESSION["saved_message"] = $_POST;
		if (strpos("reply-all", $_SERVER["HTTP_REFERER"])) {
			Router::redirect(ADMIN_ROOT."dashboard/messages/reply-all/".$_POST["response_to"]."/");
		} else {
			Router::redirect(ADMIN_ROOT."dashboard/messages/reply/".$_POST["response_to"]."/");
		}
	}
	
	// Make sure the user has the right to respond to this message
	$message = new Message($_POST["response_to"]);
	
	if ($message->Sender == $this->ID || in_array(Auth::user()->ID, $message->Recipients)) {
		Message::create(Auth::user()->ID, $_POST["subject"], $_POST["message"], $_POST["send_to"], $_POST["response_to"]);
		Utils::growl("Message Center", "Replied To Message");
		Router::redirect(ADMIN_ROOT."dashboard/messages/");
	}
