<?
	header("Content-type: text/javascript");
	
	$user = $admin->getUserByEmail($_POST["user"]);
	if (!$user) {
		die('BigTree.Growl("Password Request","No user was found for the email address you provided.");');
	}
	
	$hash = $admin->setPasswordHashForUser($user);
	
	$change_link = ADMIN_ROOT."change-password/$hash/";
	
	mail($f["email"],$site["title"]." Password Change Request","Hello ".$f["name"].",\n\nTo change your password for the ".$site["title"]." Admin, please click the link below.\n\n".$change_link."\n\n-- BigTree CMS --","From: no-reply@".str_replace(array("http://","www."),"",DOMAIN));
?>
BigTree.Growl("Password Change","Instructions have been emailed to you.");