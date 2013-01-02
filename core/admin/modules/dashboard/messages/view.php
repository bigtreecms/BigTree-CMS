<?
	// Get the message. It'll return false if the user isn't a sender/recipient.
	$message = $admin->getMessage(end($bigtree["path"]));

	if (!$message) {
?>
<div class="container">
	<section>
		<h3>Error</h3>
		<p>This message either does not exist or you do not have permission to view it.</p>
	</section>
</div>
<?
		$admin->stop();
	}

	// Mark the message read by you.
	$admin->markMessageRead($message["id"]);
	
	// Get the list of recipients to determine the names and also to tell _nav whether to show "Reply All"
	$recipients = explode("|",trim($message["recipients"],"|"));
	$recipient_names = array();
	$recipient_gravatar = false;
	foreach ($recipients as $r) {
		$u = $admin->getUser($r);
		$recipient_names[] = $u["name"];
		if ($r == $admin->ID) {
			$recipient_gravatar = $u["email"];
		}
	}
	if (!$recipient_gravatar) {
		$u = $admin->getUser($recipients[0]);
		$recipient_gravatar = $u["email"];
	}
	
	// Get the sender's name
	$u = $admin->getUser($message["sender"]);
	$sender_name = $u["name"];
	$sender_gravatar = $u["email"];
?>
<div class="container">
	<summary>
		<h2><span class="unread"></span> <?=$message["subject"]?></h2>
	</summary>
	<section>
		<div class="alert">
			<article class="message_from">
				<span class="gravatar">
					<img src="<?=BigTree::gravatar($sender_gravatar)?>" alt="" />
				</span>
				<label>From</label>
				<p><?=$sender_name?></p>
			</article>
			<article class="message_to">
				<span class="gravatar">
					<img src="<?=BigTree::gravatar($recipient_gravatar)?>" alt="" />
				</span>
				<label>To</label>
				<p><?=implode(", ",$recipient_names)?></p>
			</article>
		</div>
		<?=$message["message"]?>
	</section>
</div>