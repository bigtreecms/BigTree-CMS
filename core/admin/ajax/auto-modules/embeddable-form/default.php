<?
	$bigtree["form"]["embedded"] = true;
	if (isset($_SESSION["bigtree_admin"]["form_data"]["saved"])) {
		$bigtree["entry"] = $_SESSION["bigtree_admin"]["form_data"]["saved"];
		unset($_SESSION["bigtree_admin"]["form_data"]["saved"]);
	}
	include BigTree::path("admin/auto-modules/forms/add.php");
?>