<fieldset class="text_input">
	<?
		if ($title) {
	?>
	<label<?=$label_validation_class?>><?=$title?><? if ($subtitle) { ?> <small><?=$subtitle?></small><? } ?></label>
	<?
		}
		
		$st = isset($options["sub_type"]) ? $options["sub_type"] : false;
		if (!$st) {
	?>
	<input<?=$input_validation_class?> type="text" tabindex="<?=$tabindex?>" name="<?=$key?>" value="<?=$value?>" id="field_<?=$key?>" />
	<?
		} elseif ($st == "name") {
	?>
	<section class="input_name">
		<input<?=$input_validation_class?> type="text" tabindex="<?=$tabindex?>" name="<?=$key?>[first_name]" value="<?=$value["first_name"]?>" id="field_<?=$key?>_first_name" placeholder="First" />
	</section>
	<section class="input_name">
		<input<?=$input_validation_class?> type="text" tabindex="<?=($tabindex + 1)?>" name="<?=$key?>[last_name]" value="<?=$value["last_name"]?>" id="field_<?=$key?>_last_name" placeholder="Last" />
	</section>
	<?
			$tabindex++;
		} elseif ($st == "address") {
	?>
	<section class="input_address_street">
		<input<?=$input_validation_class?> type="text" tabindex="<?=$tabindex?>" name="<?=$key?>[street]" value="<?=$value["street"]?>" id="field_<?=$key?>_street" placeholder="Street Address" />
	</section>
	<section class="input_address_city">
		<input<?=$input_validation_class?> type="text" tabindex="<?=($tabindex + 1)?>" name="<?=$key?>[city]" value="<?=$value["city"]?>" id="field_<?=$key?>_city" placeholder="City" />
	</section>
	<section class="input_address_state">
		<select<?=$input_validation_class?> name="<?=$key?>[state]" id="field_<?=$key?>_state" tabindex="<?=($tabindex + 2)?>">
			<option value="">Select a State</option>
			<? foreach ($state_list as $a => $s) { ?>
			<option value="<?=$a?>"<? if ($a == $value["state"]) { ?> selected="selected"<? } ?>><?=$s?></option>
			<? } ?>
		</select>
	</section>
	<section class="input_address_zip">
		<input<?=$input_validation_class?> type="text" tabindex="<?=($tabindex + 3)?>" name="<?=$key?>[zip]" value="<?=$value["zip"]?>" id="field_<?=$key?>_zip" placeholder="Zip/Postal Code" />
	</section>
	<section class="input_address_country">
		<select<?=$input_validation_class?> name="<?=$key?>[country]" id="field_<?=$key?>_country" tabindex="<?=($tabindex + 4)?>">
			<? foreach ($country_list as $c) { ?>
			<option value="<?=$c?>"<? if ($c == $value["country"]) { ?> selected="selected"<? } ?>><?=$c?></option>
			<? } ?>
		</select>
	</section>
	<?
			$tabindex += 4;
		} elseif ($st == "email") {
	?>
	<input<?=$input_validation_class?> type="email" tabindex="<?=$tabindex?>" name="<?=$key?>" value="<?=$value?>" id="field_<?=$key?>" />
	<?
		} elseif ($st == "website") {
	?>
	<input<?=$input_validation_class?> type="url" tabindex="<?=$tabindex?>" name="<?=$key?>" value="<?=$value?>" id="field_<?=$key?>" />
	<?
		} elseif ($st == "phone") {
			list($area_code,$prefix,$line_number) = explode("-",$value);
	?>
	<section class="input_phone_3">
		<input<?=$input_validation_class?> type="text" tabindex="<?=$tabindex?>" name="<?=$key?>[phone_1]" maxlength="3" value="<?=$area_code?>" id="field_<?=$key?>" placeholder="xxx" />
		<span>-</span>
	</section>
	<section class="input_phone_3">
		<input<?=$input_validation_class?> type="text" tabindex="<?=$tabindex?>" name="<?=$key?>[phone_2]" maxlength="3" value="<?=$prefix?>" id="field_<?=$key?>" placeholder="xxx" />
		<span>-</span>
	</section>
	<section class="input_phone_4">
		<input<?=$input_validation_class?> type="text" tabindex="<?=$tabindex?>" name="<?=$key?>[phone_3]" maxlength="4" value="<?=$line_number?>" id="field_<?=$key?>" placeholder="xxxx" />
	</section>
	<?
			$tabindex += 2;
		}
	?>
</fieldset>