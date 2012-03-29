<?
	$breadcrumb[] = array("title" => "Created Feed", "link" => "#");
	
	$route = $admin->createFeed($_POST["name"],$_POST["description"],$_POST["table"],$_POST["type"],$_POST["options"],$_POST["fields"]);
?>
<h1><span class="icon_developer_feeds"></span>Created Feed</h1>
<p>Your feed is accessible at: <a href="<?=$www_root?>feeds/<?=$route?>/"><?=$www_root?>feeds/<?=$route?>/</a></p>