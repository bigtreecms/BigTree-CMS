<?
	$message_root = ADMIN_ROOT."dashboard/messages/";
	// Get all the messages we've sent or received.  We're going to paginate them in a hidden type fashion and just load them all at once.
	$messages = $admin->getMessages();
	$unread = $messages["unread"];
	$read = $messages["read"];
	$sent = $messages["sent"];
	
	$unread_pages = ceil(count($unread) / 5);
	$read_pages = ceil(count($read) / 5);
	$sent_pages = ceil(count($sent) / 5);
?>

<div class="table">
	<summary>
		<h2><span class="unread"></span>Unread Messages</h2>
		<? if (count($unread)) { ?>
		<nav id="unread_paging" class="view_paging"></nav>
		<? } ?>
	</summary>
	<header>
		<span class="messages_from_to">From</span>
		<span class="messages_subject">Subject</span>
		<span class="messages_date_time">Date</span>
		<span class="messages_date_time">Time</span>
		<span class="messages_view">View</span>
	</header>
	<ul>
		<?
			if (count($unread) == 0) {
		?>
		<li><section class="no_content">You have no unread messages.</section></li>
		<?	
			} else {
				$page = 0;
				$x = 0;
				foreach ($unread as $item) {
					if ($x == 5) {
						$page++;
						$x = 0;
					}
					$x++;
		?>
		<li class="page_<?=$page?>"<? if ($page > 0) { ?> style="display: none;"<? } ?>>
			<section class="messages_from_to"><span class="gravatar"><img src="<?=BigTree::gravatar($item["sender_email"], 36)?>" alt="" /></span><?=$item["sender_name"]?></section>
			<section class="messages_subject"><?=$item["subject"]?></section>
			<section class="messages_date_time"><?=date("n/j/y",strtotime($item["date"]))?></section>
			<section class="messages_date_time"><?=date("g:ia",strtotime($item["date"]))?></section>
			<section class="messages_view"><a href="<?=$message_root?>view/<?=$item["id"]?>/" class="icon_message"></a></section>
		</li>
		<?
				}
			}
		?>
	</ul>
</div>

<div class="table">
	<summary>
		<h2><span class="read"></span>Read Messages</h2>
		<? if (count($read)) { ?>
		<nav id="read_paging" class="view_paging"></nav>
		<? } ?>
	</summary>
	<header>
		<span class="messages_from_to">From</span>
		<span class="messages_subject">Subject</span>
		<span class="messages_date_time">Date</span>
		<span class="messages_date_time">Time</span>
		<span class="messages_view">View</span>
	</header>
	<ul>
		<?
			if (count($read) == 0) {
		?>
		<li><section class="no_content">You have no read messages.</section></li>
		<?	
			} else {
				$page = 0;
				$x = 0;
				foreach ($read as $item) {
					if ($x == 5) {
						$page++;
						$x = 0;
					}
					$x++;
		?>
		<li class="page_<?=$page?>"<? if ($page > 0) { ?> style="display: none;"<? } ?>>
			<section class="messages_from_to"><span class="gravatar"><img src="<?=BigTree::gravatar($item["sender_email"], 36)?>" alt="" /></span><?=$item["sender_name"]?></section>
			<section class="messages_subject"><?=$item["subject"]?></section>
			<section class="messages_date_time"><?=date("n/j/y",strtotime($item["date"]))?></section>
			<section class="messages_date_time"><?=date("g:ia",strtotime($item["date"]))?></section>
			<section class="messages_view"><a href="<?=$message_root?>view/<?=$item["id"]?>/" class="icon_message"></a></section>
		</li>
		<?
				}
			}
		?>
	</ul>
</div>

<div class="table">
	<summary>
		<h2><span class="sent"></span>Sent Messages</h2>
		<? if (count($sent)) { ?>
		<nav id="sent_paging" class="view_paging"></nav>
		<? } ?>
	</summary>
	<header>
		<span class="messages_from_to">To</span>
		<span class="messages_subject">Subject</span>
		<span class="messages_date_time">Date</span>
		<span class="messages_date_time">Time</span>
		<span class="messages_view">View</span>
	</header>
	<ul>
		<?
			if (count($sent) == 0) {
		?>
		<li><section class="no_content">You have no sent messages.</section></li>
		<?	
			} else {
				$page = 0;
				$x = 0;
				foreach ($sent as $item) {
					if ($x == 5) {
						$page++;
						$x = 0;
					}
					$x++;
					// Get the recipient names
					$recipients = explode("|",trim($item["recipients"],"|"));
					$r_names = array();
					foreach ($recipients as $r) {
						$u = $admin->getUser($r);
						$r_names[] = $u["name"];
					}
		?>
		<li class="page_<?=$page?>"<? if ($page > 0) { ?> style="display: none;"<? } ?>>
			<section class="messages_from_to"><?=implode(", ",$r_names)?></section>
			<section class="messages_subject"><?=$item["subject"]?></section>
			<section class="messages_date_time"><?=date("n/j/y",strtotime($item["date"]))?></section>
			<section class="messages_date_time"><?=date("g:ia",strtotime($item["date"]))?></section>
			<section class="messages_view"><a href="<?=$message_root?>view/<?=$item["id"]?>/" class="icon_message"></a></section>
		</li>
		<?
				}
			}
		?>
	</ul>
</div>
<script>
	BigTree.localPagesOfMessages = { unread_paging: <?=$unread_pages?>, read_paging: <?=$read_pages?>, sent_paging: <?=$sent_pages?> };
	
	BigTree.SetPageCount("#unread_paging",<?=$unread_pages?>,1);
	BigTree.SetPageCount("#read_paging",<?=$read_pages?>,1);
	BigTree.SetPageCount("#sent_paging",<?=$sent_pages?>,1);
	
	$(".table").on("click",".view_paging a",function() {
		page = parseInt($(this).attr("href").substr(1));
		$(this).parents("summary").siblings("ul").find("li").hide().filter(".page_" + page).show();
		$(this).parents("ul").find(".active").removeClass("active");
		
		id = $(this).parents("ul").attr("id");
		BigTree.SetPageCount("#" + id,BigTree.localPagesOfMessages[id],page);
		
		return false;
	});
</script>