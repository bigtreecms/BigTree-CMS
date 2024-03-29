<?php
	// Check for a page lock
	if (!empty($_GET["force"])) {
		$admin->verifyCSRFToken();
		$force = true;
	} else {
		$force = false;
	}
	
	$admin->lockCheck($bigtree["form"]["table"],$bigtree["edit_id"],"admin/auto-modules/forms/_locked.php",$force);
	
	$pending_entry = BigTreeAutoModule::getPendingItem($bigtree["form"]["table"],$bigtree["edit_id"]);
	$original_item = BigTreeAutoModule::getItem($bigtree["form"]["table"],$bigtree["edit_id"]);
		
	if (!$pending_entry) {
?>
<div class="container">
	<section>
		<h3>Error</h3>
		<p>The item you are trying to edit no longer exists.</p>
	</section>
</div>
<?php
	} else {
		$bigtree["entry"] = $item = $pending_entry["item"];
		
		// See if we have an editing hook
		if (!empty($bigtree["form"]["hooks"]["edit"])) {
			$bigtree["entry"] = call_user_func($bigtree["form"]["hooks"]["edit"], $bigtree["entry"], $bigtree["form"], false);
		}

		// Check access levels
		$bigtree["access_level"] = $admin->getAccessLevel($bigtree["module"],$item,$bigtree["form"]["table"]);
		
		if ($bigtree["access_level"] != "n") {
			$original_permission_level = $admin->getAccessLevel($bigtree["module"],$original_item["item"],$bigtree["form"]["table"]);
			if ($original_permission_level != "p") {
				$bigtree["access_level"] = $original_permission_level;
			}
		}
		
		if (empty($bigtree["access_level"]) || $bigtree["access_level"] == "n") {
			include BigTree::path("admin/auto-modules/forms/_denied.php");
		} else {
			$bigtree["many-to-many"] = $many_to_many = $pending_entry["mtm"];
			$bigtree["tags"] = $pending_entry["tags"];
				
			include BigTree::path("admin/auto-modules/forms/_form.php");
		}
	}
?>
<script>
	BigTree.localLockTimer = setInterval("$.secureAjax('<?=ADMIN_ROOT?>ajax/refresh-lock/', { type: 'POST', data: { table: '<?=$bigtree["form"]["table"]?>', id: '<?=$bigtree["edit_id"]?>' } });",60000);
</script>