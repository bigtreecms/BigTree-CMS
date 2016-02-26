<!doctype html> 
<!--[if lt IE 7 ]> <html lang="en" class="ie ie6"> <![endif]-->
<!--[if IE 7 ]>	<html lang="en" class="ie ie7"> <![endif]-->
<!--[if IE 8 ]>	<html lang="en" class="ie ie8"> <![endif]-->
<!--[if IE 9 ]>	<html lang="en" class="ie9"> <![endif]-->
<!--[if (gt IE 9)|!(IE)]><!--> <html lang="en"> <!--<![endif]-->
	<head>
		<link rel="stylesheet" href="<?=ADMIN_ROOT?>css/main.less" type="text/css" media="screen" charset="utf-8" />
		<?php
			if (isset($bigtree["css"]) && is_array($bigtree["css"])) {
				foreach ($bigtree["css"] as $style) {
		?>
		<link rel="stylesheet" href="<?=ADMIN_ROOT?>css/<?=$style?>" type="text/css" media="screen" />
		<?php
				}
			}
		?>
		<script src="<?=ADMIN_ROOT?>js/lib.js"></script>
		<script src="<?=ADMIN_ROOT?>js/main.js"></script>
		<script src="<?=ADMIN_ROOT?>js/pages.js"></script>
		<script>BigTree.dateFormat = "<?=BigTree::phpDateTojQuery($bigtree["config"]["date_format"])?>";</script>
		<script src="<?=ADMIN_ROOT?>js/<?=isset($bigtree["config"]["html_editor"]) ? $bigtree["config"]["html_editor"]["src"] : "tinymce3/tiny_mce.js"?>"></script>
		<?php
			if (isset($bigtree["js"]) && is_array($bigtree["js"])) {
				foreach ($bigtree["js"] as $script) {
		?>
		<script src="<?=ADMIN_ROOT.(defined("EXTENSION_ROOT") ? "*/".$bigtree["module"]["extension"]."/" : "")?>js/<?=$script?>"></script>
		<?php
				}
			}
		?>
		<style type="text/css">
			#mceModalBlocker { display: none !important; }
		</style>
	</head>
	<body class="bigtree front_end_editor">
		<div class="bigtree_dialog_window front_end_editor_container">
			<?=$bigtree["content"]?>
		</div>
	</body>
</html>