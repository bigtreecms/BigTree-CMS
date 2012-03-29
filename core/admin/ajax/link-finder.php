<?
	$results = $admin->searchPages($_POST["query"]);
	foreach ($results as $r) {
?>
<a href="<?=$www_root.$r["path"]."/"?>" title="<?=$r["title"]?>"><?=$r["nav_title"]?></a>
<?
	}
?>