<?php
	namespace BigTree\EmailService;

	/*
		Class: BigTree\EmailService\MailGun
			Implements a BigTree email service for Mailgun (http://www.mailgun.com/)
	*/

	class MailGun extends Provider {

		/*
			Function: send
				Sends an HTML email.

			Parameters:
				subject - Email subject
				body - HTML email body
				to - Email address to send to (single address as a string or an array of email addresses)
				from_email - From email address (optional, defaults to no-reply@domain.com where domain.com is the domain of the server/site)
				from_name - From name (optional, defaults to BigTree CMS if from_email isn't set)
				reply_to - Reply-to email address (optional)
				text - Regular text body (optional)

			Returns:
				true if successful
				Sets $this->Error with error response if not successful.
		*/

		function send($subject,$body,$to,$from_email = false,$from_name = false,$reply_to = false,$text = "") {
			global $bigtree;

			// Build POST array
			$post = array(
				"from" => $from_name ? "$from_name <$from_email>" : $from_email,
				"to" => is_array($to) ? implode(",",$to) : $to,
				"subject" => $subject,
				"text" => $text,
				"html" => $body
			);

			// Add Reply-To header
			if ($reply_to) {
				$post["h:Reply-To"] = $reply_to;
			}

			// Mailgun doesn't give a nice easy to know error response so we have to check HTTP response codes
			$response = json_decode(cURL::request("https://api.mailgun.net/v2/".$this->Settings["mailgun_domain"]."/messages",$post,array(CURLOPT_USERPWD => "api:".$this->Settings["mailgun_key"])),true);
			if ($bigtree["last_curl_response_code"] == 200) {
				return true;
			} else {
				$this->Error = $response["message"];
				return false;
			}
		}
	}