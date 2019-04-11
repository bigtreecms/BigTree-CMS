<?php
	/*
		Class: BigTree\EmailService\Mandrill
			Implements a BigTree email service for MailChimp/Mandrill (http://www.mailchimp.com/)
	*/
	
	namespace BigTree\EmailService;
	
	use BigTree\cURL;
	use BigTree\Email;
	
	class Mandrill extends Provider
	{
		
		// Implements Provider::send
		public function send(Email $email): ?bool
		{
			// Get formatted name/email
			list($from_email, $from_name) = $this->parseAddress($email->From);
			
			// Get formatted reply-to
			list($reply_to, $reply_name) = $this->parseAddress($email->ReplyTo, false);
			
			// Generate array of people to send to
			$to_array = [];
			
			if (is_array($email->To)) {
				foreach ($email->To as $address) {
					$to_array[] = ["email" => $address, "type" => "to"];
				}
			} else {
				$to_array[] = ["email" => $email->To, "type" => "to"];
			}
			
			// Add CC and BCC
			if (is_array($email->CC)) {
				foreach ($email->CC as $address) {
					$to_array[] = ["email" => $address, "type" => "cc"];
				}
			} elseif ($email->CC) {
				$to_array[] = ["email" => $email->CC, "type" => "cc"];
			}
			
			if (is_array($email->BCC)) {
				foreach ($email->BCC as $address) {
					$to_array[] = ["email" => $address, "type" => "bcc"];
				}
			} elseif ($email->BCC) {
				$to_array[] = ["email" => $email->BCC, "type" => "bcc"];
			}
			
			// Set reply header if passed in
			$headers = [];
			
			if ($reply_to) {
				$headers["Reply-To"] = $reply_to;
			}
			
			foreach ($email->Headers as $key => $value) {
				$headers[$key] = $value;
			}
			
			$data = [
				"key" => $this->Settings["mandrill_key"],
				"message" => [
					"html" => $email->HTML,
					"text" => $email->Text,
					"subject" => $email->Subject,
					"from_email" => $from_email,
					"from_name" => $from_name,
					"to" => $to_array,
					"headers" => $headers,
					"inline_css" => true
				]
			];
				
			$response = cURL::request("https://mandrillapp.com/api/1.0/messages/send.json", json_encode($data));
			$response = json_decode($response, true);
			
			if ($response["status"] == "error" || $response["status"] == "invalid") {
				$this->Error = $response["message"];
				
				return false;
			}
			
			return true;
		}
		
	}