<?php
	$newsModule = new TimberNews;
	$news = $newsModule->getRecent(5);

	if (count($news)) {
		$top_story = array_shift($news);
?>
<section class="news_callout">
	<div class="fs-row">
		<div class="fs-cell">
			<h2 class="news_callout_heading">
				<a href="<?=$newsModule->Link?>"><?=$callout["heading"]?></a>
			</h2>
		</div>
	</div>
	<div class="fs-row news_callout_row">
		<div class="fs-cell fs-lg-6 fs-xl-7">
			<article class="news_callout_item news_callout_item_top">
				<time class="news_callout_item_date" datetime=<?=date(DATE_W3C, strtotime($top_story["date"]))?>><?=date("F j, Y", strtotime($top_story["date"]))?></time>
				<h3 class="news_callout_item_heading">
					<a href="<?=$newsModule->Link?>story/<?=$top_story["route"]?>/">
						<?=$top_story["title"]?>
					</a>
				</h3>
				<?php
					if ($top_story["image"]) {
				?>
				<figure class="news_callout_item_image responsive_image">
					<picture class="news_callout_item_picture">
						<!--[if IE 9]><video style="display: none;"><![endif]-->
						<source media="(min-width: 1220px)" srcset="<?=BigTree::prefixFile($top_story["image"], "sml_")?>">
						<source media="(min-width: 980px)"  srcset="<?=BigTree::prefixFile($top_story["image"], "sqr_")?>">
						<source media="(min-width: 500px)"  srcset="<?=BigTree::prefixFile($top_story["image"], "sml_")?>">
						<!--[if IE 9]></video><![endif]-->
						<img src="<?=BigTree::prefixFile($top_story["image"], "sqr_")?>" alt="">
					</picture>
				</figure>
				<?php
					}
				?>
				<div class="news_callout_item_wrapper">
					<div class="news_callout_item_content">
						<p><?=BigTree::trimLength($top_story["blurb"], 200)?></p>
					</div>
					<a href="<?=$newsModule->Link?>story/<?=$top_story["route"]?>/" class="news_callout_item_link">Read More</a>
				</div>
			</article>
		</div>
		<div class="fs-cell-right fs-lg-5 fs-xl-4">
			<?php
				foreach ($news as $story) {
			?>
			<article class="news_callout_item">
				<time class="news_callout_item_date" datetime="<?=date(DATE_W3C, strtotime($story["date"]))?>"><?=date("F j, Y", strtotime($story["date"]))?></time>
				<h3 class="news_callout_item_heading">
					<a href="<?=$newsModule->Link?>story/<?=$story["route"]?>/">
						<?=$story["title"]?>
					</a>
				</h3>
			</article>
			<?php
				}
			?>
		</div>
	</div>
</section>
<?php
	}
?>