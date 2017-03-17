<?
	$extensions = $admin->getExtensions();

	// Get version info on our installed extensions
	$query = array();
	foreach ($extensions as $extension) {
		$query[] = "extensions[]=".urlencode($extension["id"]);
	}
	$version_info = array_filter((array)@json_decode(BigTree::cURL("http://www.bigtreecms.org/ajax/extensions/version/?".implode("&",$query),false,array(CURLOPT_CONNECTTIMEOUT => 1,CURLOPT_TIMEOUT => 5)),true));
?>
<div class="table">
	<summary><h2>Extensions</h2></summary>
	<header>
		<span class="developer_templates_name">Extension Name</span>
		<span style="width: 80px;">Actions</span>
	</header>
	<ul>
		<?
			foreach ($extensions as $extension) {
				$new = false;

				if (!isset($_COOKIE["bigtree_admin"]["ignored_extension_updates"][$extension["id"]])) {
					// Read manifest, see if a new version is available
					$manifest = json_decode(file_get_contents(SERVER_ROOT."extensions/".$extension["id"]."/manifest.json"),true);
					if (intval($manifest["revision"]) < intval($version_info[$extension["id"]]["revision"])) {
						$new = true;
						$info = $version_info[$extension["id"]];
					}
				}
		?>
		<li>
			<section class="developer_extensions_name">
				<?=$extension["name"]?> v<?=$extension["version"]?>
				<? if ($new) { ?>
				<small>(version <?=$info["version"]?> available, compatible with BigTree <?=$info["compatibility"]?>)</small>
				<? } ?>
			</section>
			<section class="developer_extensions_action">
				<? if ($new) { ?>
				<a class="button red" href="<?=DEVELOPER_ROOT?>extensions/ignore/?id=<?=$extension["id"]?>">Ignore</a>
				<? } ?>	
			</section>
			<section class="developer_extensions_action">
				<? if ($new) { ?>
				<a class="button blue" href="<?=DEVELOPER_ROOT?>extensions/upgrade/?id=<?=$extension["id"]?>">Upgrade</a>
				<? } ?>	
			</section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>extensions/edit/<?=$extension["id"]?>/" class="icon_edit"></a>
			</section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>extensions/delete/?id=<?=$extension["id"]?><? $admin->drawCSRFTokenGET() ?>" class="icon_delete"></a>
			</section>
		</li>
		<? } ?>
	</ul>
</div>
<script>
	$(".icon_delete").click(function(ev) {
		ev.preventDefault();

		BigTreeDialog({
			title: "Uninstall Extension",
			content: '<p class="confirm">Are you sure you want to uninstall this extension?<br /><br />Related components, including those that were added to this package will also <strong>completely deleted</strong> (including related files).</p>',
			icon: "delete",
			alternateSaveText: "Uninstall",
			callback: $.proxy(function() {
				window.location.href = $(this).attr("href");
			},this)
		});
	});

	$(".button.red").click(function(ev) {
		ev.preventDefault();

		BigTreeDialog({
			title: "Ignore Extension Updates",
			content: '<p class="confirm">Are you sure you want to ignore updates for this extension?</p>',
			alternateSaveText: "Ignore",
			callback: $.proxy(function() {
				window.location.href = $(this).attr("href");
			},this)
		});
	});
</script>