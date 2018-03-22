<?php
	$max_length = isset($field["settings"]["max_length"]) ? intval($field["settings"]["max_length"]) : false;
?>
<textarea class="<?=$field["settings"]["validation"]?>" name="<?=$field["key"]?>" tabindex="<?=$field["tabindex"]?>" id="<?=$field["id"]?>"<?php if ($max_length) { ?> maxlength="<?=$max_length?>"<?php } ?>><?=$field["value"]?></textarea>
<?php
	if ($max_length) {
		$current_length = $max_length - strlen(htmlspecialchars_decode($field["value"]));
?>
<div class="form_sub_label" id="<?=$field["id"]?>_sub_label"><?=$current_length?> character<?php if ($current_length != 1) { ?>s<?php } ?> remaining</div>
<script>
	$("#<?=$field["id"]?>").keyup(function() {
		var remaining = <?=intval($max_length)?> - $(this).val().length;
		var message = remaining + " character";

		if (remaining != 1) {
			message += "s";
		} 

		message += " remaining";

		$("#<?=$field["id"]?>_sub_label").html(message);
	});
</script>
<?php
	}
?>