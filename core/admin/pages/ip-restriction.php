<?php
	namespace BigTree;

	if (!empty(Router::$Path[0]) && Router::$Path[0] === "api") {
		API::triggerError("Your IP address is either banned or not in the allowed IP ranges.", "auth:error", "auth");
	}
?>
<h1><?=Text::translate("Access Denied")?></h1>
<form method="post" action="" class="module">
	<p class="error_message clear"><?=Text::translate("Your IP address is either banned or not in the allowed IP ranges.")?></p>
	<fieldset>
		<p><?=Text::translate("Please contact your webmaster if you believe this is in error.")?></p>
	</fieldset>
	<br />
</form>