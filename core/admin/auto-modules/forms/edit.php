<?
	// Check for a page lock
	$force = isset($_GET["force"]) ? true : false;
	$admin->lockCheck($form["table"],$edit_id,"admin/auto-modules/forms/_locked.php",$force);

	$data = BigTreeAutoModule::getPendingItem($form["table"],$edit_id);
	$original_item = BigTreeAutoModule::getItem($form["table"],$edit_id);
		
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
		$view = BigTreeAutoModule::getRelatedViewForForm($form);				
		$item = $data["item"];
		
		$permission_level = $admin->getAccessLevel($module,$item,$form["table"]);
		if ($permission_level != "n") {
			$original_permission_level = $admin->getAccessLevel($module,$original_item["item"],$form["table"]);
			if ($original_permission_level != "p") {
				$permission_level = $original_permission_level;
			}
		}
		
		if (!$permission_level || $permission_level == "n") {
			include BigTree::path("admin/auto-modules/forms/_denied.php");
		} else {
			$many_to_many = $data["mtm"];
			$status = $data["status"];
			
			$tags = $data["tags"];
				
			include BigTree::path("admin/auto-modules/forms/_form.php");
		}
	}
?>
<script>
	lockTimer = setInterval("$.ajax('<?=ADMIN_ROOT?>ajax/refresh-lock/', { type: 'POST', data: { table: '<?=$form["table"]?>', id: '<?=$edit_id?>' } });",60000);
</script>