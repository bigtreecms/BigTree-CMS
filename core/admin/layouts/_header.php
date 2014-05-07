<?	
	$nav = isset($bigtree["nav_override"]) ? $bigtree["nav_override"] : array(
		array("link" => "dashboard", "title" => "Dashboard", "access" => 0, "children" => array(
			array("link" => "", "title" => "Overview", "access" => 0),
			array("link" => "pending-changes", "title" => "Pending Changes", "access" => 0),
			array("link" => "messages", "title" => "Message Center", "access" => 0),
			array("link" => "vitals-statistics", "title" => "Vitals &amp; Statistics", "access" => 1)
		)),
		array("link" => "pages", "title" => "Pages", "access" => 0),
		array("link" => "modules", "title" => "Modules", "access" => 0),
		array("link" => "users", "title" => "Users", "access" => 1),
		array("link" => "settings", "title" => "Settings", "access" => 1),
		array("link" => "developer", "title" => "Developer", "access" => 2, "children" => array(
			array("link" => "", "title" => "Create", "access" => 2, "group" => true, "children" => array(
				array("link" => "developer/templates", "title" => "Templates", "access" => 2),
				array("link" => "developer/modules", "title" => "Modules", "access" => 2),
				array("link" => "developer/callouts", "title" => "Callouts", "access" => 2),
				array("link" => "developer/field-types", "title" => "Field Types", "access" => 2),
				array("link" => "developer/feeds", "title" => "Feeds", "access" => 2),
				array("link" => "developer/settings", "title" => "Settings", "access" => 2),
				array("link" => "developer/packages", "title" => "Packages", "access" => 2),
			)),
			array("link" => "", "title" => "Configure", "access" => 2, "group" => true, "children" => array(
				array("link" => "developer/cloud-storage", "title" => "Cloud Storage", "access" => 2),
				array("link" => "developer/payment-gateway", "title" => "Payment Gateway", "access" => 2),
				array("link" => "developer/geocoding", "title" => "Geocoding", "access" => 2),
				array("link" => "developer/services", "title" => "Service APIs", "access" => 2),
				array("link" => "dashboard/vitals-statistics/analytics/configure/", "title" => "Analytics", "access" => 1)
			))
		))
	);
	
	$unread_messages = $admin->getUnreadMessageCount();	
	$site = $cms->getPage(0,false);

	// Show an alert for being on the development site of a live site, in maintenance mode, or in developer mode
	$environment_alert = false;
	if (!empty($bigtree["config"]["maintenance_url"])) {
		$environment_alert = '<span><strong>Maintenance Mode</strong> &middot; Entire Site Restricted To Developers</span>';
	} elseif (!empty($bigtree["config"]["developer_mode"])) {
		$environment_alert = '<span><strong>Developer Mode</strong> &middot; Admin Area Restricted To Developers</span>';
	} elseif ($bigtree["config"]["environment"] == "dev" && $bigtree["config"]["environment_live_url"]) {
		$environment_alert = '<span><strong>Development Site</strong> &middot; Changes Will Not Effect Live Site!</span><a href="'.$bigtree["config"]["environment_live_url"].'">Go Live</a>';
	}
?>
<!doctype html> 
<!--[if lt IE 7 ]> <html lang="en" class="ie ie6"> <![endif]-->
<!--[if IE 7 ]>	<html lang="en" class="ie ie7"> <![endif]-->
<!--[if IE 8 ]>	<html lang="en" class="ie ie8"> <![endif]-->
<!--[if IE 9 ]>	<html lang="en" class="ie9"> <![endif]-->
<!--[if (gt IE 9)|!(IE)]><!--> <html lang="en"> <!--<![endif]-->
	<head>
		<meta charset="utf-8" />
		<meta name="robots" content="noindex,nofollow" />
		<title><? if (isset($bigtree["admin_title"])) { ?><?=BigTree::safeEncode($bigtree["admin_title"])?> | <? } ?><?=$site["nav_title"]?> Admin</title>
		<link rel="stylesheet" href="<?=ADMIN_ROOT?>css/main.css" type="text/css" media="screen" />
		<?
			if (isset($bigtree["css"]) && is_array($bigtree["css"])) {
				foreach ($bigtree["css"] as $style) {
		?>
		<link rel="stylesheet" href="<?=ADMIN_ROOT?>css/<?=$style?>" type="text/css" media="screen" />
		<?
				}
			}
		?>
		<script src="<?=ADMIN_ROOT?>js/lib.js"></script>
		<script src="<?=ADMIN_ROOT?>js/main.js"></script>
		<script src="<?=ADMIN_ROOT?>js/<?=isset($bigtree["config"]["html_editor"]) ? $bigtree["config"]["html_editor"]["src"] : "tinymce3/tiny_mce.js"?>"></script>
		<?
			if (isset($bigtree["js"]) && is_array($bigtree["js"])) {
				foreach ($bigtree["js"] as $script) {
		?>
		<script src="<?=ADMIN_ROOT?>js/<?=$script?>"></script>
		<?
				}
			}
		?>
		<!--[if lt IE 9]>
		<script src="//html5shim.googlecode.com/svn/trunk/html5.js"></script>
		<![endif]-->
	</head>
	<body class="bigtree">
		<header class="main">
			<section>
				<a href="<? if ($bigtree["config"]["force_secure_login"]) { echo str_replace("http://","https://",ADMIN_ROOT); } else { echo ADMIN_ROOT; } ?>login/logout/" class="logout"><span></span>Logout</a>
				<div></div>
				<p class="messages"><a href="<?=ADMIN_ROOT?>dashboard/messages/"><?=$unread_messages?> Unread Messages</a></p>
				<div></div>
				<p class="welcome"><span class="gravatar"><img src="<?=BigTree::gravatar($admin->User, 28)?>" alt="" /></span>Welcome Back <a href="<?=ADMIN_ROOT?>users/profile/"><?=$admin->Name?></a></p>
				<strong><?=$site["nav_title"]?></strong>
				<a href="<?=WWW_ROOT?>" target="_blank" class="view_site">View Site</a>
			</section>
		</header>
		<nav class="main">
			<section>
				<ul>
					<?
						$x = -1;
						foreach ($nav as $item) {
							if ($admin->Level >= $item["access"] && (!$admin->HidePages || $item["link"] != "pages")) {
								$x++;
								// Need to check custom nav states better
								$link_pieces = explode("/",$item["link"]);
								$path_pieces = array_slice($bigtree["path"],1,count($link_pieces));
					?>
					<li>
						<a href="<?=ADMIN_ROOT?><?=$item["link"]?>/"<? if ($link_pieces == $path_pieces || ($item["link"] == "modules" && $bigtree["in_module"])) { $bigtree["active_nav_item"] = $x; ?> class="active"<? } ?>><span class="<?=$cms->urlify($item["title"])?>"></span><?=$item["title"]?></a>
						<? if (isset($item["children"]) && count($item["children"])) { ?>
						<ul>
							<?
								foreach ($item["children"] as $child) {
									if ($admin->Level >= $child["access"]) {
										if (isset($child["group"]) && count($child["children"])) {
							?>
							<li class="grouper"><?=$child["title"]?></li>
							<? 
											foreach ($child["children"] as $c) {
							?>
							<li><a href="<?=ADMIN_ROOT?><?=$c["link"]?>/"><?=$c["title"]?></a></li>
							<?
											}
										} elseif (!isset($child["group"])) {
							?>
							<li><a href="<?=ADMIN_ROOT?><?=$item["link"]?>/<?=$child["link"]?>/"><?=$child["title"]?></a></li>
							<?
										}
									}
								}
							?>
						</ul>
						<? } ?>
					</li>
					<?
							}	
						}
					?>
				</ul>
				<form method="get" action="<?=ADMIN_ROOT?>search/">
					<input type="submit" class="qs_image" alt="Search" />
					<input type="search" name="query" autocomplete="off" placeholder="Quick Search" class="qs_query" />
					<div id="quick_search_results" style="display: none;"></div>
				</form>
			</section>
		</nav>
		<div class="body">
			<div class="wrapper">
				<? if ($environment_alert) { ?>
				<div class="environment_alert">
					<?=$environment_alert?>
				</div>
				<? } ?>
				<aside id="growl"></aside>