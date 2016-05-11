<?php
	namespace BigTree;

	$route = $admin->createFeed($_POST["name"],$_POST["description"],$_POST["table"],$_POST["type"],$_POST["options"],$_POST["fields"]);
?>
<div class="container">
	<section>
		<p><?=Text::translate('Your feed is accessible at: <a href=":feed_link:">:feed_link:</a>', false, array(":feed_link:" => WWW_ROOT."feeds/".$route."/"))?></p>
	</section>
</div>