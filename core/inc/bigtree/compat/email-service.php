<?php
	/*
		Class: BigTreeEmailService
			A common interface for sending email through various transactional email API providers.
	*/

	class BigTreeEmailService {
		var $Error = false;
		var $Service = "";

		/*
			Constructor:
				Sets up the currently configured service.
		*/

		function __construct() {
			$this->Email = new BigTree\Email;

			// In case someone needs to read this
			$this->Service = $this->Email->Service;
			$this->Settings = $this->Email->Settings;
		}

		/*
			Function: sendEmail
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

		function sendEmail($subject,$body,$to,$from_email = false,$from_name = false,$reply_to = false,$text = "") {
			$this->Email->Subject = $subject;
			$this->Email->HTML = $body;
			$this->Email->To = $to;
			$this->Email->From = $from_name ? "$from_name <$from_email>" : $from_email;
			$this->Email->ReplyTo = $reply_to;
			$this->Email->Text = $text;

			$response = $this->Email->send();

			if (!$response) {
				$this->Error = $this->Email->Error;
			}

			return $response;
		}
	}
