<?
	$current_url = BigTree::currentURL();
	$home_page = $cms->getPage(0);
	
	if ($bigtree["page"]["id"]) {
		$bigtree["page"]["title"] .= "&nbsp;&middot;&nbsp;".$home_page["nav_title"];
	}
?><!DOCTYPE html>
<html lang="en">
	<head>
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
		<meta name="viewport" content="width=device-width, height=device-height, initial-scale=1.0, user-scalable=0, minimum-scale=1.0, maximum-scale=1.0">
		<meta name="apple-mobile-web-app-capable" content="yes" />
		
		<meta name="keywords" content="<?=$bigtree["page"]["meta_keywords"]?>" />
		<meta name="description" content="<?=$bigtree["page"]["meta_description"]?>" />
		<meta name="author" content="<?=$home_page["nav_title"]?>" />
		
		<!-- G+ AND FACEBOOK META TAGS -->
		<meta property="og:title" content="<?=$bigtree["page"]["title"]?>" />
		<meta property="og:url" content="<?=$current_url?>" />
		<meta property="og:type" content="website">
		<meta property="og:image" content="<?=STATIC_ROOT?>images/facebook.jpg" />
		<meta property="og:description" content="<?=$page["meta_description"]?>" />
		<meta property="og:site_name" content="<?=$home_page["nav_title"]?>" />
		
		<!-- TWITTER CARD -->
		<meta name="twitter:card" content="summary" />
		<meta name="twitter:site" content="@" />
		<meta name="twitter:creator" content="@" />
		<meta name="twitter:url" content="<?=$current_url?>" />
		<meta name="twitter:title" content="<?=$bigtree["page"]["title"]?>" />
		<meta name="twitter:description" content="<?=$page["meta_description"]?>" />
		<meta name="twitter:image" content="<?=STATIC_ROOT?>images/facebook.jpg" />
		
		<title><?=$bigtree["page"]["title"]?></title>
		
		<link rel="icon" href="<?=STATIC_ROOT?>favicon.ico" type="image/x-icon" />
		<link rel="shortcut icon" href="<?=STATIC_ROOT?>favicon.ico" type="image/x-icon" />
		
		<link rel="stylesheet" href="<?=WWW_ROOT?>css/site.css" type="text/css" media="all" />
		<link rel="stylesheet" href="<?=STATIC_ROOT?>css/print.css" type="text/css" media="print" />
		
		<!--[if IE 9]>
			<link rel="stylesheet" href="<?=WWW_ROOT?>css/site-ie9.css" type="text/css" media="all" />
			<script src="<?=WWW_ROOT?>js/site-ie9.js"></script>
		<![endif]-->
		<!--[if IE 8]>
			<link rel="stylesheet" href="<?=WWW_ROOT?>css/site-ie8.css" type="text/css" media="all" />
			<script src="<?=WWW_ROOT?>js/site-ie8.js"></script>
		<![endif]-->
		
		<script src="<?=WWW_ROOT?>js/site.js" defer></script>
		
		<noscript>
			<style>
				body { opacity: 1; }
			</style>
		</noscript>
	</head>
	<body class="gridlock shifter">
		<div class="shifter-page">
			<header id="header">
				<a href="<?=WWW_ROOT?>" class="branding"><?=$home_page["nav_title"]?></a>
				<span class="shifter-handle">Menu</span>
			</header>