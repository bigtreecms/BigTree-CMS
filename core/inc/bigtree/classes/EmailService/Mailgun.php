<?php
	/*
		Class: BigTree\EmailService\Mailgun
			Implements a BigTree email service for Mailgun (http://www.mailgun.com/)
	*/
	
	namespace BigTree\EmailService;
	
	use BigTree\cURL;
	use BigTree\Email;
	
	class Mailgun extends Provider {
		
		// Implements Provider::send
		function send(Email $email) {
			// Get formatted name/email
			list($from_email, $from_name) = $this->parseAddress($email->From);
			
			// Get formatted reply-to
			list($reply_to, $reply_name) = $this->parseAddress($email->ReplyTo, false);
			
			// Build POST array
			$post = array(
				"from" => $from_name ? "$from_name <$from_email>" : $from_email,
				"to" => is_array($email->To) ? implode(",", $email->To) : $email->To,
				"subject" => $email->Subject,
				"text" => $email->Text,
				"html" => $email->HTML
			);
			
			// Add Reply-To header
			if ($reply_to) {
				$post["h:Reply-To"] = $reply_to;
			}
			
			// Add optional BCC and CC
			if ($email->CC) {
				$post["cc"] = is_array($email->CC) ? implode(",", $email->CC) : $email->CC;
			}
			
			if ($email->BCC) {
				$post["bcc"] = is_array($email->BCC) ? implode(",", $email->BCC) : $email->BCC;
			}
			
			// Add optional headers
			foreach ($email->Headers as $key => $value) {
				$post["h:$key"] = $value;
			}
			
			// Mailgun doesn't give a nice easy to know error response so we have to check HTTP response codes
			$response = json_decode(cURL::request("https://api.mailgun.net/v2/".$this->Settings["mailgun_domain"]."/messages", $post, array(CURLOPT_USERPWD => "api:".$this->Settings["mailgun_key"])), true);
			
			if (cURL::$ResponseCode == 200) {
				return true;
			} else {
				$this->Error = $response["message"];
				
				return false;
			}
		}
	}