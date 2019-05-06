<?php
	namespace BigTree;

	$site = new Page(0, false);

	// Show an alert for being on the development site of a live site, in maintenance mode, or in developer mode
	$environment_alert = false;

	if (!empty(Router::$Config["maintenance_url"])) {
		$environment_alert = '<span><strong>'.Text::translate("Maintenance Mode").'</strong> &middot; '.Text::translate("Entire Site Restricted To Developers").'</span>';
	} elseif (!empty(Router::$Config["developer_mode"])) {
		$environment_alert = '<span><strong>'.Text::translate("Developer Mode").'</strong> &middot; '.Text::translate("Admin Area Restricted To Developers").'</span>';
	} elseif (Router::$Config["environment"] == "dev" && Router::$Config["environment_live_url"]) {
		$environment_alert = '<span><strong>'.Text::translate("Development Site").'</strong> &middot; '.Text::translate("Changes Will Not Affect Live Site!").'</span><a href="'.Router::$Config["environment_live_url"].'">'.Text::translate("Go Live").'</a>';
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
		<link rel="stylesheet" href="<?=ADMIN_ROOT?>css/main.less?<?=BIGTREE_VERSION?>" type="text/css" media="screen" />
		<?php
			// Configuration based CSS
			if (isset(Router::$Config["admin_css"]) && is_array(Router::$Config["admin_css"])) {
				foreach (Router::$Config["admin_css"] as $style) {
					if (strpos($style, "https://") === false && strpos($style, "http://") === false) {
						$style = ADMIN_ROOT."css/".$style;
					}
		?>
		<link rel="stylesheet" href="<?=$style?>" type="text/css" />
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
		<script src="<?=ADMIN_ROOT?>js/lib.js?<?=BIGTREE_VERSION?>"></script>
		<script src="<?=ADMIN_ROOT?>js/main.js?<?=BIGTREE_VERSION?>"></script>
		<script src="<?=ADMIN_ROOT?>js/tinymce/tinymce.min.js?<?=BIGTREE_VERSION?>"></script>
		<script>BigTree.dateFormat = "<?=Date::convertTojQuery(Router::$Config["date_format"])?>";</script>
		<?php
			// Configuration based JS
			if (isset(Router::$Config["admin_js"]) && is_array(Router::$Config["admin_js"])) {
				foreach (Router::$Config["admin_js"] as $script) {
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
				<a href="<?php if (Router::$Config["force_secure_login"]) { echo str_replace("http://","https://",ADMIN_ROOT); } else { echo ADMIN_ROOT; } ?>login/logout/?true<?php CSRF::drawGETToken(); ?>" class="logout"><span></span><?=Text::translate("Logout")?></a>
				<div></div>
				<p class="welcome"><span class="gravatar"><img src="<?=User::gravatar(Auth::$Email, 28)?>" alt="" /></span><?=Text::translate("Welcome Back")?> <a href="<?=ADMIN_ROOT?>users/profile/"><?=Auth::$Name?></a></p>
				<strong><?=$site->NavigationTitle?></strong>
				<a href="<?=WWW_ROOT?>" target="_blank" class="view_site"><?=Text::translate("View Site")?></a>
			</section>
		</header>
		<nav class="main">
			<section>
				<ul>
					<?php
						$x = -1;

						foreach ($bigtree["nav_tree"] as $item) {
							if ($item["hidden"]) {
								continue;
							}
							
							if (empty($item["level"])) {
								$item["level"] = 0;
							}
							
							if (Auth::user()->Level >= $item["level"] && (!Auth::$PagesTabHidden || $item["link"] != "pages")) {
								$x++;
								// Need to check custom nav states better
								$link_pieces = explode("/",$item["link"]);
								$path_pieces = array_slice(Router::$Path, 1, count($link_pieces));

								if (strpos($item["link"], "https://") === 0 || strpos($item["link"], "http://") === 0) {
									$link = $item["link"];
								} else {
									$link = $item["link"] ? ADMIN_ROOT.$item["link"]."/" : ADMIN_ROOT;
								}
					?>
					<li>
						<a href="<?=$link?>"<?php if ($link_pieces == $path_pieces || ($item["link"] == "modules" && isset($bigtree["module"]))) { $bigtree["active_nav_item"] = $x; ?> class="active"<?php } ?>><span class="<?=$cms->urlify($item["title"])?>"></span><?=$item["title"]?></a>
						<?php
								if (empty($item["no_top_level_children"]) && isset($item["children"]) && count($item["children"])) {
						?>
						<ul>
							<?php
									foreach ($item["children"] as $child) {
										if (!empty($child["top_level_hidden"])) {
											continue;
										}
	
										if (strpos($child["link"], "https://") === 0 || strpos($child["link"], "http://") === 0) {
											$child_link = $child["link"];
										} else {
											$child_link = $child["link"] ? ADMIN_ROOT.rtrim($child["link"], "/")."/" : ADMIN_ROOT;
										}
										
										if (Auth::user()->Level >= $child["access"]) {
											if (!empty($child["group"])) {
							?>
							<li class="grouper"><?=$child["title"]?></li>
							<?php
											} else {
							?>
							<li><a href="<?=$child_link?>"><?=$child["title"]?></a></li>
							<?php
											}
										}
									}
							?>
						</ul>
						<?php
								}
						?>
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