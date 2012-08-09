<h1><span class="modules"></span>Edit <?=$form["title"]?></h1>
<?
	include BigTree::path("admin/auto-modules/_nav.php");
	$item_id = end($bigtree["path"]);
	
	// Check for a page lock
	$force = isset($_GET["force"]) ? $_GET["force"] : false;
	$admin->lockCheck($form["table"],$item_id,"admin/auto-modules/forms/_locked.php",$force);

	$data = BigTreeAutoModule::getPendingItem($form["table"],$item_id);
		
	if (!$data) {
?>
<h1><span class="error"></span>Error</h1>
<p class="error">The item you are trying to edit no longer exists.</p>
<?
	} else {
		$view = BigTreeAutoModule::getRelatedViewForForm($form);				
		$item = $data["item"];
		
		$permission_level = $admin->getAccessLevel($module,$item,$form["table"]);
		
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
<script type="text/javascript">
	lockTimer = setInterval("$.ajax('<?=ADMIN_ROOT?>ajax/refresh-lock/', { type: 'POST', data: { table: '<?=$form["table"]?>', id: '<?=$item_id?>' } });",60000);
</script>