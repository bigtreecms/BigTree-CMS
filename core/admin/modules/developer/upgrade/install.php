<?php
	namespace BigTree;
	
	/**
	 * @global Updater $updater
	 */
	
	if (!$updater->extract()) {
?>
<div class="container">
	<div class="container_summary"><h2><?=Text::translate("Upgrade BigTree")?></h2></div>
	<section>
		<p><?=Text::translate("An error occurred extracting the zip file. You can hit back to try the download again or click the ignore button below to try the auto upgrade again in a week.")?></p>
	</section>
	<footer>
		<a class="button blue" href="<?=DEVELOPER_ROOT?>upgrade/init/?type=<?=htmlspecialchars($_POST["type"])?>"><?=Text::translate("Try Again")?></a>
		<a class="button" href="<?=DEVELOPER_ROOT?>upgrade/remind/"><?=Text::translate("Remind Me Later")?></a>
	</footer>
</div>
<?php
	} else {

		// Very simple if we're updating locally
		if ($updater->Method == "Local") {
			$updater->installLocal();
			Router::redirect(DEVELOPER_ROOT."upgrade/scripts/");
		
		// If we're using FTP or SFTP we have to make sure we know where the files exist
		} else {
			// If we had to set a directory path we lost the POST
			if (!count($_POST)) {
				$_POST = $_SESSION["bigtree_admin"]["ftp"];
			}
			
			// Try to login
			if (!$updater->ftpLogin($_POST["username"], $_POST["password"])) {
				Utils::growl("Developer","Login Failed","error");
				Router::redirect(DEVELOPER_ROOT."upgrade/login/?type=".$_POST["type"]);
			}
			
			// Try to get the FTP root
			$ftp_root = $updater->getFTPRoot();
 			
			if ($ftp_root === false) {
				$_SESSION["bigtree_admin"]["ftp"] = [
					"username" => $_POST["username"],
					"password" => $_POST["password"]
				];
				$saved_root = htmlspecialchars(Setting::value("bigtree-internal-ftp-upgrade-root"));
?>
<form method="post" action="<?=DEVELOPER_ROOT?>upgrade/set-ftp-directory/">
	<?php CSRF::drawPOSTToken(); ?>
	<div class="container">
		<div class="container_summary"><h2><?=Text::translate("Upgrade BigTree")?></h2></div>
		<section>
			<p><?=Text::translate("BigTree could not automatically detect the :update_method: directory that it is installed in (or BigTree was not found in the directory entered below). Please enter the full :update_method: path below. This would be the directory that contains /core/.", false, [":update_method:" => $updater->Method])?></p>
			<hr />
			<?php if ($saved_root) { ?>
			<p class="error_message"><?=Text::translate("A BigTree installation could not be found in <code>:directory:</code>", false, [":directory:" => $saved_root])?></p>
			<?php } ?>
			<fieldset>
				<label for="ftp_field_root"><?=Text::translate(":update_method: Path", false, [":update_method:" => $updater->Method])?></label>
				<input id="ftp_field_root" type="text" name="ftp_root" value="<?=$saved_root?>" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Set :update_method: Directory", true, [":update_method:" => $updater->Method])?>" />
		</footer>
	</div>
</form>
<?php
			} else {
				$updater->installFTP($ftp_root);
				Router::redirect(DEVELOPER_ROOT."upgrade/scripts/");
			}
		}
	}
?>