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
	<? 
		if ($photo_file) { 
			$photo_file = BigTree::prefixFile($photo_file, "med_");
	?>
	<figure>
		<img src="<?=$photo_file?>" alt="Content Image" />
		<?
			if ($photo_caption) {
		?>
		<figcaption><?=$photo_caption?></figcaption>
		<?
			}
		?>
	</figure>
	<?
		}
	?>
	<h1><?=$page_header?></h1>
	<?=$page_content?>
</article>