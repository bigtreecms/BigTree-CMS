<?php
	$extensions = $admin->getExtensions();

	// Get version info on our installed extensions
	$query = array();
	foreach ($extensions as $extension) {
		$query[] = "extensions[]=".urlencode($extension["id"]);
	}
	$version_info = array_filter((array)@json_decode(BigTree::cURL("http://www.bigtreecms.org/ajax/extensions/version/?".implode("&",$query),false,array(CURLOPT_CONNECTTIMEOUT => 1,CURLOPT_TIMEOUT => 5)),true));

	foreach ($extensions as &$extension) {
		$extension["ignore_link"] = $extension["upgrade_link"] = "";

		if (!isset($_COOKIE["bigtree_admin"]["ignored_extension_updates"][$extension["id"]])) {
			// Read manifest, see if a new version is available
			$manifest = json_decode(file_get_contents(SERVER_ROOT."extensions/".$extension["id"]."/manifest.json"),true);
			if (intval($manifest["revision"]) < intval($version_info[$extension["id"]]["revision"])) {
				$info = $version_info[$extension["id"]];
				$extension["ignore_link"] = '<a class="button red" href="'.DEVELOPER_ROOT.'extensions/ignore/?id='.$extension["id"].'">Ignore</a>';
				$extension["upgrade_link"] = '<a class="button blue" href="'.DEVELOPER_ROOT.'extensions/upgrade/?id='.$extension["id"].'">Upgrade</a>';
				$extension["version"] .= '<small>(version '.$info["version"].' available, compatible with BigTree '.$info["compatibility"].')</small>';
			}
		}
	}
?>
<div id="extensions_table"></div>
<script>
	BigTreeTable({
		container: "#extensions_table",
		title: "Extensions",
		data: <?=json_encode($extensions)?>,
		actions: {
			edit: function(id,state) {
				document.location.href = "<?=DEVELOPER_ROOT?>extensions/edit/" + id + "/";
			},
			delete: function(id,state) {
				BigTreeDialog({
					title: "Uninstall Extension",
					content: '<p class="confirm">Are you sure you want to uninstall this extension?<br /><br />Related components, including those that were added to this package will also <strong>completely deleted</strong> (including related files).</p>',
					icon: "delete",
					alternateSaveText: "OK",
					callback: function() {
						document.location.href = "<?=DEVELOPER_ROOT?>extensions/delete/" + id + "/";
					}
				});
			}
		},
		columns: {
			name: { title: "Extension Name", largeFont: true, actionHook: "edit", size: 1, source: "{name} v{version}" },
			ignore_link: { title: "", size: 87, center: true },
			upgrade_link: { title: "", size: 101, center: true }
		}
	});

	$("#extensions_table").on("click",".button.red",function(ev) {
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