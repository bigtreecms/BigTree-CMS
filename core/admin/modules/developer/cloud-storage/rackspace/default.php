<?php
	namespace BigTree;
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>cloud-storage/rackspace/update/" class="module">
		<section>
			<fieldset>
				<label><?=Text::translate("API Key")?></label>
				<input type="text" name="api_key" value="<?=Text::htmlEncode($rackspace->Key)?>" />
			</fieldset>
			<fieldset>
				<label><?=Text::translate("Username")?></label>
				<input type="text" name="username" value="<?=Text::htmlEncode($rackspace->Username)?>" />
			</fieldset>
			<fieldset>
				<label><?=Text::translate('Region <small>(choose the location closest to your server)</small>')?></label>
				<select name="region">
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