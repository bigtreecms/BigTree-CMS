<?
	$mgroups = array();
	$module_groups = $admin->getModuleGroups();
	foreach ($module_groups as $mg) {
		if ($mg["in_nav"]) {
			$modules = $admin->getModulesByGroup($mg["id"]);
			$children = array();
			foreach ($modules as $m) {
				$children[] = array("link" => $m["route"], "title" => $m["name"], "access" => 0);
			}
			$mgroups[] = array("link" => $mg["route"], "title" => $mg["name"], "access" => 0, "group" => true, "children" => $children);
		}
	}
	
	$nav = array(
		array("link" => "dashboard", "title" => "Dashboard", "access" => 0, "children" => array(
			array("link" => "overview", "title" => "Overview", "access" => 0),
			array("link" => "pending-changes", "title" => "Pending Changes", "access" => 0),
			array("link" => "messages", "title" => "Message Center", "access" => 0),
			array("link" => "vitals-statistics", "title" => "Vitals &amp; Statistics", "access" => 1)
		)),
		array("link" => "pages", "title" => "Pages", "access" => 0),
		array("link" => "modules", "title" => "Modules", "access" => 0, "children" => $mgroups),
		array("link" => "users", "title" => "Users", "access" => 1),
		array("link" => "settings", "title" => "Settings", "access" => 1),
		array("link" => "developer", "title" => "Developer", "access" => 2, "children" => array(
			array("link" => "templates", "title" => "Templates", "access" => 2),
			array("link" => "modules", "title" => "Modules", "access" => 2),
			array("link" => "callouts", "title" => "Callouts", "access" => 2),
			array("link" => "field-types", "title" => "Field Types", "access" => 2),
			array("link" => "feeds", "title" => "Feeds", "access" => 2),
			array("link" => "settings", "title" => "Settings", "access" => 2),
			array("link" => "upload-service", "title" => "Upload Service", "access" => 2),
			array("link" => "foundry/install", "title" => "Install Package", "access" => 2),
		))
	);
	
	$unread_messages = $admin->getUnreadMessageCount();	
	$site = $cms->getPage(0,false);
?>
<!doctype html> 
<!--[if lt IE 7 ]> <html lang="en" class="no-js ie ie6"> <![endif]-->
<!--[if IE 7 ]>	<html lang="en" class="no-js ie ie7"> <![endif]-->
<!--[if IE 8 ]>	<html lang="en" class="no-js ie ie8"> <![endif]-->
<!--[if IE 9 ]>	<html lang="en" class="no-js ie ie9"> <![endif]-->
<!--[if (gt IE 9)|!(IE)]><!--> <html lang="en" class="no-js"> <!--<![endif]-->
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
		<title><? if ($module_title) { ?><?=$module_title?> | <? } ?><?=$site["nav_title"]?> Admin</title>
		<link rel="stylesheet" href="<?=$admin_root?>css/main.css" type="text/css" media="screen" charset="utf-8" />
		<link media="only screen and (max-device-width: 480px)" href="<?=$admin_root?>css/mobile.css" type= "text/css" rel="stylesheet" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no;" />
		<? if (is_array($css)) { foreach ($css as $style) { ?>
		<link rel="stylesheet" href="<?=$admin_root?>css/<?=$style?>" type="text/css" media="screen" charset="utf-8" />
		<? } } ?>
		<script type="text/javascript" src="<?=$admin_root?>js/lib.js"></script>
		<script type="text/javascript" src="<?=$admin_root?>js/main.js"></script>
		<? if (is_array($js)) { foreach ($js as $script) { ?>
		<script type="text/javascript" src="<?=$admin_root?>js/<?=$script?>"></script>
		<? } } ?>
		<!--[if lt IE 9]>
		<script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
		<![endif]-->
	</head>
	<body class="bigtree">
		<header class="main">
			<section>
				<a href="<?=$admin_root?>login/logout/" class="logout"><span></span>Logout</a>
				<div></div>
				<p class="messages"><span></span><a href="<?=$admin_root?>dashboard/messages/"><?=$unread_messages?> Unread Messages</a></p>
				<div></div>
				<p class="welcome"><span></span>Welcome Back <a href="<?=$admin_root?>users/profile/"><?=$admin->Name?></a></p>
				<strong><?=$site["nav_title"]?></strong>
				<a href="<?=$www_root?>" target="_blank" class="view_site">View Site</a>
			</section>
		</header>
		<nav class="main">
			<section>
				<ul>
					<?
						foreach ($nav as $item) {
							if ($admin->Level >= $item["access"]) {
					?>
					<li<? if ($path[1] == $item["link"] || ($item["link"] == "modules" && $in_module)) { ?> class="active"<? } ?>>
						<a href="<?=$admin_root?><?=$item["link"]?>/"<? if ($path[1] == $item["link"] || ($item["link"] == "modules" && $in_module)) { ?> class="active"<? } ?>><span class="<?=$cms->urlify($item["title"])?>"></span><?=$item["title"]?></a>
						<? if (count($item["children"])) { ?>
						<ul>
							<?
								foreach ($item["children"] as $child) {
									if ($admin->Level >= $child["access"]) {
										if ($child["group"]) {
							?>
							<li class="grouper"><?=$child["title"]?>
								<? foreach ($child["children"] as $c) { ?>
								<li><a href="<?=$admin_root?><?=$c["link"]?>/"><?=$c["title"]?></a></li>
								<? } ?>
							</li>
							<?
										} else {
							?>
							<li><a href="<?=$admin_root?><?=$item["link"]?>/<?=$child["link"]?>/"><?=$child["title"]?></a></li>
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
				<form method="post" action="<?=$admin_root?>search/">
					<input type="image" src="<?=$admin_root?>images/quick-search-icon.png" class="qs_image" />
					<input type="search" name="query" autocomplete="off" placeholder="Quick Search" class="qs_query" />
					<div id="quick_search_results" style="display: none;"></div>
				</form>
			</section>
		</nav>
		<div class="nav_shadow"></div>
		<div class="body">
			<div class="wrapper">
				<aside id="growl"></aside>
				<? if (count($breadcrumb)) { ?>
				<ul class="breadcrumb">
					<?
						$x = 0;
						foreach ($breadcrumb as $item) {
							$x++;
							
					?>
					<li<? if ($x == 1) { ?> class="first"<? } ?>><a href="<? if ($item["link"] == "#") { ?>#<? } else { ?><?=$admin_root?><?=$item["link"]?><? } ?>"<? if ($x == count($breadcrumb)) { ?> class="last"<? } ?>><?=$item["title"]?></a></li>
					<?
							if ($x != count($breadcrumb)) {
					?>
					<li>&rsaquo;</li>
					<?		
							}
						}
					?>
				</ul>
				<? } ?>