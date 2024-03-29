<?php
	$root = str_replace(array("http://","https://"),"//",ADMIN_ROOT);
?>
<!doctype html> 
<!--[if lt IE 7 ]> <html lang="en" class="no-js ie6"> <![endif]-->
<!--[if IE 7 ]>	<html lang="en" class="no-js ie7"> <![endif]-->
<!--[if IE 8 ]>	<html lang="en" class="no-js ie8"> <![endif]-->
<!--[if IE 9 ]>	<html lang="en" class="no-js ie9"> <![endif]-->
<!--[if (gt IE 9)|!(IE)]><!--> <html lang="en" class="no-js"> <!--<![endif]-->
	<head>
		<meta charset="utf-8">
		<meta name="robots" content="noindex,nofollow" />
		<title>
			<?php
				if (!empty($site["nav_title"])) {
					echo $site["nav_title"]." Login";
				} else {
					echo "Access Denied";
				}
			?>
		</title>
		<link rel="stylesheet" href="<?=$root?>css/main.less" type="text/css" media="screen" charset="utf-8" />
		<script src="<?=$root?>js/lib.js"></script>
		<script src="<?=$root?>js/main.js"></script>
	</head>
	<body class="login<?php if (defined("ADMIN_BODY_CLASS")) { echo " ".ADMIN_BODY_CLASS; } ?>">
		<div class="login_wrapper">
			<?php
				if (!empty($site["nav_title"])) {
			?>
			<h1><?=$site["nav_title"]?></h1>
			<?php
				}
				
				echo $bigtree["content"];
			?>
			
			<a href="https://www.bigtreecms.org" class="login_logo" target="_blank"></a>
		</div>
	</body>
</html>