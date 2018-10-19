<article class="full_callout">
	<div class="fs-row">
		<div class="fs-cell-right fs-xs-3 fs-sm-1 fs-md-2 fs-lg-6">
			<?php if ($callout["image"]) { ?>
			<figure class="full_callout_image responsive_image">
				<picture class="full_callout_picture">
					<!--[if IE 9]><video style="display: none;"><![endif]-->
					<source media="(min-width: 1220px)" srcset="<?=$callout["image"]?>">
					<source media="(min-width: 980px)"  srcset="<?=BigTree::prefixFile($callout["image"], "sml_")?>">
					<source media="(min-width: 500px)"  srcset="<?=BigTree::prefixFile($callout["image"], "sqr_")?>">
					<source media="(min-width: 0px)"    srcset="<?=BigTree::prefixFile($callout["image"], "sml_")?>">
					<!--[if IE 9]></video><![endif]-->
					<img src="<?=BigTree::prefixFile($callout["image"], "sml_")?>" alt="">
				</picture>
			</figure>
			<?php } ?>
		</div>
		<div class="fs-cell fs-xs-3 fs-sm-2 fs-md-4 fs-lg-6 full_callout_wrapper">
			<h2 class="full_callout_heading"><?=$callout["heading"]?></h2>
			<div class="full_callout_content">
				<?=$callout["blurb"]?>
			</div>
			<?php if ($callout["link_title"] && $callout["link_url"]) { ?>
			<a href="<?=$callout["link_url"]?>" class="full_callout_link"><?=$callout["link_title"]?></a>
			<?php } ?>
		</div>
	</div>
</article>