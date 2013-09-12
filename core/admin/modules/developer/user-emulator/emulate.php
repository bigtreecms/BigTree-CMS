<?
	$user = $admin->getUser(end($bigtree["commands"]));
	if (!$user) {
?>
<div class="container">
	<section>
		<h3>Error</h3>
		<p>The user you are trying to emulate no longer exists.</p>
	</section>
</div>
<?
	} else {
		$_SESSION["bigtree_admin"]["id"] = $user["id"];
		$_SESSION["bigtree_admin"]["email"] = $user["email"];
		$_SESSION["bigtree_admin"]["level"] = $user["level"];
		$_SESSION["bigtree_admin"]["name"] = $user["name"];
		$_SESSION["bigtree_admin"]["permissions"] = $user["permissions"];
		$admin->growl("Developer","Emulating ".$user["name"]);
		BigTree::redirect(ADMIN_ROOT);
	}
?>