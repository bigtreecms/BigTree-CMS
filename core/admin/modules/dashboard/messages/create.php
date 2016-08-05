<?php
	namespace BigTree;
	
	if (!count($_POST["send_to"]) || !$_POST["subject"] || !$_POST["message"]) {
		$_SESSION["saved_message"] = $_POST;
		Router::redirect(ADMIN_ROOT."dashboard/messages/new/");
	}
	
	Message::create(Auth::user()->ID, $_POST["subject"], $_POST["message"], $_POST["send_to"]);
	Utils::growl("Message Center", "Sent Message");
	Router::redirect(ADMIN_ROOT."dashboard/messages/");
	