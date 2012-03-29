<?
	$feeds = $admin->getFeeds();
?>
<h1><span class="icon_developer_feeds"></span>Feeds</h1>
<? include BigTree::path("admin/modules/developer/feeds/_nav.php") ?>

<div class="table">
	<summary><h2>Feeds</h2></summary>
	<header>
		<span class="developer_feeds_name">Feed Name</span>
		<span class="developer_feeds_url">URL</span>
		<span class="developer_feeds_type">Type</span>
		<span class="view_action">Edit</span>
		<span class="view_action">Delete</span>
	</header>
	<ul>
		<? foreach ($feeds as $feed) { ?>
		<li>
			<section class="developer_feeds_name">
				<a href="<?=$section_root?>edit/<?=$feed["id"]?>/"><?=$feed["name"]?></a>
			</section>
			<section class="developer_feeds_url"><a href="<?=$www_root?>feeds/<?=$feed["route"]?>/" target="_blank"><?=$www_root?>feeds/<?=$feed["route"]?>/</a></section>
			<section class="developer_feeds_type"><? if ($feed["type"]) { echo $feed_types[$feed["type"]]; } else { echo "Custom"; } ?></section>
			<section class="view_action">
				<a href="<?=$section_root?>edit/<?=$feed["id"]?>/" class="icon_edit"></a>
			</section>
			<section class="view_action">
				<a href="<?=$section_root?>delete/<?=$feed["id"]?>/" class="icon_delete"></a>
			</section>
		</li>
		<? } ?>
	</ul>
</div>