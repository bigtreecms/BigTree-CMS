<?php
	namespace BigTree;
	
	/**
	 * @global CloudStorage\Rackspace $rackspace
	 */
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>cloud-storage/rackspace/update/" class="module">
		<section>
			<fieldset>
				<label for="rackspace_field_key"><?=Text::translate("API Key")?></label>
				<input id="rackspace_field_key" type="text" name="api_key" value="<?=Text::htmlEncode($rackspace->Key)?>" />
			</fieldset>
			<fieldset>
				<label for="rackspace_field_username"><?=Text::translate("Username")?></label>
				<input id="rackspace_field_username" type="text" name="username" value="<?=Text::htmlEncode($rackspace->Username)?>" />
			</fieldset>
			<fieldset>
				<label for="rackspace_field_region"><?=Text::translate('Region <small>(choose the location closest to your server)</small>')?></label>
				<select id="rackspace_field_region" name="region">
					<?php foreach ($rackspace->Regions as $region => $name) { ?>
					<option value="<?=$region?>"<?php if ($region == $rackspace->Region) { ?> selected="selected"<?php } ?>><?=$name?></option>
					<?php } ?>
				</select>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />
		</footer>
	</form>
</div>