<?	
	include BigTree::path("inc/lib/pclzip.php");
	if (!file_exists(SERVER_ROOT."cache/update/")) {
		mkdir(SERVER_ROOT."cache/update/");
		chmod(SERVER_ROOT."cache/update/",0777);
	}
	$zip = new PclZip(SERVER_ROOT."cache/update.zip");
	$zip->extract(PCLZIP_OPT_PATH,SERVER_ROOT."cache/update/",PCLZIP_OPT_REMOVE_PATH,"BigTree-CMS");
	// Make sure everything extracted is 777.
	$contents = BigTree::directoryContents(SERVER_ROOT."cache/update/");
	foreach ($contents as $c) {
		chmod($c,0777);
	}

	if ($zip->errorName() != "PCLZIP_ERR_NO_ERROR") {
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
		if ($_POST["method"] == "local") {
			// Create backups folder
			if (!file_exists(SERVER_ROOT."backups/")) {
				mkdir(SERVER_ROOT."backups/");
				chmod(SERVER_ROOT."backups/",0777);
			}
			// Move old core
			rename(SERVER_ROOT."core/",SERVER_ROOT."backups/core-".BIGTREE_VERSION."/");
			// Backup database
			$admin->backupDatabase(SERVER_ROOT."backups/core-".BIGTREE_VERSION."/backup.sql");
			// Move new core into place
			rename(SERVER_ROOT."cache/update/core/",SERVER_ROOT."core/");
			// Delete old files
			$contents = array_reverse(BigTree::directoryContents(SERVER_ROOT."cache/update/"));
			foreach ($contents as $file) {
				if (is_dir($file)) {
					rmdir($file);	
				} else {
					unlink($file);
				}
			}
			rmdir(SERVER_ROOT."cache/update/");
			unlink(SERVER_ROOT."cache/update.zip");
			BigTree::redirect(DEVELOPER_ROOT."upgrade/database/");
		} else {
			// If we had to set a directory path we lost the POST
			if (!count($_POST)) {
				$_POST = $_SESSION["bigtree_admin"]["ftp"];
			}
			
			// Make sure FTP login works
			$ftp = new BigTreeFTP;
			if (!$ftp->connect("localhost")) {
				BigTree::redirect(DEVELOPER_ROOT."upgrade/failed/");
			}
			if (!$ftp->login($_POST["username"],$_POST["password"])) {
				$admin->growl("Developer","FTP Login Failed","error");
				BigTree::redirect(DEVELOPER_ROOT."upgrade/login/?type=".$_POST["type"]);
			}
			// Try to determine the FTP root.
			$ftp_root = false;
			if ($admin->settingExists("bigtree-internal-ftp-upgrade-root") && $ftp->changeDirectory($cms->getSetting("bigtree-internal-ftp-upgrade-root")."core/inc/bigtree/")) {
				$ftp_root = $cms->getSetting("bigtree-internal-ftp-upgrade-root");
			} elseif ($ftp->changeDirectory(SERVER_ROOT."inc/bigtree/")) {
				$ftp_root = SERVER_ROOT;
			} elseif ($ftp->changeDirectory("/core/inc/bigtree")) {
				$ftp_root = "/";
			} elseif ($ftp->changeDirectory("/httpdocs/core/inc/bigtree")) {
				$ftp_root = "/httpdocs";
			} elseif ($ftp->changeDirectory("/public_html/core/inc/bigtree")) {
				$ftp_root = "/public_html";
			} elseif ($ftp->changeDirectory("/".str_replace(array("http://","https://"),"",DOMAIN)."inc/bigtree/")) {
				$ftp_root = "/".str_replace(array("http://","https://"),"",DOMAIN);
			}
			
			if ($ftp_root === false) {
				$_SESSION["bigtree_admin"]["ftp"] = array("username" => $_POST["username"],"password" => $_POST["password"]);
?>
<form method="post" action="<?=DEVELOPER_ROOT?>upgrade/set-ftp-directory/">
	<div class="container">
		<summary><h2>Upgrade BigTree</h2></summary>
		<section>
			<p>BigTree could not automatically detect the FTP directory that it is installed in (or BigTree was not found in the directory entered below). Please enter the full FTP path below. This would be the directory that contains /core/.</p>
			<hr />
			<fieldset>
				<label>FTP Path</label>
				<input type="text" name="ftp_root" value="<?=htmlspecialchars($cms->getSetting("bigtree-internal-ftp-upgrade-root"))?>" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Set FTP Directory" />
		</footer>
	</div>
</form>
<?
			} else {
				$ftp_root = "/".trim($ftp_root,"/")."/";
				// Create backups folder
				$ftp->createDirectory($ftp_root."backups/");
				// Move old core
				$ftp->rename($ftp_root."core/",$ftp_root."backups/core-".BIGTREE_VERSION."/");
				// Backup database
				$admin->backupDatabase(SERVER_ROOT."cache/backup.sql");
				$ftp->rename($ftp_root."cache/backup.sql",$ftp_root."backups/core-".BIGTREE_VERSION."/backup.sql");
				// Move new core into place
				$ftp->rename($ftp_root."cache/update/core/",$ftp_root."core/");
				// Delete old files
				$contents = array_reverse(BigTree::directoryContents(SERVER_ROOT."cache/update/"));
				foreach ($contents as $file) {
					if (is_dir($file)) {
						rmdir($file);	
					} else {
						unlink($file);
					}
				}
				rmdir(SERVER_ROOT."cache/update/");
				unlink(SERVER_ROOT."cache/update.zip");
				BigTree::redirect(DEVELOPER_ROOT."upgrade/database/");
			}
		}
	}
?>