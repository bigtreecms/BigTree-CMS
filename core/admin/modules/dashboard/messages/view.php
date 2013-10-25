<?
	// Get the message chain. It'll return false if the user isn't a sender/recipient.
	$chain = $admin->getMessageChain(end($bigtree["path"]));

	if (!$chain) {
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
?>
<div class="container message_thread">
	<?

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
	<section<? if ($m["selected"]) { ?> class="selected"<? } ?>>
		<header>
			<h3><?=$m["subject"]?></h3>
			<div class="from">
				<span class="gravatar"><img src="<?=BigTree::gravatar($sender_gravatar)?>" alt="" /></span>
				<p><?=$sender_name?></p>
			</div>
		</header>
		<article>
			<?=$m["message"]?>
		</article>
	</section>
	<?
		}
	?>
</div>
<script>
	$(".message_thread header").click(function() {
		$(this).parents("section").toggleClass("selected");
	});
</script>