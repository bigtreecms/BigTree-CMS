<?php
	$regions = array(
		"ORD" => "Chicago, IL (USA)",
		"DFW" => "Dallas/Ft. Worth, TX (USA)",
		"HKG" => "Hong Kong",
		"LON" => "London (UK)",
		"IAD" => "Northern Virginia (USA)",
		"SYD" => "Sydney (Australia)"
	);

	if (isset($cloud->Settings["rackspace"])) {
		BigTree::globalizeArray($cloud->Settings["rackspace"],"htmlspecialchars");
	} else {
		$api_key = $username = $region = "";
	}
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>cloud-storage/rackspace/update/" class="module">
		<section>
			<fieldset>
				<label><?=Text::translate("API Key")?></label>
				<input type="text" name="api_key" value="<?=$api_key?>" />
			</fieldset>
			<fieldset>
				<label><?=Text::translate("Username")?></label>
				<input type="text" name="username" value="<?=$username?>" />
			</fieldset>
			<fieldset>
				<label><?=Text::translate("Region")?> <small>(<?=Text::translate("choose the location closest to your server")?>)</small></label>
				<select name="region">
					<?php foreach ($regions as $r => $name) { ?>
					<option value="<?=$r?>"<?php if ($r == $region) { ?> selected="selected"<?php } ?>><?=$name?></option>
					<<?php } ?>
				</select>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />
		</footer>
	</form>
</div>