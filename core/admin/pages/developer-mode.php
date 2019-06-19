<?php
	namespace BigTree;

	Router::setLayout("login");
	$site = new Page(0, false);
?>
<form method="post" action="" class="maintenance">
	<fieldset>
		<h2><span class="icon_medium_vitals"></span><?=Text::translate("Maintenance Underway")?></h2>
		<p class="notice"><?=Text::translate("We are currently undergoing site maintenance. If your need is urgent, please contact your webmaster.")?></p>
	</fieldset>
</form>