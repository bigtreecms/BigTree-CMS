<!doctype html> 
<!--[if lt IE 7 ]> <html lang="en" class="no-js ie6"> <![endif]-->
<!--[if IE 7 ]>	<html lang="en" class="no-js ie7"> <![endif]-->
<!--[if IE 8 ]>	<html lang="en" class="no-js ie8"> <![endif]-->
<!--[if IE 9 ]>	<html lang="en" class="no-js ie9"> <![endif]-->
<!--[if (gt IE 9)|!(IE)]><!--> <html lang="en" class="no-js"> <!--<![endif]-->
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
		<title><?=$site["nav_title"]?> Login</title>
		<link rel="stylesheet" href="<?=$admin_root?>css/main.css" type="text/css" media="screen" charset="utf-8" />
		<link media="only screen and (max-device-width: 480px)" href="<?=$admin_root?>css/mobile.css" type= "text/css" rel="stylesheet" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no;" />
		<script type="text/javascript" src="<?=$admin_root?>js/lib.js"></script>
		<script type="text/javascript" src="<?=$admin_root?>js/main.js"></script>
	</head>
	<body class="login">
		<div class="login_wrapper">
			<h1><?=$site["nav_title"]?></h1>
			<?=$content?>
			
			<a href="http://www.bigtreecms.com" class="login_logo" target="_blank"></a>
			<span class="login_copyright">
				Version <?=$GLOBALS["bigtree"]["version"]?>&nbsp;&nbsp;&middot;&nbsp;&nbsp;&copy; <?=date("Y")?> <a href="http://www.fastspot.com" target="_blank"> Fastspot</a>
			</span>
		</div>
	</body>
</html>