<?php
	// Get all the messages we've received.
	$messages = $admin->getMessages();
?>
<div class="table">
	<summary>
		<h2 class="full">
			<span class="unread"></span>
			Unread Messages
			<a href="<?=ADMIN_ROOT?>dashboard/messages/" class="more">View All Messages</a>
		</h2>
	</summary>
	<header>
		<span class="messages_from_to">From</span>
		<span class="messages_subject">Subject</span>
		<span class="messages_date_time">Date</span>
		<span class="messages_date_time">Time</span>
		<span class="messages_view">View</span>
	</header>
	<ul>
		<?php
			if (count($messages["unread"]) == 0) {
		?>
		<li><section class="no_content"><p>No unread messages</p></section></li>
		<?php
			} else {
				foreach ($messages["unread"] as $item) {
		?>
		<li>
			<section class="messages_from_to"><span class="gravatar"><img src="<?=BigTree::gravatar($item["sender_email"], 36)?>" alt="" /></span><?=$item["sender_name"]?></section>
			<section class="messages_subject"><?=$item["subject"]?></section>
			<section class="messages_date_time"><?=date("n/j/y",strtotime($item["date"]))?></section>
			<section class="messages_date_time"><?=date("g:ia",strtotime($item["date"]))?></section>
			<section class="messages_view"><a href="<?=ADMIN_ROOT?>dashboard/messages/view/<?=$item["id"]?>/" class="icon_message"></a></section>
		</li>
		<?php
				}
			}
		?>
	</ul>
</div>