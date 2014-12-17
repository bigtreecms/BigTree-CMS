<?
	// Figure out how we're going to do the update
	$failed = false;
	// See if we can write to the root directory first for a local filesystem update
	if (is_writable(SERVER_ROOT) && is_writable(SERVER_ROOT."core/")) {
		$_SESSION["bigtree_admin"]["upgrade_method"] = "local";
	} else {
		$ftp = new BigTreeFTP;
		$sftp = new BigTreeSFTP;

		// Try out FTP
		if ($ftp->connect("localhost")) {
			$_SESSION["bigtree_admin"]["upgrade_method"] = "FTP";
		} elseif ($sftp->connect("localhost")) {
			$_SESSION["bigtree_admin"]["upgrade_method"] = "SFTP";
		} else {
			$failed = true;
		}
	}
	// If we can't do a local, FTP, or SFTP update then we give instructions on how to manually update
	if ($failed) {
		BigTree::redirect(DEVELOPER_ROOT."upgrade/failed/");
	}
	
	$updates = @json_decode(BigTree::cURL("http://www.bigtreecms.org/ajax/version-check/?current_version=".BIGTREE_VERSION),true);
	$update = $updates[$_GET["type"]];
	if (!$update) {
		$admin->growl("Developer","Couldn't Get Download Information","error");
		BigTree::redirect(DEVELOPER_ROOT);
	}
?>
<div class="container">
	<summary><h2>Upgrade BigTree</h2></summary>
	<section>
		<p>Please wait while we download the update...</p>
	</section>
</div>
<script>
	$.ajax("<?=ADMIN_ROOT?>ajax/developer/upgrade/download/", { type: "POST", data: { file: "<?=$update["file"]?>" }, complete: function() {
		window.location.href = "<?=DEVELOPER_ROOT?>upgrade/check-file/?type=<?=htmlspecialchars($_GET["type"])?>";
	} });
</script>