<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 * @global string $page_link
	 * @global string $page_vars
	 * @global Updater $updater
	 */
	
	// Verify zip integrity
	if (!$updater->checkZip()) {
?>
<div class="container">
	<div class="container_summary"><h2><?=Text::translate("Upgrade Extension")?></h2></div>
	<section>
		<p><?=Text::translate("An error occurred extracting the zip file. You can hit back to try the download again or click the ignore button below to try the auto upgrade again in a week.")?></p>
	</section>
	<footer>
		<a class="button blue" href="<?=$page_link.$page_vars?>"><?=Text::translate("Try Again")?></a>
		<a class="button" href="<?=DEVELOPER_ROOT?>extensions/"><?=Text::translate("Return to Extensions List")?></a>
	</footer>
</div>
<?php
	} else {
		// If we're not using local install and the config settings only allow for HTTPS logins, redirect
		if ($updater->Method != "Local" && $bigtree["config"]["force_secure_login"] && !Router::getIsSSL()) {
			Router::redirect(str_replace("http://","https://",$page_link)."check-file/".$page_vars);
		}		
?>
<form method="post" action="<?=$page_link?>process/<?=$page_vars?>">
	<div class="container">
		<div class="container_summary"><h2><?=Text::translate("Upgrade Extension")?></h2></div>
		<section>
			<?php if ($updater->Method == "Local") { ?>
			<p><?=Text::translate("The upgrade file finished downloading and your file permissions allow for local install.")?></p>
			<p><?=Text::translate("Your existing extension folder will be backed up in /backups/extensions/:extension_id:/", false, [":extension_id:" => htmlspecialchars($_GET["id"])])?></p>
			<?php } else { ?>
			<p><?=Text::translate("The upgrade file has finished downloading but the web server can not write directly to the root or /core/ folder. You'll need to enter your <strong>:file_access_method:</strong> credentials below so that BigTree can upgrade.", false, [":file_access_method:" => $updater->Method])?></p>
			<p><?=Text::translate("Your existing extension folder will be backed up in /backups/extensions/:extension_id:/", false, [":extension_id:" => htmlspecialchars($_GET["id"])])?></p>
			<hr />
			<fieldset>
				<label for="ftp_field_username"><?=Text::translate(":file_access_method: Username", false, [":file_access_method:" => $updater->Method])?></label>
				<input id="ftp_field_username" type="text" name="username" autocomplete="off" />
			</fieldset>
			<fieldset>
				<label for="ftp_field_password"><?=Text::translate(":file_access_method: Password", false, [":file_access_method:" => $updater->Method])?></label>
				<input id="ftp_field_password" type="password" name="password" autocomplete="off" />
			</fieldset>
			<?php } ?>
		</section>
		<footer>
			<input type="submit" class="blue" value="<?=Text::translate("Install", true)?>" />
		</footer>
	</div>
</form>
<?php
	}
?>