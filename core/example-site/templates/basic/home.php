<?php
	use BigTree\FileSystem;
	
	$treesMod = new DemoTrees;
	$trees = $treesMod->getRandom(5);
	
	$quotesMod = new DemoQuotes;
	list($quote) = $quotesMod->getApproved("RAND()", 1);
?>
<header class="image_header wallpapered" data-wallpaper-options='{"source":"<?=$cover_image?>"}'>
	<div class="positioner">
		<h1><?=$bigtree["page"]["nav_title"]?></h1>
		<p><?=$site_subtitle?></p>
	</div>
	<?php if ($cover_attribution && $cover_link) { ?>
	<a href="<?=$cover_link?>" class="attribution"<?=targetBlank($cover_link)?>>Photo By <?=$cover_attribution?></a>
	<?php } ?>
</header>
<div class="page home">
	<div class="row">
		<div class="mobile-full tablet-4 tablet-push-1 desktop-6 desktop-push-3">
			<blockquote>
				<p><?=$quote["quote"]?></p>
				<span class="author"><?=$quote["author"]?><?php if ($quote["source"]) echo ', <em>'.$quote["source"].'</em>'; ?></span>
			</blockquote>
			<hr />
		</div>
		<section class="mobile-full tablet-full desktop-8 desktop-push-2 post_list">
			<?php
				foreach (array_filter((array)$trees) as $tree) {
			?>
			<article class="post wallpapered" data-wallpaper-options='{"source":"<?=FileSystem::getPrefixedFile($tree["cover"], "large_")?>"}'>
				<a href="<?=$tree["detail_link"]?>">
					<div class="cover">
						<h2><?=$tree["title"]?></h2>
						<span class="button">Read About <?=$tree["title"]?></span>
					</div>
				</a>
			</article>
			<?php
				}
			?>
		</section>
		<?php include "../templates/layouts/_callouts.php" ?>
	</div>
</div>