<?php
	namespace BigTree;

	/**
	 * @global array $bigtree
	 * @global ModuleForm $form
	 * @global Module $module
	 */
	
	// Check for a page lock
	$force = isset($_GET["force"]) ? true : false;
	Lock::enforce($form->Table, $bigtree["edit_id"], "admin/auto-modules/forms/_locked.php", $force);

	$pending_entry = $form->getPendingEntry($bigtree["edit_id"]);
	$original_item = $form->getEntry($bigtree["edit_id"]);

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
		$bigtree["related_view"] = $form->RelatedModuleView;
		$bigtree["entry"] = $item = $pending_entry["item"];
		
		// See if we have an editing hook
		if (!empty($form->Hooks["edit"])) {
			$bigtree["entry"] = call_user_func($form->Hooks["edit"], $bigtree["entry"], $form->Array);
		}
		
		// Check access levels
		$bigtree["access_level"] = Auth::user()->getAccessLevel($module, $item, $form->Table);

		if ($bigtree["access_level"] != "n") {
			$original_permission_level = Auth::user()->getAccessLevel($module, $original_item["item"], $form->Table);

			if ($original_permission_level != "p") {
				$bigtree["access_level"] = $original_permission_level;
			}
		}
		
		if (!$bigtree["access_level"] || $bigtree["access_level"] == "n") {
			include Router::getIncludePath("admin/auto-modules/forms/_denied.php");
		} else {
			$bigtree["many-to-many"] = $many_to_many = $pending_entry["mtm"];
			$bigtree["tags"] = $pending_entry["tags"];

			include Router::getIncludePath("admin/auto-modules/forms/_form.php");
		}
	}
?>
<script>
	BigTree.localLockTimer = setInterval(function() {
		$.secureAjax("<?=ADMIN_ROOT?>ajax/refresh-lock/", {
			type: 'POST',
			data: { table: '<?=$bigtree["form"]["table"]?>', id: '<?=$bigtree["edit_id"]?>'
		}});
	}, 60000);
</script>