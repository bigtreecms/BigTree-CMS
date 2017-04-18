<?php
	namespace BigTree;
	
	CSRF::verify();
	
	if (is_string($_POST["options"])) {
		$_POST["options"] = array_filter((array) json_decode($_POST["options"], true));
	}
	
	$feed = Feed::create($_POST["name"], $_POST["description"], $_POST["table"], $_POST["type"], $_POST["options"], $_POST["fields"]);
?>
<div class="container">
	<section>
		<p><?=Text::translate('Your feed is accessible at: <a href=":feed_link:">:feed_link:</a>', false, array(":feed_link:" => WWW_ROOT."feeds/".$feed->Route."/"))?></p>
	</section>
</div>