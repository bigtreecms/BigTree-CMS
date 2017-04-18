<?php
	namespace BigTree;

	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();

	$form = new ModuleEmbedForm(end($bigtree["path"]));
	$form->update($_POST["title"], $_POST["table"], $_POST["fields"], $_POST["hooks"], $_POST["default_position"],
				  $_POST["default_pending"], $_POST["css"], $_POST["redirect_url"], $_POST["thank_you_message"]);

	Utils::growl("Developer","Updated Embeddable Form");
	Router::redirect(DEVELOPER_ROOT."modules/edit/".$form->Module."/");
	