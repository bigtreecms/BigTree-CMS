<?php
	namespace BigTree;

	// Get the message chain. It'll return false if the user isn't a sender/recipient.
	$chain = $admin->getMessageChain(end($bigtree["path"]));

	if (!$chain) {
		$admin->stop("This message either does not exist or you do not have permission to view it.",
					 Router::getIncludePath("admin/layouts/_error.php"));
	}
?>
<div class="container message_thread">
	<?php
		// Mark the message read by you.
		foreach ($chain as $m) {
			if ($m["selected"]) {
				$admin->markMessageRead($m["id"]);
			}

			// Get the sender's name
			$u = $admin->getUser($m["sender"]);
			$sender_name = $u["name"];
			$sender_gravatar = $u["email"];
	?>
	<section<?php if ($m["selected"]) { ?> class="selected"<?php } ?>>
		<header>
			<h3><?=$m["subject"]?></h3>
			<div class="from">
				<span class="gravatar"><img src="<?=Image::gravatar($sender_gravatar)?>" alt="" /></span>
				<p><?=$sender_name?></p>
			</div>
		</header>
		<article>
			<?=$m["message"]?>
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