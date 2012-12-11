<?
	$route = $admin->createFeed($_POST["name"],$_POST["description"],$_POST["table"],$_POST["type"],$_POST["options"],$_POST["fields"]);
?>
<div class="container">
	<section>
		<p>Your feed is accessible at: <a href="<?=WWW_ROOT?>feeds/<?=$route?>/"><?=WWW_ROOT?>feeds/<?=$route?>/</a></p>
	</section>
</div>