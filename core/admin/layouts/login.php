<?
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
		<title><?=$site["nav_title"]?> Login</title>
		<link rel="stylesheet" href="<?=$root?>css/main.css" type="text/css" media="screen" charset="utf-8" />
		<script src="<?=$root?>js/lib.js"></script>
		<script src="<?=$root?>js/main.js"></script>
	</head>
	<body class="login">
		<div class="login_wrapper">
			<h1><?=$site["nav_title"]?></h1>
			<?=$bigtree["content"]?>
			
			<a href="http://www.bigtreecms.com" class="login_logo" target="_blank"></a>
			<span class="login_copyright">
				Version <?=BIGTREE_VERSION?>&nbsp;&nbsp;&middot;&nbsp;&nbsp;&copy; <?=date("Y")?> <a href="http://www.fastspot.com" target="_blank"> Fastspot</a>
			</span>
		</div>
	</body>
</html>