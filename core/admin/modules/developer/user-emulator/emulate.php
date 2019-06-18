<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();
	
	$user = new User($_GET["id"]);

	if (empty($user->ID)) {
?>
<div class="container">
	<section>
		<h3><?=Text::translate("Error")?></h3>
		<p><?=Text::translate("The user you are trying to emulate no longer exists.")?></p>
	</section>
</div>
<?php
	} else {
		$_SESSION["bigtree_admin"]["id"] = $user->ID;
		$_SESSION["bigtree_admin"]["email"] = $user->Email;
		$_SESSION["bigtree_admin"]["level"] = $user->Level;
		$_SESSION["bigtree_admin"]["name"] = $user->Name;
		$_SESSION["bigtree_admin"]["permissions"] = $user->Permissions;

		Admin::growl("Developer","Emulating ".$user->Name);
		Router::redirect(ADMIN_ROOT);
	}
