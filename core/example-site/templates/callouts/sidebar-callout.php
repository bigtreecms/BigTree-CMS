<article class="sidebar_callout">
	<?php
		if ($callout["image"]) {
	?>
	<figure class="sidebar_callout_image responsive_image">
		<picture class="sidebar_callout_picture">
			<!--[if IE 9]><video style="display: none;"><![endif]-->
			<source media="(min-width: 980px)" srcset="<?=$callout["image"]?>">
			<!--[if IE 9]></video><![endif]-->
			<img src="<?=BigTree::prefixFile($callout["image"], "sqr_")?>" alt="">
		</picture>
	</figure>
	<?php
		}
	?>
	<div class="sidebar_callout_wrapper">
		<h2 class="sidebar_callout_heading"><?=$callout["heading"]?></h2>
		<div class="sidebar_callout_content">
			<?=$callout["blurb"]?>
		</div>
		<?php
			if ($callout["link_title"] && $callout["link_url"]) {
		?>
		<a href="<?=$callout["link_url"]?>" class="sidebar_callout_link"><?=$callout["link_title"]?></a>
		<?php
			}
		?>
	</div>
</article>