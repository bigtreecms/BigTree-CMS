<?php
	namespace BigTree;

	/**
	 * @global array $bigtree
	 */

	$default_position = isset($default_position) ? $default_position : "";

	$form = ModuleEmbedForm::create(end($bigtree["path"]), $_POST["title"], $_POST["table"], $_POST["fields"],
									$_POST["hooks"], $default_position, $_POST["default_pending"], $_POST["css"],
									$_POST["redirect_url"], $_POST["thank_you_message"]);

	Utils::growl("Developer","Created Embeddable Form");
?>
<div class="container">
	<section>
		<h3><?=$form->Title?></h3>
		<p><?=Text::translate("Your embeddable form has been created. You can copy and paste the code below to embed this form.")?></p>
		<textarea><?=$form->EmbedCode?></textarea>
	</section>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>modules/edit/<?=$form->Module?>/" class="button blue"><?=Text::translate("Return to Module")?></a>
	</footer>
</div>