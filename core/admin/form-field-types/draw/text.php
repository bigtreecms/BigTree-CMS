<div class="text_input">
	<?
		$sub_type = isset($field["options"]["sub_type"]) ? $field["options"]["sub_type"] : false;
		$max_length = isset($field["options"]["max_length"]) ? intval($field["options"]["max_length"]) : false;

		if (!$sub_type) {
	?>
	<input class="<?=$field["options"]["validation"]?>" type="text" tabindex="<?=$field["tabindex"]?>" name="<?=$field["key"]?>" value="<?=$field["value"]?>" id="<?=$field["id"]?>"<? if ($max_length) { ?> maxlength="<?=$max_length?>" placeholder="Maximum of <?=$max_length?> characters"<? } ?> />
	<?
		} elseif ($sub_type == "name") {
			// To prevent warnings we'll try to extract a first name / last name from a string.
			if (!is_array($field["value"])) {
				if ($field["value"]) {
					$temp = explode(" ",$field["value"]);
					$field["value"] = array("first_name" => $temp[0],"last_name" => end($temp));
				} else {
					$field["value"] = array("first_name" => "","last_name" => "");
				}
			}
	?>
	<section class="input_name">
		<input class="<?=$field["options"]["validation"]?>" type="text" tabindex="<?=$field["tabindex"]?>" name="<?=$field["key"]?>[first_name]" value="<?=$field["value"]["first_name"]?>" id="<?=$field["id"]?>_first_name" placeholder="First" />
	</section>
	<section class="input_name">
		<input class="<?=$field["options"]["validation"]?>" type="text" tabindex="<?=($field["tabindex"] + 1)?>" name="<?=$field["key"]?>[last_name]" value="<?=$field["value"]["last_name"]?>" id="<?=$field["id"]?>_last_name" placeholder="Last" />
	</section>
	<?
			// Increase form tab index since we used extras
			$bigtree["tabindex"]++;
		} elseif ($sub_type == "address") {
			// Prevent warnings.
			if (!is_array($field["value"])) {
				$field["value"] = array("street" => "", "city" => "", "state" => "", "zip" => "", "country" => "");
			}
	?>
	<section class="input_address_street">
		<input class="<?=$field["options"]["validation"]?>" type="text" tabindex="<?=$field["tabindex"]?>" name="<?=$field["key"]?>[street]" value="<?=$field["value"]["street"]?>" id="<?=$field["id"]?>_street" placeholder="Street Address" />
	</section>
	<section class="input_address_city">
		<input class="<?=$field["options"]["validation"]?>" type="text" tabindex="<?=($field["tabindex"] + 1)?>" name="<?=$field["key"]?>[city]" value="<?=$field["value"]["city"]?>" id="<?=$field["id"]?>_city" placeholder="City" />
	</section>
	<section class="input_address_state">
		<select class="<?=$field["options"]["validation"]?>" name="<?=$field["key"]?>[state]" id="<?=$field["id"]?>_state" tabindex="<?=($field["tabindex"] + 2)?>">
			<option value="">Select a State</option>
			<? foreach (BigTree::$StateList as $abbreviation => $state_name) { ?>
			<option value="<?=$abbreviation?>"<? if ($abbreviation == $field["value"]["state"]) { ?> selected="selected"<? } ?>><?=$state_name?></option>
			<? } ?>
		</select>
	</section>
	<section class="input_address_zip">
		<input class="<?=$field["options"]["validation"]?>" type="text" tabindex="<?=($field["tabindex"] + 3)?>" name="<?=$field["key"]?>[zip]" value="<?=$field["value"]["zip"]?>" id="<?=$field["id"]?>_zip" placeholder="Zip/Postal Code" />
	</section>
	<section class="input_address_country">
		<select class="<?=$field["options"]["validation"]?>" name="<?=$field["key"]?>[country]" id="<?=$field["id"]?>_country" tabindex="<?=($field["tabindex"] + 4)?>">
			<? foreach (BigTree::$CountryList as $country_name) { ?>
			<option value="<?=$country_name?>"<? if ($country_name == $field["value"]["country"]) { ?> selected="selected"<? } ?>><?=$country_name?></option>
			<? } ?>
		</select>
	</section>
	<?
			// Increase form tab index since we used extras
			$bigtree["tabindex"] += 4;
		} elseif ($sub_type == "email") {
	?>
	<input class="<?=$field["options"]["validation"]?>" type="email" tabindex="<?=$field["tabindex"]?>" name="<?=$field["key"]?>" value="<?=$field["value"]?>" id="<?=$field["id"]?>" />
	<?
		} elseif ($sub_type == "website") {
	?>
	<input class="<?=$field["options"]["validation"]?>" type="url" tabindex="<?=$field["tabindex"]?>" name="<?=$field["key"]?>" value="<?=$field["value"]?>" id="<?=$field["id"]?>" />
	<?
		} elseif ($sub_type == "phone") {
			list($area_code,$prefix,$line_number) = explode("-",$field["value"]);
	?>
	<section class="input_phone_3">
		<input class="<?=$field["options"]["validation"]?>" type="text" tabindex="<?=$field["tabindex"]?>" name="<?=$field["key"]?>[phone_1]" maxlength="3" value="<?=$area_code?>" id="<?=$field["id"]?>" placeholder="xxx" />
		<span>-</span>
	</section>
	<section class="input_phone_3">
		<input class="<?=$field["options"]["validation"]?>" type="text" tabindex="<?=$field["tabindex"]?>" name="<?=$field["key"]?>[phone_2]" maxlength="3" value="<?=$prefix?>" id="<?=$field["id"]?>" placeholder="xxx" />
		<span>-</span>
	</section>
	<section class="input_phone_4">
		<input class="<?=$field["options"]["validation"]?>" type="text" tabindex="<?=$field["tabindex"]?>" name="<?=$field["key"]?>[phone_3]" maxlength="4" value="<?=$line_number?>" id="<?=$field["id"]?>" placeholder="xxxx" />
	</section>
	<?
			// Increase form tab index since we used extras
			$bigtree["tabindex"] += 2;
		}
	?>
</div>