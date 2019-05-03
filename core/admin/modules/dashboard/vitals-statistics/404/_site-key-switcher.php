<?php
	namespace BigTree;
	
	if (is_array(Router::$Config["sites"]) && count(Router::$Config["sites"]) > 1) {
?>
<label for="site_key_switcher" class="visually_hidden">Choose a Site</label>
<select id="site_key_switcher">
	<?php
		$active_site = Cookie::get("bigtree_admin[active_site]");
		
		foreach (Router::$Config["sites"] as $site_key => $site) {
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