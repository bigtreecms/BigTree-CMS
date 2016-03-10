<?php
	BigTree::globalizePOSTVars();

	if (!empty($group_new)) {
		$new_group = BigTree\CalloutGroup::create($group_new);
		$group_id = $new_group->ID;
	} else {
		$group_id = $group_existing;
	}

	$callout = new BigTree\Callout($id);
	$callout->update($name,$description,$level,$fields,$display_field,$display_default);

	$admin->growl("Developer","Updated Callout");
	BigTree::redirect(DEVELOPER_ROOT."callouts/");