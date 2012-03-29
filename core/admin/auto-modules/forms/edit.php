<h1><span class="modules"></span>Edit <?=$form["title"]?></h1>
<?
	include BigTree::path("admin/auto-modules/_nav.php");
	$item_id = end($path);
	
	// Check for a page lock
	$admin->lockCheck($form["table"],$item_id,"admin/modules/forms/_locked.php",$_GET["force"]);

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
	    	$pending_resources = $data["resources"] ? $data["resources"] : array();
	    	
	    	$tags = $data["tags"];
	    		
	    	include BigTree::path("admin/auto-modules/forms/_form.php");
	    }
	}
?>