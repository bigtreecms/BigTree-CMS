<?
	$parent = end($path);

	$r = $admin->getPageAccessLevel($parent); 
	if ($r == "p") {
		$publisher = true;
	} elseif ($r == "e") {
		$publisher = false;
	} else {
		die("You do not have access to this page.");
	}
	
	$tags = array();
?>
<h1><span class="add_page"></span>Add Page</h1>
<?
	include BigTree::path("admin/modules/pages/_nav.php");
	$action = "create";
	include BigTree::path("admin/modules/pages/_form.php");
?>