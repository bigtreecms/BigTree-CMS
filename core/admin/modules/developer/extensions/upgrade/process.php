<?
	$installed = false;

	if (!$updater->extract()) {
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
		// Save original manifest, prevent path manipulation
		$id = BigTree::cleanFile($_GET["id"]);
		$original_manifest = json_decode(file_get_contents(SERVER_ROOT."extensions/$id/manifest.json"),true);
		
		// Very simple if we're updating locally
		if ($updater->Method == "Local") {
			$updater->installLocal();
			$installed = true;

		// If we're using FTP or SFTP we have to make sure we know where the files exist
		} else {
			// If we had to set a directory path we lost the POST
			if (!count($_POST)) {
				$_POST = $_SESSION["bigtree_admin"]["ftp"];
			}
			
			// Try to login
			if (!$updater->ftpLogin($_POST["username"],$_POST["password"])) {
				$admin->growl("Developer","Login Failed","error");
				BigTree::redirect(DEVELOPER_ROOT."extensions/upgrade/check-file/?id=".$_GET["id"]);
			}
			
			// Try to get the FTP root
			$ftp_root = $updater->getFTPRoot();
 			if ($ftp_root === false) {
				$_SESSION["bigtree_admin"]["ftp"] = array("username" => $_POST["username"],"password" => $_POST["password"]);
?>
<form method="post" action="<?=$page_link?>set-ftp-directory/<?=$page_vars?>">
	<div class="container">
		<summary><h2>Upgrade Extension</h2></summary>
		<section>
			<p>BigTree could not automatically detect the <?=$method?> directory that it is installed in (or BigTree was not found in the directory entered below). Please enter the full <?=$method?> path below. This would be the directory that contains /core/.</p>
			<hr />
			<fieldset>
				<label><?=$method?> Path</label>
				<input type="text" name="ftp_root" value="<?=htmlspecialchars($cms->getSetting("bigtree-internal-ftp-upgrade-root"))?>" />
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
				$installed = true;
			}
		}

		if ($installed) {
			// Install/replace existing extension
			$manifest = json_decode(file_get_contents(SERVER_ROOT."extensions/$id/manifest.json"),true);
			$admin->installExtension($manifest,$original_manifest);

			// If we have an update.php file, run it. We're catching the output buffer to see if update.php has anything to show -- if it doesn't, we'll redirect to the complete screen.
			$update_file_path = SERVER_ROOT."extensions/$id/update.php";
			if (file_exists($update_file_path)) {
				ob_clean();
				include $update_file_path;
				$ob_contents = ob_get_contents();
				// If the update file didn't generate any markup, just move on to the completion screen
				if (!$ob_contents) {
					BigTree::redirect($page_link."complete/".$page_vars);
				}
			// No update file, completion screen
			} else {
				BigTree::redirect($page_link."complete/".$page_vars);
			}
		}
	}
?>