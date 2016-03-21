<?php
	/*
		Class: BigTree\Message
			Provides an interface for handling BigTree messages.
	*/

	namespace BigTree;

	use BigTree;
	use BigTreeCMS;

	class Message extends BaseObject {

		protected $ID;

		public $Date;
		public $Message;
		public $Recipients;
		public $ReadBy;
		public $ResponseTo;
		public $Sender;
		public $SenderEmail;
		public $SenderName;
		public $Subject;

		/*
			Constructor:
				Builds a Message object referencing an existing database entry.

			Parameters:
				message - Either an ID (to pull a record) or an array (to use the array as the record)
		*/

		function __construct($message) {
			// Passing in just an ID
			if (!is_array($message)) {
				$message = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_messages WHERE id = ?", $message);
			}

			// Bad data set
			if (!is_array($message)) {
				trigger_error("Invalid ID or data set passed to constructor.", E_WARNING);
			} else {
				$this->ID = $message["id"];

				$this->Date = $message["date"];
				$this->Message = $message["message"];
				$this->Recipients = explode("||",trim($message["recipients"],"|"));
				$this->ReadBy = explode("||",trim($message["read_by"],"|"));
				$this->ResponseTo = $message["response_to"];
				$this->Sender = $message["sender"];
				$this->SenderEmail = $message["sender_email"] ?: null;
				$this->SenderName = $message["sender_name"] ?: null;
				$this->Subject = $message["subject"];
			}
		}

		/*
			Function: allByUser
				Returns all a user's messages.

			Parameters:
				user - User ID to retrieve messages for
				return_arrays - Set to true to return arrays of data rather than objects.

			Returns:
				An array containing "sent", "read", and "unread" keys that contain an array of messages each.
		*/

		static function allByUser($user = false,$return_arrays = false) {
			$sent = $read = $unread = array();

			$user = BigTreeCMS::$DB->escape($user);
			$messages = BigTreeCMS::$DB->fetchAll("SELECT bigtree_messages.*, 
														  bigtree_users.name AS sender_name, 
														  bigtree_users.email AS sender_email 
												   FROM bigtree_messages JOIN bigtree_users 
												   ON bigtree_messages.sender = bigtree_users.id 
												   WHERE sender = '$user' OR recipients LIKE '%|$user|%' 
												   ORDER BY date DESC");

			foreach ($messages as $message) {
				// If we're the sender put it in the sent array.
				if ($message["sender"] == $user) {
					$sent[] = $return_arrays ? $message : new Message($message);
				} else {
					// If we've been marked read, put it in the read array.
					if ($message["read_by"] && strpos($message["read_by"],"|".$user."|") !== false) {
						$read[] = $return_arrays ? $message : new Message($message);
					} else {
						$unread[] = $return_arrays ? $message : new Message($message);
					}
				}
			}

			return array("sent" => $sent, "read" => $read, "unread" => $unread);
		}

		/*
			Function: create
				Creates a message in message center.

			Parameters:
				sender - The user sending the message.
				subject - The subject line.
				message - The message.
				recipients - The recipients.
				in_response_to - The message being replied to.

			Returns:
				A Message object.
		*/

		static function create($sender,$subject,$message,$recipients,$in_response_to = 0) {
			// We build the send_to field this way so that we don't have to create a second table of recipients.
			$send_to = "|";
			foreach ($recipients as $r) {
				// Make sure they actually put in a number and didn't try to screw with the $_POST
				$send_to .= intval($r)."|";
			}

			// Insert the message
			$id = BigTreeCMS::$DB->insert("bigtree_messages",array(
				"sender" => $sender,
				"recipients" => $send_to,
				"subject" => BigTree::safeEncode(strip_tags($subject)),
				"message" => strip_tags($message,"<p><b><strong><em><i><a>"),
				"date" => "NOW()",
				"in_response_to" => $in_response_to
			));

			return new Message($id);
		}

		/*
			Function: getChain
				Gets a full chain of messages based on this message.

			Returns:
				An array of Message objects with the current message's Selected property set to true.
		*/

		function getChain() {
			// Show this message as special in the chain
			$message = clone $this;
			$message->Selected = true;

			$chain = array($message);

			// Find parents
			while ($message->ResponseTo) {
				$message = new Message($message->ResponseTo);

				// Prepend this message to the chain
				array_unshift($chain,$message);
			}

			// Find children
			$id = $this->ID;
			while ($id = BigTreeCMS::$DB->fetchSingle("SELECT id FROM bigtree_messages WHERE response_to = ?", $id)) {
				$chain[] = new Message($id);
			}

			return $chain;
		}

		/*
			Function: getUserUnreadCount
				Returns the number of unread messages for the logged in user.

			Returns:
				The number of unread messages.
		*/

		static function getUserUnreadCount() {
			global $admin;

			// Make sure a user is logged in
			if (get_class($admin) != "BigTreeAdmin" || !$admin->ID) {
				trigger_error("Method getUserUnreadCount not available outside logged-in user context.");
				return false;
			}

			return BigTreeCMS::$DB->fetchSingle("SELECT COUNT(*) FROM bigtree_messages 
												 WHERE recipients LIKE '%|".$admin->ID."|%' AND read_by NOT LIKE '%|".$admin->ID."|%'");
		}

		/*
			Function: markRead
				Marks the message as read by the currently logged in user.
		*/

		function markRead() {
			global $admin;

			// Make sure a user is logged in
			if (get_class($admin) != "BigTreeAdmin" || !$admin->ID) {
				trigger_error("Method markRead not available outside logged-in user context.");
				return false;
			}

			$this->ReadBy = str_replace("|".$admin->ID."|","",$this->ReadBy)."|".$admin->ID."|";
			$this->save();
		}
	}
