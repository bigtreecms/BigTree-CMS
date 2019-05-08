<?php
	namespace BigTree;

	/**
	 * @global string $edit_id
	 * @global ModuleForm $form
	 * @global Module $module
	 */
	
	// Check for a page lock
	if (!empty($_GET["force"])) {
		CSRF::verify();
		$force = true;
	} else {
		$force = false;
	}
	
	Lock::enforce($form->Table, $edit_id, "admin/auto-modules/forms/_locked.php", $force);

	$pending_entry = $form->getPendingEntry($edit_id);
	$original_item = $form->getEntry($edit_id);

	if (!$pending_entry) {
?>
<div class="container">
	<section>
		<h3><?=Text::translate("Error")?></h3>
		<p><?=Text::translate("The item you are trying to edit no longer exists.")?></p>
	</section>
</div>
<?php
	} else {
		$content = $pending_entry["item"];
		
		// See if we have an editing hook
		if (!empty($form->Hooks["edit"])) {
			$content = call_user_func($form->Hooks["edit"], $content, $form->Array, false);
		}
		
		// Check access levels
		$access_level = Auth::user()->getAccessLevel($module, $item, $form->Table);

		if ($access_level != "n") {
			$original_permission_level = Auth::user()->getAccessLevel($module, $original_item["item"], $form->Table);

			if ($original_permission_level != "p") {
				$access_level = $original_permission_level;
			}
		}
		
		if (!$access_level || $access_level == "n") {
			include Router::getIncludePath("admin/auto-modules/forms/_denied.php");
		} else {
			$many_to_many = $pending_entry["mtm"];
			$tags = $pending_entry["tags"];

			include Router::getIncludePath("admin/auto-modules/forms/_form.php");
		}
	}
?>
<script>
	BigTree.localLockTimer = setInterval(function() {
		$.secureAjax("<?=ADMIN_ROOT?>ajax/refresh-lock/", {
			type: 'POST',
			data: { table: '<?=$form->Table?>', id: '<?=$edit_id?>'
		}});
	}, 60000);
</script>