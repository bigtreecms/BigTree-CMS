<?
	/*
		Resources Available:
		$page_header = Page Header - Text
		$page_content = Page Content - HTML Area
		$photo_file = Photo - Upload
		$photo_caption = Photo Caption - Text
	*/
?>
<article>
	<h1><?=$page_header?></h1>
	<? if ($photo_file) { ?>
	<figure>
		<img src="<?=$photo_file?>" alt="" width="300" height="300" />
		<? if ($photo_caption) { ?>
		<figcaption><?=$photo_caption?></figcaption>
		<? } ?>
	</figure>
	<? } ?>
	<?=$page_content?>
</article>