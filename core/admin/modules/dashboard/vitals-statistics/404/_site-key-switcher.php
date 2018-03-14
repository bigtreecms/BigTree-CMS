<?php
	if (is_array($bigtree["config"]["sites"]) && count($bigtree["config"]["sites"]) > 1) {
?>
<select id="site_key_switcher">
	<?php
		$active_site = BigTree::getCookie("bigtree_admin[active_site]");

		foreach ($bigtree["config"]["sites"] as $site_key => $site) {
			$domain = parse_url($site["domain"], PHP_URL_HOST);
	?>
	<option value="<?=BigTree::safeEncode($site_key)?>"<?php if ($active_site == $site_key) { ?> selected="selected"<?php } ?>><?=$domain?></option>
	<?php
		}
	?>
</select>
<?php
	}
?>