<?php
	namespace BigTree;

	\BigTree::globalizePOSTVars();

	$callout = new Callout($id);
	$callout->update($name,$description,$level,$fields,$display_field,$display_default);

	$admin->growl("Developer","Updated Callout");

	Router::redirect(DEVELOPER_ROOT."callouts/");
	