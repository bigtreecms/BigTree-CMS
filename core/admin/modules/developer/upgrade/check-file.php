<?php
	namespace BigTree;
	
	// Verify zip integrity
	if (!$updater->checkZip()) {
?>
<div class="container">
	<div class="container_summary"><h2><?=Text::translate("Upgrade BigTree")?></h2></div>
	<section>
		<p><?=Text::translate("An error occurred extracting the zip file. You can hit back to try the download again or click the ignore button below to try the auto upgrade again in a week.")?></p>
	</section>
	<footer>
		<a class="button blue" href="<?=DEVELOPER_ROOT?>upgrade/init/?type=<?=htmlspecialchars($_GET["type"])?>"><?=Text::translate("Try Again")?></a>
		<a class="button" href="<?=DEVELOPER_ROOT?>upgrade/remind/"><?=Text::translate("Remind Me Later")?></a>
	</footer>
</div>
<?php
	} else {
		// If we're not using local install and the config settings only allow for HTTPS logins, redirect
		$secure = (!empty($_SERVER["HTTPS"]) && $_SERVER['HTTPS'] !== "off" || $_SERVER["SERVER_PORT"] == 443);
		if ($updater->Method != "Local" && $bigtree["config"]["force_secure_login"] && !$secure) {
			Router::redirect(str_replace("http://","https://",DEVELOPER_ROOT)."upgrade/check-file/");
		}		
?>
<form method="post" action="<?=DEVELOPER_ROOT?>upgrade/install/">
	<input type="hidden" name="type" value="<?=htmlspecialchars($_GET["type"])?>" />
	<div class="container">
		<div class="container_summary"><h2><?=Text::translate("Upgrade BigTree")?></h2></div>
		<section>
			<?php if ($updater->Method == "Local") { ?>
			<p><?=Text::translate("The upgrade file finished downloading and your file permissions allow for local install.")?></p>
			<ul>
				<li><?=Text::translate("Your existing /core/ folder will be backed up in /backups/core-:version:/", false, array(":version:" => BIGTREE_VERSION))?></li>
				<li><?=Text::translate("Your existing database will be backed up as /backups/core-:version:/backup.sql", false, array(":version:" => BIGTREE_VERSION))?></li>
			</ul>
			<?php } else { ?>
			<p><?=Text::translate("The upgrade file has finished downloading but the web server can not write directly to the root or /core/ folder. You'll need to enter your <strong>:update_method:</strong> credentials below so that BigTree can upgrade.", false, array(":update_method" => $updater->Method))?></p>
			<ul>
				<li><?=Text::translate("Your existing /core/ folder will be backed up in /backups/core-:version:/", false, array(":version:" => BIGTREE_VERSION))?></li>
				<li><?=Text::translate("Your existing database will be backed up as /backups/core-:version:/backup.sql", false, array(":version:" => BIGTREE_VERSION))?></li>
			</ul>
			<hr />
			<fieldset>
				<label><?=Text::translate(":update_method: Username", false, array(":update_method:" => $updater->Method))?></label>
				<input type="text" name="username" autocomplete="off" />
			</fieldset>
			<fieldset>
				<label><?=Text::translate(":update_method: Password", false, array(":update_method:" => $updater->Method))?></label>
				<input type="password" name="password" autocomplete="off" />
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