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
		if ($photo_file != "") { 
			$photo_file = BigTree::prefixFile($photo_file, "med_");
	?>
	<img src="<?=$photo_file?>" alt="Content Image" class="block_right" />
	<? 
		}
	?>
	<h1><?=$page_header?></h1>
	<?=$page_content?>
</article>