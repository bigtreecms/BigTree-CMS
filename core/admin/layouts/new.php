<?php
	namespace BigTree;
	
	include SERVER_ROOT."core/admin/includes/helpers.php";

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

	// Configuration based CSS
	if (isset(Router::$Config["admin_css"]) && is_array(Router::$Config["admin_css"])) {
		foreach (Router::$Config["admin_css"] as $css) {
			Admin::registerRuntimeCSS($css);
		}
	}
	
	// Configuration based JS
	if (isset(Router::$Config["admin_js"]) && is_array(Router::$Config["admin_js"])) {
		foreach (Router::$Config["admin_js"] as $script) {
			Admin::registerRuntimeJavascript($script);
		}
	}
?><!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex,nofollow" />

		<title><?=$site->NavigationTitle?> <?=Text::translate("Admin")?></title>

		<link rel="stylesheet" href="<?=ADMIN_ROOT?>css/scss/styles.scss?<?=BIGTREE_VERSION?>" type="text/css" media="screen" />
		<?php			
			foreach (Admin::$CSS as $css) {
		?>
		<link rel="stylesheet" href="<?=$css?>" type="text/css" media="screen" />
		<?php
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
	</head>
	<body>
		<div id="js-vue" class="page">
			<header class="header">
				<div class="header_inner">
					<div class="header_group">
						<div class="site">
							<h1 class="site_name"><?=$site->NavigationTitle?></h1>
							<?=icon("site", "keyboard_arrow_down")?>
						</div>
						<div class="user">
							<?=icon("user", "account_circle")?>
							<span class="user_label"><?=Auth::user()->Name?></span>
						</div>
					</div>
					<button class="js-menu-toggle menu_toggle">
						<?=icon("menu_toggle", "menu")?>
						<span class="menu_toggle_label">Menu</span>
					</button>
					<div class="js-menu menu">
						<nav class="admin_nav">
							<ul class="admin_items">
								<?php
									foreach (Router::$AdminNavTree as $item) {
										if ($item["hidden"]) {
											continue;
										}
									
										if (empty($item["level"])) {
											$item["level"] = 0;
										}
									
										if (Auth::user()->Level >= $item["level"] && (!Auth::$PagesTabHidden || $item["link"] != "pages")) {
											// Need to check custom nav states better
											$link_pieces = explode("/",$item["link"]);
											$path_pieces = array_slice(Router::$Path, 1, count($link_pieces));
										
											if (strpos($item["link"], "https://") === 0 || strpos($item["link"], "http://") === 0) {
												$link = $item["link"];
											} else {
												$link = $item["link"] ? ADMIN_ROOT.$item["link"]."/" : ADMIN_ROOT;
											}
											
											$active = ($link_pieces == $path_pieces || ($item["link"] == "modules" && isset($bigtree["module"])));
								?>
								<li class="admin_item">
									<a class="admin_link<?php if ($active) { ?> active<?php } ?>" href="<?=$link?>">
										<?=icon("admin", $item["icon"])?>
										<span class="admin_label"><?=$item["title"]?></span>
									</a>
									<?php
										if ($active && empty($item["no_top_level_children"]) && isset($item["children"]) && count($item["children"])) {
									?>
									<div class="admin_children">
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
										?>
										<a class="admin_child" href="<?=$child_link?>"><?=$child["title"]?></a>
										<?php
												}
											}
										?>
									</div>
									<?php
										}
									?>
								</li>
								<?php
										}
									}
								?>
							</ul>
						</nav>
					</div>
					<div class="header_details">
						<p class="credit">Version <?=BIGTREE_VERSION?>  • <a href="https://github.com/bigtreecms/BigTree-CMS/blob/master/license.txt" target="_blank">License</a></p>
						<nav class="util_nav">
							<a class="util_link" href="<?=ADMIN_ROOT?>">Credits &amp; Licenses</a>
							<a class="util_link" href="https://www.bigtreecms.org/about/help/" target="_blank">Developer &amp; User Support</a>
						</nav>
					</div>
				</div>
			</header>
	
			<main class="main">
				<div class="page_header" v-bind:class='{ "layout_empty_breadcrumb": !breadcrumb, "layout_empty_sub_nav": !sub_nav }'>
					<breadcrumb v-if="breadcrumb" v-bind:links="breadcrumb"></breadcrumb>
					
					<div class="page_header_body">
						<page-title v-bind:title="page_title" v-bind:url="page_public_url"></page-title>
						<page-tools v-if="tools" v-bind:links="tools"></page-tools>
					</div>
					
					<sub-navigation v-if="sub_nav" v-bind:links="sub_nav"></sub-navigation>
				</div>
				
				<?php Router::renderContent(); ?>
			</main>
			
			<footer class="footer">
				<p class="credit">Version <?=BIGTREE_VERSION?> • <a href="https://github.com/bigtreecms/BigTree-CMS/blob/master/license.txt" target="_blank">License</a></p>
				<nav class="util_nav">
					<a class="util_link" href="<?=ADMIN_ROOT?>">Credits &amp; Licenses</a>
					<a class="util_link" href="https://www.bigtreecms.org/about/help/" target="_blank">Developer &amp; User Support</a>
				</nav>
			</footer>
		</div>
		<script src="<?=ADMIN_ROOT?>js/vue.js"></script>
		<?php
			Admin::registerRuntimeJavascript("views/icon.js");
			Admin::registerRuntimeJavascript("views/navigation/breadcrumb.js");
			Admin::registerRuntimeJavascript("views/navigation/page-tools.js");
			Admin::registerRuntimeJavascript("views/navigation/sub-navigation.js");
			Admin::registerRuntimeJavascript("views/structure/page-title.js");
			foreach (Admin::$Javascript as $script) {
		?>
		<script src="<?=$script?>"></script>
		<?php
			}
		?>
		<script>
			new Vue({
				el: "#js-vue",
				data: {
					breadcrumb: [{ "title": "Test", "url": "#test" }, { "title": "Another", "url": "" }],
					page_title: "Testing",
					page_public_url: "http://www.google.com",
					tools: [
						{ "title": "Edit Template", "url": "http://www.google.com", "icon": "view_quilt" },
						{ "title": "View Audit Trail", "url": "#", "icon": "timeline" }
					],
					sub_nav: [
						{ "title": "Link One", "url": "http://www.google.com", "active": true },
						{ "title": "Link Two", "url": "http://www.yahoo.com", "tooltip": { "title": "Test", "content": "Content" }}
					]
				}
			});
		</script>
	</body>
</html>