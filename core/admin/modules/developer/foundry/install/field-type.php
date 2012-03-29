<?
	$details = json_decode(bigtree_curl("http://developer.bigtreecms.com/ajax/foundry/get-type-details/",array("id" => end($path))),true);
?>
<h3 class="foundry">Installing Field Type</h3>
<h4>Unpacking &ldquo;<?=$details["name"]?>&rdquo;</h4>
<p>Version <?=$details["primary_version"]?>.<?=$details["secondary_version"]?>.<?=$details["tertiary_version"]?> by <?=$details["author"]["name"]?></p>
<p>
	<strong>Description</strong><br />
	<?=$details["description"]?>
</p>
<?
	$existing = sqlrows(sqlquery("SELECT * FROM bigtree_field_types WHERE id = '".mysql_real_escape_string($details["field_type_id"])."'"));
	
	if ($existing) {
?>
<h4>ERROR</h4>
<p>The field type you attempted to install uses a field ID that is already in use by your BigTree install (<strong><?=$details["field_type_id"]?></strong>).</p>
<p>Install aborted.</p>
<?
	} else {	
		// Create the unpack directory, download the package and extract it.
		$cache = $server_root."cache/unpack/";
		if (!file_exists($cache)) {
			mkdir($cache);
			chmod($cache,0777);
		}
		file_put_contents($cache."package.tgz",file_get_contents("http://developer.bigtreecms.com/files/foundry/field-types/".$details["file"]));
		exec("cd $cache; tar zxvf package.tgz");
		
		// Check the index for all the available files.
		$warnings = array();
		$errors = array();
		$index = file_get_contents($cache."index.bpz");
		$lines = explode("\n",$index);
		foreach ($lines as $line) {
			$pieces = explode("::||::",$line);
			$file = $pieces[2];
			if (!bigtree_is_writable($server_root.$file))
				$errors[] = "Cannot write file at &ldquo;$file&rdquo;";
			if (file_exists($server_root.$file))
				$warnings[] = "A file already exists at &ldquo;$file&rdquo; &mdash; the file will be overwritten.";
		}
		
		bigtree_clean_globalize_array($details,array("mysql_real_escape_string"));

		if (count($warnings)) {
?>
<strong class="import_warnings">Warnings</strong>
<ul class="import_warnings">
	<? foreach ($warnings as $w) { ?>
	<li>&raquo; <?=$w?></li>
	<? } ?>
</ul>
<?
		}
		
		if (count($errors)) {
?>
<strong class="import_errors">Errors</strong>
<ul class="import_errors">
	<? foreach ($errors as $e) { ?>
	<li>&raquo; <?=$e?></li>
	<? } ?>
</ul>
<p><strong>ERRORS OCCURRED!</strong> &mdash; Please correct all errors.  You may not install this field type while errors persist.</p>
<?
		} else {
?>
<form method="post" action="<?=$aroot?>developer/foundry/install/process/field-type/<?=$details["id"]?>/" class="module">
	<input type="hidden" name="details" value="<?=htmlspecialchars(serialize($details))?>" />
	<input type="submit" class="button white" value="Install Now" name="submit" />
</form>
<?
		}
	}
?>