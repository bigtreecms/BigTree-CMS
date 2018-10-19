<article class="flexible_callout">
	<?php
		if ($callout["image"]) {
	?>
	<figure class="flexible_callout_image responsive_image">
		<picture class="flexible_callout_picture">
			<!--[if IE 9]><video style="display: none;"><![endif]-->
			<source media="(min-width: 500px)" srcset="<?=BigTree::prefixFile($callout["image"], "sqr_")?>">
			<!--[if IE 9]></video><![endif]-->
			<img src="<?=$callout["image"]?>" alt="">
		</picture>
	</figure>
	<?php
		}
	?>
	<div class="flexible_callout_wrapper">
		<h2 class="flexible_callout_heading"><?=$callout["heading"]?></h2>
		<div class="flexible_callout_content">
			<?=$callout["blurb"]?>
		</div>
		<?php
			if ($callout["link_title"] && $callout["link_url"]) {
		?>
		<a href="<?=$callout["link_url"]?>" class="flexible_callout_link"><?=$callout["link_title"]?></a>
		<?php
			}
		?>
	</div>
</article>