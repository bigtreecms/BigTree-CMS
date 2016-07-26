<?php
	namespace BigTree;
	
	/**
	 * @global Updater $updater
	 */
	
	// If we can't do a local, FTP, or SFTP update then we give instructions on how to manually update
	if (!$updater->Method) {
		Router::redirect(DEVELOPER_ROOT."upgrade/failed/");
	}
	
	$updates = @json_decode(cURL::request("http://www.bigtreecms.org/ajax/version-check/?current_version=".BIGTREE_VERSION), true);
	$update = $updates[$_GET["type"]];
	
	if (!$update) {
		Utils::growl("Developer","Couldn't Get Download Information","error");
		Router::redirect(DEVELOPER_ROOT);
	}

	// Store download key for retrieval
	$download_key = Cache::putUnique("org.bigtreecms.downloads", $update["file"]);
?>
<div class="container">
	<summary><h2><?=Text::translate("Upgrade BigTree")?></h2></summary>
	<section>
		<p><?=Text::translate("Please wait while we download the update...")?></p>
	</section>
</div>
<script>
	$.ajax("<?=ADMIN_ROOT?>ajax/developer/upgrade/download/", { type: "POST", data: { key: "<?=htmlspecialchars($download_key)?>" }, complete: function() {
		window.location.href = "<?=DEVELOPER_ROOT?>upgrade/check-file/?type=<?=htmlspecialchars($_GET["type"])?>";
	} });
</script>