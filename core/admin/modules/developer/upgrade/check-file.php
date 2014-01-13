<?
	include BigTree::path("inc/lib/pclzip.php");
	$zip = new PclZip(SERVER_ROOT."cache/update.zip");
	$zip->listContent();
	if ($zip->errorName() != "PCLZIP_ERR_NO_ERROR") {
?>
<div class="container">
	<summary><h2>Upgrade BigTree</h2></summary>
	<section>
		<p>An error occurred extracting the zip file. You can hit back to try the download again or click the ignore button below to try the auto upgrade again in a week.</p>
	</section>
	<footer>
		<a class="button blue" href="<?=DEVELOPER_ROOT?>upgrade/init/?type=<?=htmlspecialchars($_GET["type"])?>">Try Again</a>
		<a class="button" href="<?=DEVELOPER_ROOT?>upgrade/remind/">Remind Me Later</a>
	</footer>
</div>
<?
	} else {
		// See if we can write to the root directory
		if (is_writable(SERVER_ROOT) && is_writable(SERVER_ROOT."core/")) {
			$writable = true;
		} else {
			$writable = false;
		}

		$secure = (!empty($_SERVER["HTTPS"]) && $_SERVER['HTTPS'] !== "off" || $_SERVER["SERVER_PORT"] == 443);
		if (!$writable && $bigtree["config"]["force_secure_login"] && !$secure) {
			BigTree::redirect(str_replace("http://","https://",DEVELOPER_ROOT)."upgrade/check-file/");
		}
?>
<form method="post" action="<?=DEVELOPER_ROOT?>upgrade/install/">
	<input type="hidden" name="method" value="<? if ($writable) { ?>local<? } else { ?>ftp<? } ?>" />
	<input type="hidden" name="type" value="<?=htmlspecialchars($_GET["type"])?>" />
	<div class="container">
		<summary><h2>Upgrade BigTree</h2></summary>
		<section>
			<? if ($writable) { ?>
			<p>The upgrade file finished downloading and your file permissions allow for local install.</p>
			<ul>
				<li>Your existing /core/ folder will be backed up in /backups/core-<?=BIGTREE_VERSION?>/</li>
				<li>Your existing database will be backed up as /backups/core-<?=BIGTREE_VERSION?>/backup.sql</li>
			</ul>
			<? } else { ?>
			<p>The upgrade file has finished downloading but the web server can not write directly to the root or /core/ folder. You'll need to enter your FTP credentials below so that BigTree can upgrade.</p>
			<ul>
				<li>Your existing /core/ folder will be backed up in /backups/core-<?=BIGTREE_VERSION?>/</li>
				<li>Your existing database will be backed up as /backups/core-<?=BIGTREE_VERSION?>/backup.sql</li>
			</ul>
			<hr />
			<fieldset>
				<label>FTP Username</label>
				<input type="text" name="username" autocomplete="off" />
			</fieldset>
			<fieldset>
				<label>FTP Password</label>
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