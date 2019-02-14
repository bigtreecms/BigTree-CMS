<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$text_character = Text::translate("character");
	$text_characters = Text::translate("characters");
	$text_remaining = Text::translate("remaining");
?>
<div class="text_input">
	<?php
		$sub_type = isset($this->Settings["sub_type"]) ? $this->Settings["sub_type"] : false;
		$max_length = isset($this->Settings["max_length"]) ? intval($this->Settings["max_length"]) : false;

		if (!$sub_type) {
	?>
	<input class="<?=$this->Settings["validation"]?>" type="text" tabindex="<?=$this->TabIndex?>" name="<?=$this->Key?>" value="<?=$this->Value?>" id="<?=$this->ID?>"<?php if ($max_length) { ?> maxlength="<?=$max_length?>"<?php } ?> />
	<?php
			if ($max_length) {
				$current_length = $max_length - strlen(htmlspecialchars_decode($this->Value));
	?>
	<div class="form_sub_label" id="<?=$this->ID?>_sub_label"><?=$current_length?> <?php if ($current_length != 1) { echo $text_characters; } else { echo $text_character; } ?> <?=$text_remaining?></div>
	<script>
		$("#<?=$this->ID?>").keyup(function() {
			var remaining = <?=intval($max_length)?> - $(this).val().length;
			var message = remaining;
	
			if (parseInt(remaining) === 1) {
				message += " <?=$text_characters?>";
			} else {
				message += " <?=$text_characters?>";
			} 
	
			message += " <?=$text_remaining?>";
	
			$("#<?=$this->ID?>_sub_label").html(message);
		});
	</script>
	<?php
			}
		} elseif ($sub_type == "name") {
			// To prevent warnings we'll try to extract a first name / last name from a string.
			if (!is_array($this->Value)) {
				if ($this->Value) {
					$temp = explode(" ",$this->Value);
					$this->Value = ["first_name" => $temp[0],"last_name" => end($temp)];
				} else {
					$this->Value = ["first_name" => "","last_name" => ""];
				}
			}
	?>
	<section class="input_name">
		<input class="<?=$this->Settings["validation"]?>" type="text" tabindex="<?=$this->TabIndex?>" name="<?=$this->Key?>[first_name]" value="<?=$this->Value["first_name"]?>" id="<?=$this->ID?>_first_name" placeholder="<?=Text::translate("First", true)?>" />
	</section>
	<section class="input_name">
		<input class="<?=$this->Settings["validation"]?>" type="text" tabindex="<?=($this->TabIndex + 1)?>" name="<?=$this->Key?>[last_name]" value="<?=$this->Value["last_name"]?>" id="<?=$this->ID?>_last_name" placeholder="<?=Text::translate("Last", true)?>" />
	</section>
	<?php
			// Increase form tab index since we used extras
			$bigtree["tabindex"]++;
		} elseif ($sub_type == "address") {
			// Prevent warnings.
			if (!is_array($this->Value)) {
				$this->Value = ["street" => "", "city" => "", "state" => "", "zip" => "", "country" => ""];
			}
	?>
	<section class="input_address_street">
		<input class="<?=$this->Settings["validation"]?>" type="text" tabindex="<?=$this->TabIndex?>" name="<?=$this->Key?>[street]" value="<?=$this->Value["street"]?>" id="<?=$this->ID?>_street" placeholder="<?=Text::translate("Street Address", true)?>" />
	</section>
	<section class="input_address_city">
		<input class="<?=$this->Settings["validation"]?>" type="text" tabindex="<?=($this->TabIndex + 1)?>" name="<?=$this->Key?>[city]" value="<?=$this->Value["city"]?>" id="<?=$this->ID?>_city" placeholder="<?=Text::translate("City", true)?>" />
	</section>
	<section class="input_address_state">
		<select class="<?=$this->Settings["validation"]?>" name="<?=$this->Key?>[state]" id="<?=$this->ID?>_state" tabindex="<?=($this->TabIndex + 2)?>">
			<option value=""><?=Text::translate("Select a State")?></option>
			<?php foreach (Field::$StateList as $abbreviation => $state_name) { ?>
			<option value="<?=$abbreviation?>"<?php if ($abbreviation == $this->Value["state"]) { ?> selected="selected"<?php } ?>><?=$state_name?></option>
			<?php } ?>
		</select>
	</section>
	<section class="input_address_zip">
		<input class="<?=$this->Settings["validation"]?>" type="text" tabindex="<?=($this->TabIndex + 3)?>" name="<?=$this->Key?>[zip]" value="<?=$this->Value["zip"]?>" id="<?=$this->ID?>_zip" placeholder="<?=Text::translate("Zip/Postal Code", true)?>" />
	</section>
	<section class="input_address_country">
		<select class="<?=$this->Settings["validation"]?>" name="<?=$this->Key?>[country]" id="<?=$this->ID?>_country" tabindex="<?=($this->TabIndex + 4)?>">
			<?php foreach (Field::$CountryList as $country_name) { ?>
			<option value="<?=$country_name?>"<?php if ($country_name == $this->Value["country"]) { ?> selected="selected"<?php } ?>><?=$country_name?></option>
			<?php } ?>
		</select>
	</section>
	<?php
			// Increase form tab index since we used extras
			$bigtree["tabindex"] += 4;
		} elseif ($sub_type == "email") {
	?>
	<input class="<?=$this->Settings["validation"]?>" type="email" tabindex="<?=$this->TabIndex?>" name="<?=$this->Key?>" value="<?=$this->Value?>" id="<?=$this->ID?>" />
	<?php
		} elseif ($sub_type == "website") {
	?>
	<input class="<?=$this->Settings["validation"]?>" type="url" tabindex="<?=$this->TabIndex?>" name="<?=$this->Key?>" value="<?=$this->Value?>" id="<?=$this->ID?>" />
	<?php
		} elseif ($sub_type == "phone") {
			list($area_code, $prefix, $line_number) = explode("-", $this->Value);
	?>
	<section class="input_phone_3">
		<input class="<?=$this->Settings["validation"]?>" type="text" tabindex="<?=$this->TabIndex?>" name="<?=$this->Key?>[phone_1]" maxlength="3" value="<?=$area_code?>" id="<?=$this->ID?>" placeholder="xxx" />
		<span>-</span>
	</section>
	<section class="input_phone_3">
		<input class="<?=$this->Settings["validation"]?>" type="text" tabindex="<?=$this->TabIndex?>" name="<?=$this->Key?>[phone_2]" maxlength="3" value="<?=$prefix?>" id="<?=$this->ID?>" placeholder="xxx" />
		<span>-</span>
	</section>
	<section class="input_phone_4">
		<input class="<?=$this->Settings["validation"]?>" type="text" tabindex="<?=$this->TabIndex?>" name="<?=$this->Key?>[phone_3]" maxlength="4" value="<?=$line_number?>" id="<?=$this->ID?>" placeholder="xxxx" />
	</section>
	<?php
			// Increase form tab index since we used extras
			$bigtree["tabindex"] += 2;
		}
	?>
</div>