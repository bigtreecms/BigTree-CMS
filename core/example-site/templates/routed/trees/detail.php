<?php
	use BigTree\FileSystem;
	
	if (isset($bigtree["commands"][0])) {
		$tree = $treesMod->getByRoute($bigtree["commands"][0]);
	}
	
	if (!$tree) {
		$cms->catch404();
	}
	
	$bigtree["page"]["title"] = $tree["title"]."&nbsp;&middot;&nbsp;".$bigtree["page"]["title"];
	
	$nextTree = $treesMod->getNext($tree);
	$previousTree = $treesMod->getPrevious($tree);
?>
<header class="image_header wallpapered" data-wallpaper-options='{"source":"<?=$tree["cover"]?>"}'>
	<div class="positioner">
		<h1><?=$tree["title"]?></h1>
		<p><?=$tree["subtitle"]?></p>
	</div>
	<?php if ($tree["cover_attribution"] && $tree["cover_link"]) { ?>
	<a href="<?=$tree["cover_link"]?>" class="attribution"<?=targetBlank($tree["cover_link"])?>>Photo By <?=$tree["cover_attribution"]?></a>
	<?php } ?>
</header>
<div class="page">
	<div class="row">
		<div class="mobile-full tablet-4 tablet-push-1 desktop-6 desktop-push-3 content">
			<?=$tree["content"]?>
		</div>
		<?php if (count($tree["gallery"])) { ?>
		<div class="gallery clear">
			<?php foreach ($tree["gallery"] as $photo) { ?>
			<figure class="mobile-half tablet-fourth desktop-fourth thumbnail">
				<a href="<?=$photo["image"]?>" class="lightbox" rel="gallery" data-attribution="<?=$photo["attribution"]?>" data-link="<?=$photo["link"]?>">
					<img src="<?=FileSystem::getPrefixedFile($photo["image"], "thumb_")?>" />
					<div class="cover">Explore</div>
				</a>
			</figure>
			<?php } ?>
		</div>
		<?php } ?>
		<div class="mobile-full tablet-4 tablet-push-1 desktop-6 desktop-push-3">
			<nav class="pagination clearfix">
				<hr />
				<?php 
					if ($nextTree) {
				?>
				<a href="<?=$nextTree["detail_link"]?>" class="arrow next">
					<h4>Next</h4>
					<h3><?=$nextTree["title"]?></h3>
				</a>
				<?php
					}
					if ($previousTree) {
				?>
				<a href="<?=$previousTree["detail_link"]?>" class="arrow previous">
					<h4>Previous</h4>
					<h3><?=$previousTree["title"]?></h3>
				</a>
				<?php
					}
				?>
			</nav>
		</div>
	</div>
</div>