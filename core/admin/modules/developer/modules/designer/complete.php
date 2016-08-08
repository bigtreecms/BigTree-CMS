<?php
	namespace BigTree;

	$module = new Module($_GET["module"]);
?>
<div class="container">
	<summary>
		<h2><?=Text::translate("Module Complete")?></h2>
	</summary>
	<section>
		<p><?=Text::translate('Your module is created.  You may access it <a href=":module_link:">by clicking here</a>.', false, array(":module_link:" => ADMIN_ROOT.$module->Route."/"))?></p>
	</section>
</div>