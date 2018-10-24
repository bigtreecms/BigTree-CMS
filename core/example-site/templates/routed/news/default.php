<?php
	if (empty($bigtree["commands"][0])) {
		BigTree::redirect($news_link."1/");
	}

	$news_page = intval($bigtree["commands"][0]);
	$news_order = "date DESC";
	$news_where = "date <= NOW()";
	$news_perpage = 4;

	$news_page_count = $newsModule->getPageCount($news_perpage, $news_where);

	if ($news_page > $news_page_count) {
		$cms->catch404();
	}

	$news = $newsModule->getPage($news_page, $news_order, $news_perpage, $news_where);

	if ($news_page == 1) {
		$top_story = array_shift($news);
	}
?>
<div class="fs-row page_row">
	<div class="fs-cell fs-lg-8">
		<div class="typography">
			<h1>Recent News</h1>
		</div>
	</div>
	<div class="fs-cell-right fs-lg-4 fs-xl-3">
		<?php include "../templates/layouts/_subnav.php"; ?>
	</div>
	<div class="fs-cell fs-lg-8 page_content">
		<div class="typography">
			<?php
				if ($top_story) {
			?>
			<article class="news_article news_article_top">
				<?php
					if ($top_story["image"]) {
				?>
				<figure class="news_article_image responsive_image">
					<picture class="news_article_picture">
						<!--[if IE 9]><video style="display: none;"><![endif]-->
						<source media="(min-width: 500px)" srcset="<?=BigTree::prefixFile($top_story["image"], "sml_")?>">
						<!--[if IE 9]></video><![endif]-->
						<img src="<?=BigTree::prefixFile($top_story["image"], "sqr_")?>" alt="">
					</picture>
				</figure>
				<?php
					}
				?>
				<div class="news_article_wrapper">
					<time class="news_article_date" datetime="<?=date(DATE_W3C, strtotime($top_story["date"]))?>"><?=date("F j, Y", strtotime($top_story["date"]))?></time>
					<h2 class="news_article_heading">
						<a href="<?=$news_link?>story/<?=$top_story["route"]?>/"><?=$top_story["title"]?></a>
					</h2>
					<div class="news_article_content">
						<p><?=BigTree::trimLength($top_story["blurb"], 150)?></p>
					</div>
					<a href="<?=$news_link?>story/<?=$top_story["route"]?>/" class="news_article_link">Read More</a>
				</div>
			</article>
			<?php
				}

				foreach ($news as $story) {
			?>
			<article class="news_article">
				<?php
					if ($story["image"]) {
				?>
				<figure class="news_article_image responsive_image">
					<picture class="news_article_picture">
						<!--[if IE 9]><video style="display: none;"><![endif]-->
						<source media="(min-width: 500px)" srcset="<?=BigTree::prefixFile($story["image"], "sml_")?>">
						<source media="(min-width: 0px)"   srcset="<?=BigTree::prefixFile($story["image"], "sqr_")?>">
						<!--[if IE 9]></video><![endif]-->
						<img src="<?=BigTree::prefixFile($story["image"], "sqr_")?>" alt="">
					</picture>
				</figure>
				<?php
					}
				?>
				<div class="news_article_wrapper">
					<time class="news_article_date" datetime=<?=date(DATE_W3C, strtotime($story["date"]))?>><?=date("F j, Y", strtotime($story["date"]))?></time>
					<h2 class="news_article_heading">
						<a href="<?=$news_link?>story/<?=$story["route"]?>/"><?=$story["title"]?></a>
					</h2>
					<div class="news_article_content">
						<p><?=BigTree::trimLength($story["blurb"], 150)?></p>
					</div>
					<a href="<?=$news_link?>story/<?=$story["route"]?>/" class="news_article_link">Read More</a>
				</div>
			</article>
			<?php
				}
			?>
			<div class="pagination">
				<?php
					if ($news_page > 1) {
				?>
				<a href="<?=$news_link?><?=($news_page - 1)?>/" class="pagination_arrow pagination_previous">Previous</a>
				<?php
					}

					for ($i = 1; $i <= $news_page_count; $i++) {
						if ($i == $news_page) {
				?>
				<span class="pagination_link pagination_current"><?=$i?></span>
				<?php
						} else {
				?>
				<a href="<?=$news_link?><?=$i?>/" class="pagination_link"><?=$i?></a>
				<?php

						}
					}

					if ($news_page < $news_page_count) {
				?>
				<a href="<?=$news_link?><?=($news_page + 1)?>/" class="pagination_arrow pagination_next">Next</a>
				<?php
					}
				?>
			</div>
		</div>
	</div>
	<div class="fs-cell-right fs-lg-4 fs-xl-3">
		<!-- Sidebar -->
	</div>
</div>