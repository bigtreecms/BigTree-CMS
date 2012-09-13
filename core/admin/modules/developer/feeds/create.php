<?
	$breadcrumb[] = array("title" => "Created Feed", "link" => "#");
	
	$route = $admin->createFeed($_POST["name"],$_POST["description"],$_POST["table"],$_POST["type"],$_POST["options"],$_POST["fields"]);
?>
<h1><span class="feeds"></span>Created Feed</h1>
<p>Your feed is accessible at: <a href="<?=WWW_ROOT?>feeds/<?=$route?>/"><?=WWW_ROOT?>feeds/<?=$route?>/</a></p>