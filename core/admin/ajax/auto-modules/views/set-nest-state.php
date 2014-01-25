<?
	setcookie('bigtree_admin[nested_views]['.$_POST["view"]."][".$_POST["id"]."]",($_POST["expanded"] == "true" ? true : false),time()+31*60*60*24,str_replace(DOMAIN,"",WWW_ROOT));
?>