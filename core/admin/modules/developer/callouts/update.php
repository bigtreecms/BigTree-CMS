<?php
	namespace BigTree;

	Globalize::POST();

	$callout = new Callout($id);
	$callout->update($name,$description,$level,$fields,$display_field,$display_default);

	Utils::growl("Developer","Updated Callout");

	Router::redirect(DEVELOPER_ROOT."callouts/");
	