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
		<script src="<?=ADMIN_ROOT?>js/tinymce/tinymce.min.js?<?=BIGTREE_VERSION?>"></script>
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
					
					<navigation-main title="<?=Text::translate("Menu", true)?>" :links="main_nav"></navigation-main>
					
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
				<meta-bar v-if="meta_bar.length" :items="meta_bar"></meta-bar>
				<div class="page_header" :class='{ "layout_empty_breadcrumb": !breadcrumb.length, "layout_empty_sub_nav": !sub_nav.length }'>
					<breadcrumb v-if="breadcrumb.length" :links="breadcrumb"></breadcrumb>
					
					<div class="page_header_body">
						<page-title :title="page_title" :url="page_public_url"></page-title>
						<page-tools v-if="tools.length" :links="tools"></page-tools>
					</div>
					
					<navigation-sub v-if="sub_nav.length" :links="sub_nav"></navigation-sub>
				</div>
				
				<button-bar v-if="sub_nav_actions.length" :links="sub_nav_actions"></button-bar>
				
				<div id="content">
					<?php Admin::renderContent(); ?>
				</div>
			</main>
			
			<footer class="footer">
				<p class="credit">Version <?=BIGTREE_VERSION?></p>
				<nav class="util_nav">
					<a class="util_link" href="<?=ADMIN_ROOT?>credits/">Credits &amp; Licenses</a>
					<a class="util_link" href="https://www.bigtreecms.org/about/help/" target="_blank">Developer &amp; User Support</a>
				</nav>
			</footer>
		</div>
		<link type="text/css" rel="stylesheet" src="<?=ADMIN_ROOT?>css/cache/vue.css" />
		<?php
			Admin::drawState();
			Vue::buildCache(); // Remove in production
		?>
		<script>
			const ADMIN_ROOT = "<?=ADMIN_ROOT?>";
			const WWW_ROOT = "<?=WWW_ROOT?>";
			const VueLanguagePack = {};
			const TinyMCEConfig = <?=file_get_contents(Router::getIncludePath("tinymce.config.json"))?>;
		</script>
		<script src="<?=ADMIN_ROOT?>js/tinymce/tinymce.min.js"></script>
		<script src="<?=ADMIN_ROOT?>js/api.js"></script>
		<script src="<?=ADMIN_ROOT?>js/vue/vue.js"></script>
		<script src="<?=ADMIN_ROOT?>js/vue/async-computed.js"></script>
		<script src="<?=ADMIN_ROOT?>js/vue/tinymce.js"></script>
		<script src="<?=ADMIN_ROOT?>js/vue/sortable.js"></script>
		<script src="<?=ADMIN_ROOT?>js/vue/helpers.js"></script>
		<script src="<?=ADMIN_ROOT?>js/cache/vue.js"></script>
		<script src="<?=ADMIN_ROOT?>js/app.js"></script>
		<?php
			foreach (Admin::$Javascript as $script) {
		?>
		<script src="<?=$script?>"></script>
		<?php
			}
		?>
	</body>
</html>