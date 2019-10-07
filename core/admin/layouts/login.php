<?php
	namespace BigTree;

	$site = new Page(0, null, false);

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

		<script src="<?=ADMIN_ROOT?>js/lib.js?<?=BIGTREE_VERSION?>"></script>
	</head>
	<body>
		<div id="js-vue" class="page">
			<div class="busy" id="js-busy" style="display: none;" aria-live="assertive">
				<span class="busy_title" id="js-busy-message"></span>
				<span class="busy_indicator"></span>
			</div>
	
			<main class="main" id="main_content" tabindex="0">
				<?php Admin::renderContent(); ?>
			</main>
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