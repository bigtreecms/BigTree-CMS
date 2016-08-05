<?php
	namespace BigTree;
	
	// Get all the messages we've received.
	$messages = Message::allByUser(Auth::user()->ID);

	// Unread messages table data 
	$unread_data = array();
	
	foreach ($messages["unread"] as $message) {
		$unread_data[] = array(
			"id" => $message->ID,
			"from" => '<span class="gravatar"><img src="'.Image::gravatar($message->SenderEmail, 36).'" alt="" /></span>'.$message->SenderName,
			"subject" => $message["subject"],
			"date" => date("n/j/y",strtotime($message["date"])),
			"time" => date("g:ia",strtotime($message["date"]))
		);
	}
?>
<div id="unread_messages_table"></div>
<script>
	// Unread Messages
	BigTreeTable({
		container: "#unread_messages_table",
		title: "<?=Text::translate("Unread Messages")?>",
		icon: "unread",
		button: { title: "<?=Text::translate("View All Messages")?>", link: "<?=ADMIN_ROOT?>dashboard/messages/" },
		noContentMessage: "<?=Text::translate("You have no unread messages.")?>",
		perPage: 5,
		searchable: true,
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
</script>