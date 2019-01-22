<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	// Get the message chain. It'll return false if the user isn't a sender/recipient.
	$parent_message = new Message(end($bigtree["path"]));

	if (!$parent_message->ID) {
		Auth::stop("This message either does not exist or you do not have permission to view it.",
					 Router::getIncludePath("admin/layouts/_error.php"));
	}
?>
<div class="container message_thread">
	<?php
		// Mark the message read by you.
		foreach ($parent_message->Chain as $message) {
			if ($message->Selected) {
				$message->markRead();
			}

			// Get the sender's name
			$user = new User($message->Sender);
			$sender_name = $user->Name;
			$sender_gravatar = $user->Email;
	?>
	<section<?php if ($message->Selected) { ?> class="selected"<?php } ?>>
		<header>
			<h3><?=$message->Subject?></h3>
			<div class="from">
				<span class="gravatar"><img src="<?=User::gravatar($sender_gravatar)?>" alt="" /></span>
				<p><?=$sender_name?></p>
			</div>
		</header>
		<article>
			<?=$message->Message?>
		</article>
	</section>
	<?php
		}
	?>
</div>

<script>
	$(".message_thread header").click(function() {
		$(this).parents("section").toggleClass("selected");
	});
</script>