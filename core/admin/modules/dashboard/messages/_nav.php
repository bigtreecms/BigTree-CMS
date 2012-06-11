<nav class="sub">
	<a href="<?=ADMIN_ROOT?>dashboard/messages/"<? if (end($bigtree["path"]) == "messages") { ?> class="active"<? } ?>><span class="icon_small icon_small_list"></span>View Messages</a>
	<a href="<?=ADMIN_ROOT?>dashboard/messages/new/" <? if (end($bigtree["path"]) == "new") { ?> class="active"<? } ?>><span class="icon_small icon_small_add"></span>New Message</a>
	<?
		if (is_numeric(end($bigtree["path"]))) {
	?>
	<a href="<?=ADMIN_ROOT?>dashboard/messages/reply/<?=end($bigtree["path"])?>/"<? if ($bigtree["path"][count($bigtree["path"])-2] == "reply") { ?> class="active"<? } ?>><span class="icon_small icon_small_reply"></span>Reply</a>
	<?
			if (isset($recipients) && count($recipients) > 1) {
	?>
	<a href="<?=ADMIN_ROOT?>dashboard/messages/reply-all/<?=end($bigtree["path"])?>/"<? if ($bigtree["path"][count($bigtree["path"])-2] == "reply-all") { ?> class="active"<? } ?>><span class="icon_small icon_small_reply_all"></span>Reply All</a>
	<?
			}
		}
	?>
</nav>