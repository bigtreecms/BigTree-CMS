<?php
	/*
		Class: BigTree\EmailService\SMTP
			Implements a BigTree email service via SMTP.
	*/
	
	namespace BigTree\EmailService;
	
	use BigTree\Email;
	use PHPMailer;
	
	class SMTP extends Provider {
		
		// Implements Provider::send
		function send(Email $email): ?bool {
			$mailer = new PHPMailer;

			$mailer->isSMTP();
			$mailer->Host = $this->Settings["smtp_host"];
			$mailer->Port = $this->Settings["smtp_port"] ?: 25;
			$mailer->SMTPSecure = $this->Settings["smtp_security"] ?: null;

			if (!empty($this->Settings["smtp_user"])) {
				$mailer->SMTPAuth = true;
				$mailer->Username = $this->Settings["smtp_user"];
				$mailer->Password = $this->Settings["smtp_password"];
			} else {
				$mailer->SMTPAuth = false;
			}
			
			foreach ($email->Headers as $key => $val) {
				$mailer->addCustomHeader($key, $val);
			}
			
			$mailer->Subject = $email->Subject;
			
			if ($email->HTML) {
				$mailer->isHTML(true);
				$mailer->Body = $email->HTML;
				$mailer->AltBody = $email->Text;
			} else {
				$mailer->Body = $email->Text;
			}
			
			list($from_email, $from_name) = $this->parseAddress($email->From);
			
			$mailer->From = $from_email;
			$mailer->FromName = $from_name;
			
			list($reply_email, $reply_name) = $this->parseAddress($email->ReplyTo, false);
			
			if ($reply_email) {
				$mailer->addReplyTo($reply_email, $reply_name);
			}
			
			if ($email->CC) {
				if (is_array($email->CC)) {
					foreach ($email->CC as $item) {
						$mailer->addCC($item);
					}
				} else {
					$mailer->addCC($email->CC);
				}
			}
			
			if ($email->BCC) {
				if (is_array($email->BCC)) {
					foreach ($email->BCC as $item) {
						$mailer->addBCC($item);
					}
				} else {
					$mailer->addBCC($email->BCC);
				}
			}
			
			if (is_array($email->To)) {
				foreach ($email->To as $item) {
					$mailer->addAddress($item);
				}
			} else {
				$mailer->addAddress($email->To);
			}
			
			return $mailer->send();
		}
		
	}