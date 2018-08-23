<?php
	$file = str_replace(SITE_ROOT, STATIC_ROOT, $_POST["file"]);

	foreach ($_POST["crops"] as $crop) {
?>
<a href="<?=BigTree::prefixFile($file, $crop["prefix"])?>" class="existing_crops_link" target="_blank">
	<img src="<?=BigTree::prefixFile($file, $crop["prefix"])?>" alt="" class="existing_crops_image">
</a>
<?php
	}
?>