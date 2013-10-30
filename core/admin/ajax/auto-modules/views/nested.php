<?
	if ((isset($_POST["search"]) && $_POST["search"]) || (isset($_GET["search"]) && $_GET["search"])) {
		include "draggable.php";
	} else {
		if (isset($_POST["view"])) {
			$bigtree["view"] = BigTreeAutoModule::getView($_POST["view"]);
		}
	
		$module_id = BigTreeAutoModule::getModuleForView($bigtree["view"]);
		$module = $admin->getModule($module_id);
		$mpage = ADMIN_ROOT.$module["route"]."/";
		$permission = $admin->getAccessLevel($module_id);
	
		// Edit Suffix
		$suffix = $bigtree["view"]["suffix"] ? "-".$bigtree["view"]["suffix"] : "";
		
		// Setup the preview action if we have a preview URL and field.
		if ($bigtree["view"]["preview_url"]) {
			$bigtree["view"]["actions"]["preview"] = "on";
		}

		function _localDrawLevel($items,$depth,$open = false) {

		}
	
		$items = BigTreeAutoModule::getViewDataForGroup($bigtree["view"],"position DESC, id ASC","both",$module);
?>
<div class="table nested_table">

</div>
<?
	}
?>