<?
	include BigTree::path("inc/lib/pclzip.php");
	$zip = new PclZip(SERVER_ROOT."cache/update.zip");
	$zip->extract(PCLZIP_OPT_PATH,SERVER_ROOT."cache/update/",PCLZIP_OPT_REMOVE_PATH,"BigTree-CMS");
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
			$contents = BigTree::directoryContents(SERVER_ROOT."cache/update/");
			foreach ($contents as $file) {
				unlink($file);
			}
			rmdir(SERVER_ROOT."cache/update/");
			unlink(SERVER_ROOT."cache/update.zip");
		} else {

		}
	}
?>