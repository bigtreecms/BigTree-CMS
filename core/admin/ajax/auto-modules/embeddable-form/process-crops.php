<?	
	$admin->processCrops(json_decode($_POST["crop_info"],true));
	unset($_SESSION["bigtree_admin"]["form_data"]);
	BigTree::redirect($bigtree["form_root"]."complete/?hash=".$bigtree["form"]["hash"]);
?>