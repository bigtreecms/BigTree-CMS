<?php
	/*
		Class: BigTree\EmailService\Mandrill
			Implements a BigTree email service for MailChimp/Mandrill (http://www.mailchimp.com/)
	*/
	
	namespace BigTree\EmailService;

	use BigTree\cURL;
	use BigTree\Email;

	class Mandrill extends Provider {

		// Implements Provider::send
		function send(Email $email) {
			// Get formatted name/email
			list($from_email,$from_name) = $this->parseAddress($email->From);

			// Get formatted reply-to
			list($reply_to,$reply_email) = $this->parseAddress($email->ReplyTo,false);

			// Generate array of people to send to
			$to_array = array();
			if (is_string($email->To)) {
				$to_array[] = array("email" => $email->To, "type" => "to");
			} else {
				foreach ($email->To as $address) {
					$to_array[] = array("email" => $address, "type" => "to");
				}
			}

			// Add CC and BCC
			if (is_string($email->CC) && $email->CC) {
				$to_array[] = array("email" => $email->CC, "type" => "cc");
			} else {
				foreach ($email->CC as $address) {
					$to_array[] = array("email" => $address, "type" => "cc");
				}
			}

			if (is_string($email->BCC) && $email->BCC) {
				$to_array[] = array("email" => $email->BCC, "type" => "bcc");
			} else {
				foreach ($email->BCC as $address) {
					$to_array[] = array("email" => $address, "type" => "bcc");
				}
			}

			// Set reply header if passed in
			$headers = array();
			if ($reply_to) {
				$headers["Reply-To"] = $reply_to;
			}

			foreach ($email->Headers as $key => $value) {
				$headers[$key] = $value;
			}

			$response = json_decode(cURL::request("https://mandrillapp.com/api/1.0/messages/send.json",json_encode(array(
				"key" => $this->Settings["mandrill_key"],
				"message" => array(
					"html" => $body,
					"text" => $text,
					"subject" => $subject,
					"from_email" => $from_email,
					"from_name" => $from_name,
					"to" => $to_array,
					"headers" => $headers,
					"inline_css" => true
				)
			))),true);

			if ($response["status"] == "error" || $response["status"] == "invalid") {
				$this->Error = $response["message"];
				
				return false;
			}

			return true;
		}
	}