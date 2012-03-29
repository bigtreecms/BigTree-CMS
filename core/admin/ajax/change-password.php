<?
	header("Content-type: text/javascript");
	
	$user = $admin->getUserByEmail($_POST["user"]);
	if (!$user) {
		die('BigTree.growl("Password Request","No user was found for the email address you provided.");');
	}
	
	$hash = $admin->setPasswordHashForUser($user);
	
	$change_link = $admin_root."change-password/$hash/";
	
	mail($f["email"],$site["title"]." Password Change Request","Hello ".$f["name"].",\n\nTo change your password for the ".$site["title"]." Admin, please click the link below.\n\n".$change_link."\n\n-- BigTree CMS --","From: no-reply@".str_replace(array("http://","www."),"",$config["domain"]));
?>
BigTree.growl("Password Change","Instructions have been emailed to you.");