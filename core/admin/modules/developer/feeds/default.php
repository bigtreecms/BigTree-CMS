<?
	$feeds = $admin->getFeeds();
?>
<div class="table">
	<summary><h2>Feeds</h2></summary>
	<header>
		<span class="developer_feeds_name">Feed Name</span>
		<span class="developer_feeds_url">URL</span>
		<span class="developer_feeds_type">Type</span>
		<span class="view_action" style="width: 80px;">Actions</span>
	</header>
	<ul>
		<? foreach ($feeds as $feed) { ?>
		<li>
			<section class="developer_feeds_name">
				<a href="<?=DEVELOPER_ROOT?>feeds/edit/<?=$feed["id"]?>/"><?=$feed["name"]?></a>
			</section>
			<section class="developer_feeds_url"><a href="<?=WWW_ROOT?>feeds/<?=$feed["route"]?>/" target="_blank"><?=WWW_ROOT?>feeds/<?=$feed["route"]?>/</a></section>
			<section class="developer_feeds_type"><? if ($feed["type"]) { echo $feed_types[$feed["type"]]; } else { echo "Custom"; } ?></section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>feeds/edit/<?=$feed["id"]?>/" class="icon_edit"></a>
			</section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>feeds/delete/<?=$feed["id"]?>/" class="icon_delete"></a>
			</section>
		</li>
		<? } ?>
	</ul>
</div>