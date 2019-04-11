<?php
	/*
		Class: BigTree\Message
			Provides an interface for handling BigTree messages.
	*/
	
	namespace BigTree;
	
	/**
	 * @property-read array $Chain
	 */
	
	class Message extends BaseObject
	{
		
		protected $ID;
		
		public $Date;
		public $Message;
		public $Recipients;
		public $ReadBy;
		public $ResponseTo;
		public $Selected = false;
		public $Sender;
		public $SenderEmail;
		public $SenderName;
		public $Subject;
		
		public static $Table = "bigtree_messages";
		
		/*
			Constructor:
				Builds a Message object referencing an existing database entry.

			Parameters:
				message - Either an ID (to pull a record) or an array (to use the array as the record)
		*/
		
		public function __construct($message = null)
		{
			if ($message !== null) {
				// Passing in just an ID
				if (!is_array($message)) {
					$message = SQL::fetch("SELECT * FROM bigtree_messages WHERE id = ?", $message);
				}
				
				// Bad data set
				if (!is_array($message)) {
					trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
				} else {
					$this->ID = $message["id"];
					
					$this->Date = $message["date"];
					$this->Message = $message["message"];
					$this->Recipients = array_filter((array) json_decode($message["recipients"], true));
					$this->ReadBy = array_filter((array) json_decode($message["read_by"], true));
					$this->ResponseTo = $message["response_to"];
					$this->Sender = $message["sender"];
					$this->SenderEmail = $message["sender_email"] ?: null;
					$this->SenderName = $message["sender_name"] ?: null;
					$this->Subject = $message["subject"];
				}
			} else {
				$this->ID = -1;
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
		
		public static function allByUser(string $user, bool $return_arrays = false): array
		{
			$sent = $read = $unread = [];
			$user = SQL::escape($user);
			$messages = SQL::fetchAll("SELECT bigtree_messages.*, 
											  bigtree_users.name AS sender_name, 
											  bigtree_users.email AS sender_email 
									   FROM bigtree_messages JOIN bigtree_users 
									   ON bigtree_messages.sender = bigtree_users.id 
									   WHERE sender = '$user' OR recipients LIKE '%\\\"$user\\\"%'
									   ORDER BY date DESC");
			
			foreach ($messages as $message_data) {
				$message = new Message($message_data);
				$message->SenderEmail = $message_data["sender_email"];
				$message->SenderName = $message_data["sender_name"];
				
				// If we're the sender put it in the sent array.
				if ($message_data["sender"] == $user) {
					$sent[] = $return_arrays ? $message->Array : $message;
				} else {
					// If we've been marked read, put it in the read array.
					if (in_array($user, $message->ReadBy)) {
						$read[] = $return_arrays ? $message->Array : $message;
					} else {
						$unread[] = $return_arrays ? $message->Array : $message;
					}
				}
			}
			
			return ["sent" => $sent, "read" => $read, "unread" => $unread];
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
		
		public static function create(string $sender, string $subject, string $message, array $recipients,
							   ?int $in_response_to = 0): Message
		{
			// Force recipients as a string value
			foreach ($recipients as $index => $recipient) {
				$recipients[$index] = strval(intval($recipient));
			}
			
			$id = SQL::insert("bigtree_messages", [
				"sender" => $sender,
				"recipients" => array_filter(array_unique($recipients)),
				"subject" => Text::htmlEncode(strip_tags($subject)),
				"message" => strip_tags($message, "<p><b><strong><em><i><a>"),
				"date" => "NOW()",
				"response_to" => $in_response_to
			]);
			
			return new Message($id);
		}
		
		/*
			Function: getChain
				Gets a full chain of messages based on this message.

			Returns:
				An array of Message objects with the current message's Selected property set to true.
		*/
		
		public function getChain(): array
		{
			// Show this message as special in the chain
			$message = clone $this;
			$message->Selected = true;
			$chain = [$message];
			
			// Find parents
			while ($message->ResponseTo) {
				$message = new Message($message->ResponseTo);
				
				// Prepend this message to the chain
				array_unshift($chain, $message);
			}
			
			// Find children
			$id = $this->ID;
			
			while ($id = SQL::fetchSingle("SELECT id FROM bigtree_messages WHERE response_to = ?", $id)) {
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
		
		public static function getUserUnreadCount(): ?int
		{
			$user = Auth::user()->ID;
			
			// Make sure a user is logged in
			if (is_null($user)) {
				trigger_error("Method getUserUnreadCount not available outside logged-in user context.");
				
				return null;
			}
			
			return SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_messages 
									 WHERE recipients LIKE '%\\\"$user\\\"%' AND read_by NOT LIKE '%\\\"$user\\\"%'");
		}
		
		/*
			Function: markRead
				Marks the message as read by the currently logged in user.
		*/
		
		public function markRead(): void
		{
			$user = Auth::user()->ID;
			
			// Make sure a user is logged in
			if (is_null($user)) {
				trigger_error("Method markRead not available outside logged-in user context.", E_USER_WARNING);
				
				return;
			}
			
			$this->ReadBy[] = strval($user); // Force string because we want it wrapped in quotes in JSON
			$this->save();
		}
		
		/*
		 	Function: save
				Saves the Message back to the database.
		*/
		
		public function save(): bool
		{
			$recipients = array_filter(array_unique($this->Recipients));
			$read_by = array_filter(array_unique($this->ReadBy));
			
			// Force recipients and read_by as strings
			foreach ($recipients as $index => $recipient) {
				$recipients[$index] = strval(intval($recipient));
			}
			
			foreach ($read_by as $index => $reader) {
				$read_by[$index] = strval(intval($reader));
			}
			
			$sender = intval($this->Sender);
			$subject = Text::htmlEncode(strip_tags($this->Subject));
			$message = strip_tags($this->Message, "<p><b><strong><em><i><a>");
			$response_to = intval($this->ResponseTo);
			
			if ($this->ID === -1) {
				SQL::insert(static::$Table, [
					"sender" => $sender,
					"recipients" => $recipients,
					"read_by" => $read_by,
					"subject" => $subject,
					"message" => $message,
					"response_to" => $response_to,
					"date" => "NOW()"
				]);
			} else {
				SQL::update(static::$Table, $this->ID, [
					"sender" => $sender,
					"recipients" => $recipients,
					"read_by" => $read_by,
					"subject" => $subject,
					"message" => $message,
					"response_to" => $response_to
				]);
			}
			
			return true;
		}
		
	}
