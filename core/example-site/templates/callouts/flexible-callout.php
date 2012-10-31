<?
	/*
		Resources Available:
		"title" = Title - Text
		"image" = Image - Text
		"description" = Description - HTML Area
		"link" = Link - Text
	*/
?>
<div class="callout grey_block<? if (!$bigtree["page"]["id"]) { ?> row_12<? } ?>">
	<? if ($callout["image"]) { ?>
	<figure>
		<img src="<?=$callout["image"]?>" alt="" />
		<? if ($callout["caption"]) { ?>
		<figcaption><?=$callout["caption"]?></figcaption>
		<? } ?>
	</figure>
	<? } ?>
	<h4><?=$callout["title"]?></h4>
	<?=$callout["description"]?>
	<? if ($callout["link"]) { ?>
	<a href="<?=$callout["link"]?>" class="more">Learn More</a>
	<? } ?>
</div>