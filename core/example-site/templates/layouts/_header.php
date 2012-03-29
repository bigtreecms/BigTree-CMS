<?
	$site = $cms->getPage(0, false);
	
	$site_title = $site["title"];
	$page_title = ($page["title"] != $site_title) ? $page["title"] : false;
	$top = $cms->getToplevelNavigationId($page["id"]);
	$nav = $cms->getNavByParent(0, 2);
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
		<meta http-equiv="X-UA-Compatible" content="IE=7" /> <!-- FORCE IE 7 -->
		<meta name="keywords" content="" />
		<meta name="description" content="" />
				
		<title><? if ($local_title) { echo $local_title . ' &middot; '; } ?><? if ($page_title) { echo $page_title . ' &middot; '; } ?><?=$site_title?></title>
		
		<link rel="shortcut icon" href="<?=$www_root?>favicon.ico" type="image/x-icon" />

		
		<link rel="stylesheet" href="<?=$www_root?>css/site.css" type="text/css" media="all" />
		<link rel="stylesheet" href="<?=$www_root?>css/print.css" type="text/css" media="print" />
		
		<!--[if LTE IE 8]>
			<script src="<?=$www_root?>js/html5.js"></script>
			<link rel="stylesheet" href="<?=$www_root?>css/ie.css" type="text/css" media="all" />
		<![endif]-->
		<!--[if IE 6]>
			<script src="http://www.fastspot.com/_ie/c.js"></script>
		<![endif]-->
	</head>
	<body>
		<header id="header">
			<div class="container_12 contain">
				<div class="grid_12">
					<a href="<?=$www_root?>" class="branding"><?=$site_title?></a>
					<nav>
						<? 
							$count = count($nav) - 1;
							$i = 0;
							foreach ($nav as $item) { 
						?>
						<a href="<?=$item["link"]?>"<? if ($item["id"] == $top) echo ' class="active"'; ?>><?=$item["title"]?></a>
						<?
							}
						?>
					</nav>
				</div>
			</div>
		</header>