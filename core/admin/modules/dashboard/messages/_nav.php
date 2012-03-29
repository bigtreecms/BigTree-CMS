<nav class="sub">
	<a href="<?=$admin_root?>dashboard/messages/"<? if (end($path) == "messages") { ?> class="active"<? } ?>><span class="icon_small icon_small_list"></span>View Messages</a>
	<a href="<?=$admin_root?>dashboard/messages/new/" <? if (end($path) == "new") { ?> class="active"<? } ?>><span class="icon_small icon_small_add"></span>New Message</a>
	<?
		if (is_numeric(end($path))) {
	?>
	<a href="<?=$admin_root?>dashboard/messages/reply/<?=end($path)?>/"<? if ($path[count($path)-2] == "reply") { ?> class="active"<? } ?>><span class="icon_small icon_small_reply"></span>Reply</a>
	<?
			if (count($recipients) > 1) {
	?>
	<a href="<?=$admin_root?>dashboard/messages/reply-all/<?=end($path)?>/"<? if ($path[count($path)-2] == "reply-all") { ?> class="active"<? } ?>><span class="icon_small icon_small_reply_all"></span>Reply All</a>
	<?
			}
		}
	?>
</nav>