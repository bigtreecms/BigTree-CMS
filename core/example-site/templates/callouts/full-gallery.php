<?php
	$carousel_options = array(
		"autoHeight" => true
	);
?>
<article class="media_gallery">
	<div class="fs-row">
		<div class="fs-cell">
			<h2 class="media_gallery_heading"><?=$callout["heading"]?></h2>
			<div class="media_gallery_carousel js-carousel" data-carousel-options="<?=htmlspecialchars(json_encode($carousel_options))?>">
				<?php
					foreach ($callout["images"] as $image) {
				?>
				<figure class="media_gallery_item">
					<picture class="media_gallery_picture responsive_image">
						<!--[if IE 9]><video style="display: none;"><![endif]-->
						<source media="(min-width: 980px)" srcset="<?=$image["image"]?>">
						<source media="(min-width: 740px)" srcset="<?=BigTree::prefixFile($image["image"], "lrg_")?>">
						<source media="(min-width: 500px)" srcset="<?=BigTree::prefixFile($image["image"], "med_")?>">
						<!--[if IE 9]></video><![endif]-->
						<img src="<?=BigTree::prefixFile($image["image"], "sml_")?>" alt="">
					</picture>
					<figcaption class="media_gallery_caption"><?=$image["caption"]?></figcaption>
				</figure>
				<?php
					}
				?>
			</div>
		</div>
	</div>
</article>