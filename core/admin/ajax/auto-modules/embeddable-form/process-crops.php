<?	
	$admin->processCrops(json_decode($_POST["crop_info"],true));
	// For embedded forms we let them process crops first since we're not saving the data to the database and we'll lose their uploads otherwise
	if (count($_SESSION["bigtree_admin"]["form_data"]["errors"])) {
		BigTree::redirect($bigtree["form_root"]."error/?id=".$bigtree["form"]["id"]."&hash=".$bigtree["form"]["hash"]);
	} else {
		unset($_SESSION["bigtree_admin"]["form_data"]);
		BigTree::redirect($bigtree["form_root"]."complete/?id=".$bigtree["form"]["id"]."&hash=".$bigtree["form"]["hash"]);
	}
?>