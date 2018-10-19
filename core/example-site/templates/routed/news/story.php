<?php
	$story = $newsModule->getByRoute($bigtree["commands"][0]);

	// Don't allow arbitrary routes to be entered after the story URL
	if (!$story || isset($bigtree["commands"][1])) {
		$cms->catch404();
	}

	// Setup the meta tags for this page based on open graph info entered by the user, falling back to story fields that make sense
	$cms->setHeadContext("timber_news", $story["id"], $story["title"], $story["blurb"], $story["image"], "article");
	
	// Set the front-end admin bar edit link for this news story
	$bigtree["bar_edit_link"] = ADMIN_ROOT."news/edit/".$story["id"]."/";
?>
<div class="fs-row page_row">
	<div class="fs-cell fs-lg-8 page_content">
		<div class="typography">
			<h1><?=$story["title"]?></h1>
			<?php
				if ($story["image"]) {
			?>
			<figure class="news_detail_image responsive_image">
				<picture class="news_detail_picture">
					<!--[if IE 9]><video style="display: none;"><![endif]-->
					<source media="(min-width: 1220px)" srcset="<?=$story["image"]?>">
					<source media="(min-width: 500px)"  srcset="<?=BigTree::prefixFile($story["image"], "lrg_")?>">
					<!--[if IE 9]></video><![endif]-->
					<img src="<?=BigTree::prefixFile($story["image"], "med_")?>" alt="">
				</picture>
			</figure>
			<?php
				}
			?>
			<time class="news_detail_date" datetime="<?=date(DATE_W3C, strtotime($story["date"]))?>"><?=date("F j, Y", strtotime($story["date"]))?></time>
			
			<?=$story["content"]?>
		</div>
	</div>
	<div class="fs-cell-right fs-lg-4 fs-xl-3">
		<div class="page_sidebar news_detail_sidebar">
			<a href="<?=$news_link?>" class="button_block button_back new_detail_button">Back to News</a>
		</div>
	</div>
</div>