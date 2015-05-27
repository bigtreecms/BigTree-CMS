<div class="container">
	<summary><h2>Extension Upgrade Failed</h2></summary>
	<section>
		<p>
			BigTree attempted and failed to install the extension via FTP, SFTP, or via local file permissions.
			You will need to manually download the zip file from the <a href="http://www.bigtreecms.org/extensions/" target="_blank">BigTree extensions repository</a> and upgrade via replacing the /extensions/<?=htmlspecialchars($_GET["id"])?>/ folder with the one from the new zip.
		</p>
	</section>
</div>