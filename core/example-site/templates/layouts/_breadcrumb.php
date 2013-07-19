<?
	$breadcrumb = $cms->getBreadcrumb();
?>
<nav class="desktop-12 breadcrumb">
	<a href="<?=WWW_ROOT?>">Home</a>
	<? foreach ($breadcrumb as $crumb) { ?>
	<span>&raquo;</span>
	<a href="<?=$crumb["link"]?>"><?=$crumb["title"]?></a>
	<? } ?>
</nav>