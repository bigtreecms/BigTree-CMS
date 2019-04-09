<?php
	namespace BigTree;

	$messages = Message::allByUser(Auth::user()->ID, true);

	// Going to be querying a lot of user names
	$user_cache = array();

	// Sent messages table data
	$sent_data = array();
	
	foreach ($messages["sent"] as $message) {
		$recipient_names = array();
		
		foreach ($message["recipients"] as $recipient) {
			if (!isset($user_cache[$recipient])) {
				if (User::exists($recipient)) {
					$recipient_user = new User($recipient);
					$user_cache[$recipient] = $recipient_user->Array;
				} else {
					$user_cache[$recipient] = null;
				}
			}
			
			$recipient_names[] = $user_cache[$recipient] ? $user_cache[$recipient]["name"] : null;
		}

		$sent_data[] = array(
			"id" => $message["id"],
			"to" => implode(", ",$recipient_names),
			"subject" => $message["subject"],
			"date" => Auth::user()->convertTimestampTo($message["date"], "n/j/y"),
			"time" => Auth::user()->convertTimestampTo($message["date"], "g:ia")
		);
	}

	// Unread messages table data 
	$unread_data = array();
	
	foreach ($messages["unread"] as $message) {
		$unread_data[] = array(
			"id" => $message["id"],
			"from" => '<span class="gravatar"><img src="'.User::gravatar($message["sender_email"], 36).'" alt="" /></span>'.$message["sender_name"],
			"subject" => $message["subject"],
			"date" => Auth::user()->convertTimestampTo($message["date"], "n/j/y"),
			"time" => Auth::user()->convertTimestampTo($message["date"], "g:ia")
		);
	}

	// Read messages table data 
	$read_data = array();
	
	foreach ($messages["read"] as $message) {
		$read_data[] = array(
			"id" => $message["id"],
			"from" => '<span class="gravatar"><img src="'.User::gravatar($message["sender_email"], 36).'" alt="" /></span>'.$message["sender_name"],
			"subject" => $message["subject"],
			"date" => Auth::user()->convertTimestampTo($message["date"], "n/j/y"),
			"time" => Auth::user()->convertTimestampTo($message["date"], "g:ia")
		);
	}
?>
<div id="unread_messages_table"></div>
<div id="read_messages_table"></div>
<div id="sent_messages_table"></div>

<script>
	// Unread Messages
	BigTreeTable({
		container: "#unread_messages_table",
		title: "<?=Text::translate("Unread Messages")?>",
		icon: "unread",
		noContentMessage: "<?=Text::translate("You have no unread messages.")?>",
		perPage: 5,
		searchable: true,
		sortable: true,
		columns: {
			from: { title: "<?=Text::translate("From")?>", size: 0.4 },
			subject: { title: "<?=Text::translate("Subject")?>", size: 0.6 },
			date: { title: "<?=Text::translate("Date")?>", size: 80 },
			time: { title: "<?=Text::translate("Time")?>", size: 80 }
		},
		actions: {
			view: "<?=ADMIN_ROOT?>dashboard/messages/view/{id}/"
		},
		data: <?=json_encode($unread_data)?>
	});

	// Read Messages
	BigTreeTable({
		container: "#read_messages_table",
		title: "<?=Text::translate("Read Messages")?>",
		icon: "read",
		noContentMessage: "<?=Text::translate("You have no read messages.")?>",
		perPage: 5,
		searchable: true,
		sortable: true,
		columns: {
			from: { title: "<?=Text::translate("From")?>", size: 0.4 },
			subject: { title: "<?=Text::translate("Subject")?>", size: 0.6 },
			date: { title: "<?=Text::translate("Date")?>", size: 80 },
			time: { title: "<?=Text::translate("Time")?>", size: 80 }
		},
		actions: {
			view: "<?=ADMIN_ROOT?>dashboard/messages/view/{id}/"
		},
		data: <?=json_encode($read_data)?>
	});

	// Sent Messages
	BigTreeTable({
		container: "#sent_messages_table",
		title: "<?=Text::translate("Sent Messages")?>",
		icon: "sent",
		noContentMessage: "<?=Text::translate("You have no sent messages.")?>",
		perPage: 5,
		searchable: true,
		sortable: true,
		columns: {
			to: { title: "<?=Text::translate("To")?>", size: 0.4 },
			subject: { title: "<?=Text::translate("Subject")?>", size: 0.6 },
			date: { title: "<?=Text::translate("Date")?>", size: 80 },
			time: { title: "<?=Text::translate("Time")?>", size: 80 }
		},
		actions: {
			view: "<?=ADMIN_ROOT?>dashboard/messages/view/{id}/"
		},
		data: <?=json_encode($sent_data)?>
	});
</script>