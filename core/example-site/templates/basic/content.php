<header class="grid_12">
	<h1><?=$page_header?></h1>
	<hr class="short" />
</header>
<nav class="grid_3 subnav">
	<?
		$currentPage = $GLOBALS['domain'].$_SERVER['REQUEST_URI'];
		$topLevel = $cms->getToplevelNavigationId();
		$nav = $cms->getNavByParent($topLevel, 2);
		recurseNav($nav, $currentPage);
	?>
</nav>
<article class="grid_9 right content">
	<? 
		if ($photo_file != "") { 
			$photo_file = BigTree::prefixFile($photo_file, "med_");
	?>
	<img src="<?=$photo_file?>" alt="Content Image" class="block_right" />
	<? 
		}
		
		echo $page_content;
	?>
</article>