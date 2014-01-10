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
		<a class="button blue" href="<?=DEVELOPER_ROOT?>upgrade/remind/">Remind Me Later</a>
	</footer>
</div>
<?
	} else {
		// See if we can write to core
		$files = BigTree::directoryContents(SERVER_ROOT."core/");
		$writable = true;
		foreach ($files as $file) {
			if (!is_writable($file)) {
				$writable = false;
			}
		}
?>
<form method="post" action="<?=DEVELOPER_ROOT?>upgrade/<? if ($writable) { ?>extract<? } else { ?>check-ftp/<? } ?>">
	<div class="container">
		<summary><h2>Upgrade BigTree</h2></summary>
		<section>
			<? if ($writable) { ?>
			<p>The upgrade file finished downloading and your file permissions allow for local install. You may want to backup your database before proceeding.</p>
			<? } else { ?>
			<p>The upgrade file has finished downloading but the web server can not write directly to the /core/ folder. You'll need to enter your FTP credentials below so that BigTree can upgrade. You may also want to backup your database before proceeding.</p>
			<hr />
			<fieldset>
				<label>FTP Username</label>
				<input type="text" name="username" />
			</fieldset>
			<fieldset>
				<label>FTP Password</label>
				<input type="password" name="password" />
			</fieldset>
			<? } ?>
		</section>
		<footer>
			<input type="submit" class="blue" value="Continue" />
		</footer>
	</div>
</form>
<?	
	}
?>