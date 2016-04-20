<?php
	namespace BigTree;

	$messages = $admin->getMessages($admin->ID);	

	// Going to be querying a lot of user names
	$user_cache = array();

	// Sent messages table data
	$sent_data = array();
	foreach ($messages["sent"] as $message) {
		$recipients = explode("|",trim($message["recipients"],"|"));
		$recipient_names = array();
		foreach ($recipients as $recipient) {
			if (!isset($user_cache[$recipient])) {
				$user_cache[$recipient] = $admin->getUser($recipient);
			}
			$recipient_names[] = $user_cache[$recipient]["name"];
		}

		$sent_data[] = array(
			"id" => $message["id"],
			"to" => implode(", ",$recipient_names),
			"subject" => $message["subject"],
			"date" => date("n/j/y",strtotime($message["date"])),
			"time" => date("g:ia",strtotime($message["date"]))
		);
	}

	// Unread messages table data 
	$unread_data = array();
	foreach ($messages["unread"] as $message) {
		$unread_data[] = array(
			"id" => $message["id"],
			"from" => '<span class="gravatar"><img src="'.BigTree::gravatar($message["sender_email"], 36).'" alt="" /></span>'.$message["sender_name"],
			"subject" => $message["subject"],
			"date" => date("n/j/y",strtotime($message["date"])),
			"time" => date("g:ia",strtotime($message["date"]))
		);
	}

	// Read messages table data 
	$read_data = array();
	foreach ($messages["read"] as $message) {
		$read_data[] = array(
			"id" => $message["id"],
			"from" => '<span class="gravatar"><img src="'.BigTree::gravatar($message["sender_email"], 36).'" alt="" /></span>'.$message["sender_name"],
			"subject" => $message["subject"],
			"date" => date("n/j/y",strtotime($message["date"])),
			"time" => date("g:ia",strtotime($message["date"]))
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

<script>
	BigTree.localPagesOfMessages = { unread_paging: <?=$unread_pages?>, read_paging: <?=$read_pages?>, sent_paging: <?=$sent_pages?> };
	
	BigTree.setPageCount("#unread_paging",<?=$unread_pages?>,1);
	BigTree.setPageCount("#read_paging",<?=$read_pages?>,1);
	BigTree.setPageCount("#sent_paging",<?=$sent_pages?>,1);
	
	$(".table").on("click",".view_paging a",function() {
		var page = parseInt($(this).attr("href").substr(1));
		$(this).parents("summary").siblings("ul").find("li").hide().filter(".page_" + page).show();
		$(this).parents("ul").find(".active").removeClass("active");
		
		var id = $(this).parents("ul").attr("id");
		BigTree.setPageCount("#" + id,BigTree.localPagesOfMessages[id],page);
		
		return false;
	});
</script>