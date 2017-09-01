<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	if (is_array($bigtree["config"]["sites"]) && count($bigtree["config"]["sites"]) > 1) {
?>
<label for="site_key_switcher" class="visually_hidden">Choose a Site</label>
<select id="site_key_switcher">
	<?php
		$active_site = Cookie::get("bigtree_admin[active_site]");
		
		foreach ($bigtree["config"]["sites"] as $site_key => $site) {
			$domain = parse_url($site["domain"], PHP_URL_HOST);
	?>
	<option value="<?=Text::htmlEncode($site_key)?>"<?php if ($active_site == $site_key) { ?> selected="selected"<?php } ?>><?=$domain?></option>
	<?php
		}
	?>
</select>
<?php
	}
?>