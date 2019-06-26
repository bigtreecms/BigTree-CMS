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
							<h1 class="site_name">
								<button class="site_trigger">
									<span class="site_trigger_label"><?=$site->NavigationTitle?></span>
									<?=icon("site_trigger", "keyboard_arrow_down")?>
								</button>
							</h1>
							<nav class="site_links">
								<a class="site_link" href="<?=ADMIN_ROOT?>users/profile/">Edit Profile</a>
								<a class="site_link" href="<?=ADMIN_ROOT?>login/logout/">Logout</a>
							</nav>
						</div>
						<div class="user">
							<?=icon("user", "account_circle")?>
							<span class="user_label"><?=Auth::user()->Name?></span>
						</div>
					</div>
					
					<main-navigation title="<?=Text::translate("Menu", true)?>" :links="main_nav"></main-navigation>
					
					<div class="header_details">
						<p class="credit">Version <?=BIGTREE_VERSION?></p>
						<nav class="util_nav">
							<a class="util_link" href="<?=ADMIN_ROOT?>credits/">Credits &amp; Licenses</a>
							<a class="util_link" href="https://www.bigtreecms.org/about/help/" target="_blank">Developer &amp; User Support</a>
						</nav>
					</div>
				</div>
			</header>
	
			<main class="main">
				<meta-bar :items="meta_bar"></meta-bar>
				<div class="page_header" :class='{ "layout_empty_breadcrumb": !breadcrumb.length, "layout_empty_sub_nav": !sub_nav.length }'>
					<breadcrumb v-if="breadcrumb.length" :links="breadcrumb"></breadcrumb>
					
					<div class="page_header_body">
						<page-title :title="page_title" :url="page_public_url"></page-title>
						<page-tools v-if="tools.length" :links="tools"></page-tools>
					</div>
					
					<sub-navigation v-if="sub_nav.length" :links="sub_nav"></sub-navigation>
				</div>
				
				<?php Router::renderContent(); ?>
			</main>
			
			<footer class="footer">
				<p class="credit">Version <?=BIGTREE_VERSION?></p>
				<nav class="util_nav">
					<a class="util_link" href="<?=ADMIN_ROOT?>credits/">Credits &amp; Licenses</a>
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
			Admin::registerRuntimeJavascript("views/navigation/main-navigation.js");
			Admin::registerRuntimeJavascript("views/structure/page-title.js");
			Admin::registerRuntimeJavascript("views/structure/meta-bar.js");
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
					],
					meta_bar: [
						{ "title": "Expires", "value": "5 days", "type": "text" },
						{ "title": "SEO Score", "value": 50, "type": "visual" },
						{ "title": "Last Updated", "value": "June 15, 2019", "type": "text" }
					],
					main_nav: <?=get_navigation_menu_state()?>
				},
				mounted: function() {
					console.log("mounted");
					
					this.$on("action_menu_change", function(data) {
						console.log(data);
					});
				}
			});
		</script>
	</body>
</html>