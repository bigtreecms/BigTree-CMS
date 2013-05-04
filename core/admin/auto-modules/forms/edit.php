<?
	// Check for a page lock
	$force = isset($_GET["force"]) ? true : false;
	$admin->lockCheck($bigtree["form"]["table"],$bigtree["edit_id"],"admin/auto-modules/forms/_locked.php",$force);

	$data = BigTreeAutoModule::getPendingItem($bigtree["form"]["table"],$bigtree["edit_id"]);
	$original_item = BigTreeAutoModule::getItem($bigtree["form"]["table"],$bigtree["edit_id"]);
		
	if (!$data) {
?>
<div class="container">
	<section>
		<h3>Error</h3>
		<p>The item you are trying to edit no longer exists.</p>
	</section>
</div>
<?
	} else {
		$bigtree["related_view"] = BigTreeAutoModule::getRelatedViewForForm($bigtree["form"]);				
		$bigtree["item"] = $item = $data["item"];

		// Check access levels
		$bigtree["access_level"] = $admin->getAccessLevel($bigtree["current_module"],$item,$bigtree["form"]["table"]);
		if ($bigtree["access_level"] != "n") {
			$original_permission_level = $admin->getAccessLevel($bigtree["current_module"],$original_item["item"],$bigtree["form"]["table"]);
			if ($original_permission_level != "p") {
				$bigtree["access_level"] = $original_permission_level;
			}
		}
		
		if (!$bigtree["access_level"] || $bigtree["access_level"] == "n") {
			include BigTree::path("admin/auto-modules/forms/_denied.php");
		} else {
			$bigtree["many-to-many"] = $many_to_many = $data["mtm"];
			$bigtree["tags"] = $data["tags"];
				
			include BigTree::path("admin/auto-modules/forms/_form.php");
		}
	}
?>
<script>
	BigTree.localLockTimer = setInterval("$.ajax('<?=ADMIN_ROOT?>ajax/refresh-lock/', { type: 'POST', data: { table: '<?=$bigtree["form"]["table"]?>', id: '<?=$bigtree["edit_id"]?>' } });",60000);
</script>