<?
	// Verify zip integrity
	if (!$updater->checkZip()) {
?>
<div class="container">
	<summary><h2>Upgrade Extension</h2></summary>
	<section>
		<p>An error occurred extracting the zip file. You can hit back to try the download again or click the ignore button below to try the auto upgrade again in a week.</p>
	</section>
	<footer>
		<a class="button blue" href="<?=$page_link.$page_vars?>">Try Again</a>
		<a class="button" href="<?=DEVELOPER_ROOT?>extensions/">Return to Extensions List</a>
	</footer>
</div>
<?
	} else {
		// If we're not using local install and the config settings only allow for HTTPS logins, redirect
		$secure = (!empty($_SERVER["HTTPS"]) && $_SERVER['HTTPS'] !== "off" || $_SERVER["SERVER_PORT"] == 443);
		if ($updater->Method != "Local" && $bigtree["config"]["force_secure_login"] && !$secure) {
			BigTree::redirect(str_replace("http://","https://",$page_link)."check-file/".$page_vars);
		}		
?>
<form method="post" action="<?=$page_link?>process/<?=$page_vars?>">
	<div class="container">
		<summary><h2>Upgrade Extension</h2></summary>
		<section>
			<? if ($updater->Method == "Local") { ?>
			<p>The upgrade file finished downloading and your file permissions allow for local install.</p>
			<p>Your existing extension folder will be backed up in /backups/extensions/<?=htmlspecialchars($_GET["id"])?>/</p>
			<? } else { ?>
			<p>The upgrade file has finished downloading but the web server can not write directly to the root or /core/ folder. You'll need to enter your <strong><?=$updater->Method?></strong> credentials below so that BigTree can upgrade.</p>
			<p>Your existing extension folder will be backed up in /backups/extensions/<?=htmlspecialchars($_GET["id"])?>/</p>
			<hr />
			<fieldset>
				<label><?=$updater->Method?> Username</label>
				<input type="text" name="username" autocomplete="off" />
			</fieldset>
			<fieldset>
				<label><?=$updater->Method?> Password</label>
				<input type="password" name="password" autocomplete="off" />
			</fieldset>
			<? } ?>
		</section>
		<footer>
			<input type="submit" class="blue" value="Install" />
		</footer>
	</div>
</form>
<?	
	}
?>