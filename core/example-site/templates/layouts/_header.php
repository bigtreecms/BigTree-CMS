<?
	// Get the homepage, don't process resources.
	$site = $cms->getPage(0, false);
	
	$site_title = $site["title"];
	$page_title = ($page["title"] != $site_title) ? $page["title"] : false;
	
	// Get top level navigation, only one level deep.
	$nav = $cms->getNavByParent(0, 1);
	
	// Get the current page URL
	$current_page = WWW_ROOT.$_GET["bigtree_htaccess_url"];
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
		<meta http-equiv="X-UA-Compatible" content="IE=7" /> <!-- FORCE IE 7 -->
		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
		<meta name="keywords" content="" />
		<meta name="description" content="" />
				
		<title><? if ($local_title) { echo $local_title . ' &middot; '; } ?><? if ($page_title) { echo $page_title . ' &middot; '; } ?><?=$site_title?></title>
		
		<link rel="shortcut icon" href="<?=STATIC_ROOT?>favicon.ico" type="image/x-icon" />
		
		<link rel="stylesheet" href="<?=WWW_ROOT?>css/site.css" type="text/css" media="all" />
		<link rel="stylesheet" href="<?=STATIC_ROOT?>css/print.css" type="text/css" media="print" />
		
		<script src="<?=WWW_ROOT?>js/site.js"></script>
		
		<!--[if LTE IE 8]>
			<script src="<?=STATIC_ROOT?>js/html5.js"></script>
			<link rel="stylesheet" href="<?=STATIC_ROOT?>css/ie.css" type="text/css" media="all" />
		<![endif]-->
	</head>
	<body class="griddle">
		<header id="header">
			<div class="row_12 contain">
				<div class="cell_5">
					<a href="<?=WWW_ROOT?>" class="branding"><?=$site_title?></a>
				</div>
				<div class="cell_7">
					<nav>
						<? foreach ($nav as $item) { ?>
						<a href="<?=$item["link"]?>"<? if (strpos($current_page,$item["link"]) !== false) { ?> class="active"<? } ?>><?=$item["title"]?></a>
						<? } ?>
					</nav>
				</div>
			</div>
		</header>