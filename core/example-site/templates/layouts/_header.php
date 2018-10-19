<?php
	$site_title = "Timber Lumberjack Co";
	$primary_nav = $cms->getNavByParent(0,2);
	$secondary_nav = $cms->getSetting("navigation-secondary");
	$current_url = BigTree::currentURL();
	
	if (empty($page_image)) {
		$page_image = $cms->getSetting("page-image-fallback");
	}

	$background_options = [
		"source" => [
			"0px"    => BigTree::prefixFile($page_image, "sqr_"),
			"500px"  => BigTree::prefixFile($page_image, "sml_"),
			"740px"  => BigTree::prefixFile($page_image, "med_"),
			"980px"  => BigTree::prefixFile($page_image, "lrg_"),
			"1220px" => $page_image
		]
	];
?><!DOCTYPE html>
<html lang="en" class="no-js">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="apple-mobile-web-app-capable" content="yes">
		<meta name="mobile-web-app-capable" content="yes">
		
		<?php $cms->drawHeadTags($site_title, "&middot;"); ?>

		<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:400,600,700,400italic,600italic">
		<link rel="stylesheet" href="<?=STATIC_ROOT?>css/site.css?<?=filemtime(SITE_ROOT."css/main.less")?>">

		<script src="<?=STATIC_ROOT?>js/modernizr.js"></script>
	</head>
	<body class="fs-grid">
		<a href="#page" id="skip_to_content" class="offscreen">Skip to Main Content</a>

		<div class="page_wrapper js-navigation_content">
			<header id="header" class="header js-background" data-background-options="<?=htmlentities(json_encode($background_options))?>">
				<div class="header_position">
					<div class="fs-row header_row">
						<div class="fs-cell fs-sm-half fs-md-half fs-lg-3">
							<a href="<?=WWW_ROOT?>" class="header_logo"><?=$site_title?></a>
						</div>
						<div class="fs-cell fs-sm-half fs-md-half fs-lg-9">
							<div class="desktop_nav">
								<nav class="secondary_nav secondary_nav_desktop">
									<h2 class="nav_heading">Secondary Navigation</h2>
									<?php
										foreach ($secondary_nav as $item) {
									?>
									<div class="secondary_nav_item">
										<a href="<?=$item["link"]?>" class="secondary_nav_link"><?=$item["title"]?></a>
									</div>
									<?php
										}
									?>
								</nav>

								<nav class="main_nav main_nav_desktop">
									<h2 class="nav_heading">Main Navigation</h2>
									<?php
										foreach ($primary_nav as $item) {
									?>
									<div class="main_nav_item">
										<a href="<?=$item["link"]?>" class="main_nav_link<?php if (strpos($current_url, $item["link"]) !== false) { ?> active<?php } ?>"<?php if ($item["new_window"]) { ?> target="_blank"<?php } ?>><?=$item["title"]?></a>
									</div>
									<?php
										}
									?>
								</nav>
								<button class="mobile_nav_handle js-navigation_handle">Menu</button>
							</div>
						</div>
					</div>
					<div class="fs-row">
						<?php
							if ($is_home) {
						?>
						<div class="fs-cell fs-md-5 fs-lg-10 fs-xl-8">
							<div class="home_header">
								<h1><?=str_ireplace(array("{","}"), array("<span>","</span>"), $page_header);?></h1>
							</div>
						</div>
						<?php
							} else {
						?>
						<div class="fs-cell">
							<?php include SERVER_ROOT."templates/layouts/_breadcrumb.php"; ?>
						</div>
						<?php
							}
						?>
					</div>
				</div>
			</header>