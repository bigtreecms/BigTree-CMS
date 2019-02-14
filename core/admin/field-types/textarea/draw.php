<?php
	namespace BigTree;
	
	$text_character = Text::translate("character");
	$text_characters = Text::translate("characters");
	$text_remaining = Text::translate("remaining");
	
	$max_length = isset($this->Settings["max_length"]) ? intval($this->Settings["max_length"]) : false;
?>
<textarea class="<?=$this->Settings["validation"]?>" name="<?=$this->Key?>" tabindex="<?=$this->TabIndex?>" id="<?=$this->ID?>"<?php if ($max_length) { ?> maxlength="<?=$max_length?>"<?php } ?>><?=$this->Value?></textarea>
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
?>