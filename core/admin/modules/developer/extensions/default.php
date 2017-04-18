<?php
	namespace BigTree;

	$extensions = Extension::allByType("extension", "last_updated DESC", true);

	// Get version info on our installed extensions
	$query = array();
	
	foreach ($extensions as $extension) {
		$query[] = "extensions[]=".urlencode($extension->ID);
	}
	
	$version_info = array_filter((array)@json_decode(cURL::request("http://www.bigtreecms.org/ajax/extensions/version/?".implode("&",$query),false,array(CURLOPT_CONNECTTIMEOUT => 1,CURLOPT_TIMEOUT => 5)),true));

	foreach ($extensions as &$extension) {
		$extension->IgnoreLink = $extension->UpgradeLink = "";

		if (!isset($_COOKIE["bigtree_admin"]["ignored_extension_updates"][$extension->ID])) {
			// Read manifest, see if a new version is available
			$manifest = json_decode(file_get_contents(SERVER_ROOT."extensions/".$extension->ID."/manifest.json"),true);
			if (intval($manifest["revision"]) < intval($version_info[$extension->ID]["revision"])) {
				$info = $version_info[$extension->ID];
				$extension->IgnoreLink = '<a class="button red" href="'.DEVELOPER_ROOT.'extensions/ignore/?id='.$extension->ID.'">Ignore</a>';
				$extension->UpgradeLink = '<a class="button blue" href="'.DEVELOPER_ROOT.'extensions/upgrade/?id='.$extension->ID.'">Upgrade</a>';
				$extension->Version .= '<small>(version '.$info["version"].' available, compatible with BigTree '.$info["compatibility"].')</small>';
			}
		}
	}
?>
<div id="extensions_table"></div>
<script>
	BigTreeTable({
		container: "#extensions_table",
		title: "<?=Text::translate("Extensions", true)?>",
		data: <?=JSON::encodeColumns($extensions, array("name", "id", "version", "ignore_link", "upgrade_link"))?>,
		actions: {
			"edit": "<?=DEVELOPER_ROOT?>extensions/edit/{id}/",
			"delete": function(id) {
				BigTreeDialog({
					title: "<?=Text::translate("Uninstall Extension", true)?>",
					content: '<p class="confirm"><?=str_replace("'","\\'",Text::translate("Are you sure you want to uninstall this extension?<br /><br />Related components, including those that were added to this package will also <strong>completely deleted</strong> (including related files)."))?></p>',
					icon: "delete",
					alternateSaveText: "<?=Text::translate("OK", true)?>",
					callback: function() {
						document.location.href = "<?=DEVELOPER_ROOT?>extensions/delete/?id=" + id + "<?php CSRF::drawGETToken(); ?>";
					}
				});
			}
		},
		columns: {
			name: { title: "<?=Text::translate("Extension Name", true)?>", largeFont: true, actionHook: "edit", size: 1, source: "{name} v{version}" },
			ignore_link: { title: "", size: 87, center: true },
			upgrade_link: { title: "", size: 101, center: true }
		},
		searchable: true,
		sortable: true
	});

	$("#extensions_table").on("click",".button.red",function(ev) {
		ev.preventDefault();

		BigTreeDialog({
			title: "<?=Text::translate("Ignore Extension Updates", true)?>",
			content: '<p class="confirm"><?=Text::translate("Are you sure you want to ignore updates for this extension?", true)?></p>',
			alternateSaveText: "<?=Text::translate("Ignore", true)?>",
			callback: $.proxy(function() {
				window.location.href = $(this).attr("href");
			},this)
		});
	});
</script>