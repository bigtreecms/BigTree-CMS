<?php
	namespace BigTree;
	
	/**
	 * @global string $page_link
	 * @global string $page_vars
	 * @global Updater $updater
	 */
	
	// If we can't do a local, FTP, or SFTP update then we give instructions on how to manually update
	if (!$updater->Method) {
		Router::redirect($page_link."failed/?id=".$_GET["id"]);
	}

	// We're going to store the download URL in a cache to prevent the download script from abuse
	$info = array_filter((array) @json_decode(cURL::request("http://www.bigtreecms.org/ajax/extensions/version/?extensions[]=".$_GET["id"], false, array(CURLOPT_CONNECTTIMEOUT => 1, CURLOPT_TIMEOUT => 5)), true));
	$extension_info = $info[$_GET["id"]];
	
	if (!$extension_info) {
		Utils::growl("Extensions","Failed to get download information");
		Router::redirect(DEVELOPER_ROOT."extensions/");
	}
	
	$download_key = Cache::putUnique("org.bigtreecms.downloads", $extension_info["github_url"]);
?>
<div class="container">
	<div class="container_summary"><h2><?=Text::translate("Upgrade Extension")?></h2></div>
	<section>
		<p><?=Text::translate("Please wait while we download the update...")?></p>
	</section>
</div>
<script>
	$.secureAjax("<?=ADMIN_ROOT?>ajax/developer/upgrade/download/", { type: "POST", data: { key: "<?=htmlspecialchars($download_key)?>" }, complete: function() {
		window.location.href = "<?=$page_link?>check-file/<?=$page_vars?>";
	} });
</script>