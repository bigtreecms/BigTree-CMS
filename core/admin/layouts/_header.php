<?php
	namespace BigTree;

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
				array("link" => "developer/extensions", "title" => "Extensions &amp; Packages", "access" => 2),
			)),
			array("link" => "", "title" => "Configure", "access" => 2, "group" => true, "children" => array(
				array("link" => "developer/cloud-storage", "title" => "Cloud Storage", "access" => 2),
				array("link" => "developer/payment-gateway", "title" => "Payment Gateway", "access" => 2),
				array("link" => "dashboard/vitals-statistics/analytics/configure/", "title" => "Analytics", "access" => 1),
				array("link" => "developer/geocoding", "title" => "Geocoding", "access" => 2),
				array("link" => "developer/email", "title" => "Email Delivery", "access" => 2),
				array("link" => "developer/services", "title" => "Service APIs", "access" => 2),
				array("link" => "developer/media", "title" => "Media", "access" => 2),
				array("link" => "developer/security", "title" => "Security", "access" => 2)
			))
		))
	);
	
	$unread_messages = Message::getUserUnreadCount();
	$unread_message_string = Text::translate(":count: Unread Message".(($unread_messages == 1) ? "" : "s"), false, array(":count:" => $unread_messages));
	$site = new Page(0, false);

	// Show an alert for being on the development site of a live site, in maintenance mode, or in developer mode
	$environment_alert = false;
	if (!empty($bigtree["config"]["maintenance_url"])) {
		$environment_alert = '<span><strong>'.Text::translate("Maintenance Mode").'</strong> &middot; '.Text::translate("Entire Site Restricted To Developers").'</span>';
	} elseif (!empty($bigtree["config"]["developer_mode"])) {
		$environment_alert = '<span><strong>'.Text::translate("Developer Mode").'</strong> &middot; '.Text::translate("Admin Area Restricted To Developers").'</span>';
	} elseif ($bigtree["config"]["environment"] == "dev" && $bigtree["config"]["environment_live_url"]) {
		$environment_alert = '<span><strong>'.Text::translate("Development Site").'</strong> &middot; '.Text::translate("Changes Will Not Affect Live Site!").'</span><a href="'.$bigtree["config"]["environment_live_url"].'">'.Text::translate("Go Live").'</a>';
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
		<title><?php if (isset($bigtree["admin_title"])) { ?><?=Text::htmlEncode($bigtree["admin_title"])?> | <?php } ?><?=$site->NavigationTitle?> <?=Text::translate("Admin")?></title>
		<link rel="stylesheet" href="<?=ADMIN_ROOT?>css/main.less" type="text/css" media="screen" />
		<?php
			// Configuration based CSS
			if (isset($bigtree["config"]["admin_css"]) && is_array($bigtree["config"]["admin_css"])) {
				foreach ($bigtree["config"]["admin_css"] as $style) {
		?>
		<link rel="stylesheet" href="<?=ADMIN_ROOT?>css/<?=$style?>" type="text/css" media="screen" />
		<?php
				}
			}
			
			// Runtime based CSS
			if (isset($bigtree["css"]) && is_array($bigtree["css"])) {
				$bigtree["css"] = array_unique($bigtree["css"]);
				foreach ($bigtree["css"] as $style) {
					$css_path = explode("/",$style);

					// This is an extension piece acknowledging it could be used outside the extension root
					if ($css_path[0] == "*") {
						$include_path = ADMIN_ROOT.$style;
					// This is an extension inside its routed directory loading its own styles
					} elseif (defined("EXTENSION_ROOT")) {
						$include_path = ADMIN_ROOT."*/".$bigtree["module"]["extension"]."/css/".$style;
					// This is just a regular old include
					} else {
						$include_path = ADMIN_ROOT."css/".$style;
					}
		?>
		<link rel="stylesheet" href="<?=$include_path?>" type="text/css" media="screen" />
		<?php
				}
			}
		?>
		<script>
			var CSRFTokenField = "<?=CSRF::$Field?>";
			var CSRFToken = "<?=CSRF::$Token?>";
		</script>
		<script src="<?=ADMIN_ROOT?>js/lib.js"></script>
		<script src="<?=ADMIN_ROOT?>js/main.js"></script>
		<script>BigTree.dateFormat = "<?=Date::convertTojQuery($bigtree["config"]["date_format"])?>";</script>
		<?php
			if (!empty($bigtree["config"]["html_editor"]) && $bigtree["config"]["html_editor"]["src"] != "tinymce4/tinymce.js") {
		?>
		<script src="<?=ADMIN_ROOT?>js/<?=$bigtree["config"]["html_editor"]["src"]?>"></script>
		<?php
			} else {
		?>
		<script src="<?=ADMIN_ROOT?>js/tinymce/tinymce.min.js"></script>
		<?php
			}

			// Configuration based JS
			if (isset($bigtree["config"]["admin_js"]) && is_array($bigtree["config"]["admin_js"])) {
				foreach ($bigtree["config"]["admin_js"] as $script) {
		?>
		<script src="<?=ADMIN_ROOT?>js/<?=$script?>"></script>
		<?php
				}
			}

			// Runtime based JS
			if (isset($bigtree["js"]) && is_array($bigtree["js"])) {
				$bigtree["js"] = array_unique($bigtree["js"]);
				foreach ($bigtree["js"] as $script) {
					$js_path = explode("/",$script);

					// This is an extension piece acknowledging it could be used outside the extension root
					if ($js_path[0] == "*") {
						$include_path = ADMIN_ROOT.$script;
					// This is an extension inside its routed directory loading its own scripts
					} elseif (defined("EXTENSION_ROOT")) {
						$include_path = ADMIN_ROOT."*/".$bigtree["module"]["extension"]."/js/".$script;
					// This is just a regular old include
					} else {
						$include_path = ADMIN_ROOT."js/".$script;
					}

		?>
		<script src="<?=$include_path?>"></script>
		<?php
				}
			}
		?>
	</head>
	<body class="bigtree">
		<script>
			if ($.browser.name == "msie" && $.browser.versionNumber > 11) {
				$("body").addClass("browser_msedge browser_msedge_" + $.browser.versionNumber);
			} else {
				$("body").addClass("browser_" + $.browser.name).addClass("browser_" + $.browser.name + "_" + $.browser.versionNumber);
			}
		</script>
		<header class="main">
			<section>
				<a href="<?php if ($bigtree["config"]["force_secure_login"]) { echo str_replace("http://","https://",ADMIN_ROOT); } else { echo ADMIN_ROOT; } ?>login/logout/?true<?php CSRF::drawGETToken(); ?>" class="logout"><span></span><?=Text::translate("Logout")?></a>
				<div></div>
				<p class="messages"><a href="<?=ADMIN_ROOT?>dashboard/messages/"><?=$unread_message_string?></a></p>
				<div></div>
				<p class="welcome"><span class="gravatar"><img src="<?=Image::gravatar(Auth::$Email, 28)?>" alt="" /></span><?=Text::translate("Welcome Back")?> <a href="<?=ADMIN_ROOT?>users/profile/"><?=Auth::$Name?></a></p>
				<strong><?=$site->NavigationTitle?></strong>
				<a href="<?=WWW_ROOT?>" target="_blank" class="view_site"><?=Text::translate("View Site")?></a>
			</section>
		</header>
		<nav class="main">
			<section>
				<ul>
					<?php
						$x = -1;
						foreach ($nav as $item) {
							if (Auth::user()->Level >= $item["access"] && (!Auth::$PagesTabHidden || $item["link"] != "pages")) {
								$x++;
								// Need to check custom nav states better
								$link_pieces = explode("/",$item["link"]);
								$path_pieces = array_slice($bigtree["path"],1,count($link_pieces));
					?>
					<li>
						<a href="<?=ADMIN_ROOT?><?=$item["link"]?>/"<?php if ($link_pieces == $path_pieces || ($item["link"] == "modules" && isset($bigtree["module"]))) { $bigtree["active_nav_item"] = $x; ?> class="active"<?php } ?>><span class="<?=Link::urlify($item["title"])?>"></span><?=Text::translate($item["title"])?></a>
						<?php if (isset($item["children"]) && count($item["children"])) { ?>
						<ul>
							<?php
								foreach ($item["children"] as $child) {
									if (Auth::user()->Level >= $child["access"]) {
										if (isset($child["group"]) && count($child["children"])) {
							?>
							<li class="grouper"><?=Text::translate($child["title"])?></li>
							<?php 
											foreach ($child["children"] as $c) {
							?>
							<li><a href="<?=ADMIN_ROOT?><?=$c["link"]?>/"><?=Text::translate($c["title"])?></a></li>
							<?php
											}
										} elseif (!isset($child["group"])) {
							?>
							<li><a href="<?=ADMIN_ROOT?><?=$item["link"]?>/<?=$child["link"]?>/"><?=Text::translate($child["title"])?></a></li>
							<?php
										}
									}
								}
							?>
						</ul>
						<?php } ?>
					</li>
					<?php
							}	
						}
					?>
				</ul>
				<form method="get" action="<?=ADMIN_ROOT?>search/">
					<input type="submit" class="qs_image" alt="<?=Text::translate("Search")?>" />
					<input type="search" name="query" autocomplete="off" placeholder="<?=Text::translate("Quick Search")?>" class="qs_query" />
					<div id="quick_search_results" style="display: none;"></div>
				</form>
			</section>
		</nav>
		<div class="body">
			<div class="wrapper">
				<?php if ($environment_alert) { ?>
				<div class="environment_alert">
					<?=$environment_alert?>
				</div>
				<?php } ?>
				<aside id="growl"></aside>