<?php
	$bigtree['form'] = $form = BigTreeAutoModule::getEmbedFormByHash($_GET['hash']);
	$bigtree['form_root'] = ADMIN_ROOT.'ajax/auto-modules/embeddable-form/';
?><!doctype html> 
<!--[if lt IE 7 ]> <html lang="en" class="ie ie6"> <![endif]-->
<!--[if IE 7 ]>	<html lang="en" class="ie ie7"> <![endif]-->
<!--[if IE 8 ]>	<html lang="en" class="ie ie8"> <![endif]-->
<!--[if IE 9 ]>	<html lang="en" class="ie9"> <![endif]-->
<!--[if (gt IE 9)|!(IE)]><!--> <html lang="en"> <!--<![endif]-->
	<head>
		<meta charset="utf-8" />
		<link rel="stylesheet" href="<?=ADMIN_ROOT?>css/main.css" type="text/css" media="screen" />
		<?php
			if (isset($bigtree['css']) && is_array($bigtree['css'])) {
			    foreach ($bigtree['css'] as $style) {
			        ?>
		<link rel="stylesheet" href="<?=ADMIN_ROOT?>css/<?=$style?>" type="text/css" media="screen" />
		<?php

			    }
			}
			if ($form['css']) {
			    ?>
		<link rel="stylesheet" href="<?=$form['css']?>" type="text/css" media="screen" />
		<?php

			}
		?>
		<script src="<?=ADMIN_ROOT?>js/lib.js"></script>
		<script src="<?=ADMIN_ROOT?>js/main.js"></script>
		<script src="<?=ADMIN_ROOT?>js/<?=isset($bigtree['config']['html_editor']) ? $bigtree['config']['html_editor']['src'] : 'tinymce3/tiny_mce.js'?>"></script>
		<?php
			if (isset($bigtree['js']) && is_array($bigtree['js'])) {
			    foreach ($bigtree['js'] as $script) {
			        ?>
		<script src="<?=ADMIN_ROOT?>js/<?=$script?>"></script>
		<?php

			    }
			}
		?>
		<!--[if lt IE 9]>
		<script src="//html5shim.googlecode.com/svn/trunk/html5.js"></script>
		<![endif]-->
	</head>
	<body class="bigtree embedded">
