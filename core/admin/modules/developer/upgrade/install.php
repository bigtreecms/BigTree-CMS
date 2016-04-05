<?
	if (!$updater->extract()) {
?>
<div class="container">
	<summary><h2>Upgrade BigTree</h2></summary>
	<section>
		<p>An error occurred extracting the zip file. You can hit back to try the download again or click the ignore button below to try the auto upgrade again in a week.</p>
	</section>
	<footer>
		<a class="button blue" href="<?=DEVELOPER_ROOT?>upgrade/init/?type=<?=htmlspecialchars($_POST["type"])?>">Try Again</a>
		<a class="button" href="<?=DEVELOPER_ROOT?>upgrade/remind/">Remind Me Later</a>
	</footer>
</div>
<?
	} else {

		// Very simple if we're updating locally
		if ($updater->Method == "Local") {
			$updater->installLocal();
			BigTree::redirect(DEVELOPER_ROOT."upgrade/database/");
		
		// If we're using FTP or SFTP we have to make sure we know where the files exist
		} else {
			// If we had to set a directory path we lost the POST
			if (!count($_POST)) {
				$_POST = $_SESSION["bigtree_admin"]["ftp"];
			}
			
			// Try to login
			if (!$updater->ftpLogin($_POST["username"],$_POST["password"])) {
				$admin->growl("Developer","Login Failed","error");
				BigTree::redirect(DEVELOPER_ROOT."upgrade/login/?type=".$_POST["type"]);
			}
			
			// Try to get the FTP root
			$ftp_root = $updater->getFTPRoot();
 			if ($ftp_root === false) {
				$_SESSION["bigtree_admin"]["ftp"] = array("username" => $_POST["username"],"password" => $_POST["password"]);
				$saved_root = htmlspecialchars($cms->getSetting("bigtree-internal-ftp-upgrade-root"));
?>
<form method="post" action="<?=DEVELOPER_ROOT?>upgrade/set-ftp-directory/">
	<div class="container">
		<summary><h2>Upgrade BigTree</h2></summary>
		<section>
			<p>BigTree could not automatically detect the <?=$method?> directory that it is installed in (or BigTree was not found in the directory entered below). Please enter the full <?=$method?> path below. This would be the directory that contains /core/.</p>
			<hr />
			<? if ($saved_root) { ?>
			<p class="error_message">A BigTree installation could not be found in <code><?=$saved_root?></code></p>
			<? } ?>
			<fieldset>
				<label><?=$method?> Path</label>
				<input type="text" name="ftp_root" value="<?=$saved_root?>" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Set <?=$method?> Directory" />
		</footer>
	</div>
</form>
<?
			} else {
				$updater->installFTP($ftp_root);
				BigTree::redirect(DEVELOPER_ROOT."upgrade/database/");
			}
		}
	}
?>