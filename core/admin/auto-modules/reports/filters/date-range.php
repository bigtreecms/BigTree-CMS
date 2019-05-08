<?php
	namespace BigTree;

	/**
	 * @global string $id
	 * @global string $filter_field_id
	 */
?>
<div class="float_margin">
	<div class="contain">
		<input id="<?=$filter_field_id?>_start" type="text" name="<?=$id?>[start]" value="" autocomplete="off" class="date_time_picker" />
		<span class="icon_small icon_small_calendar date_picker_icon"></span>
	</div>
	<label class="note" for="<?=$filter_field_id?>_start"><?=Text::translate("Start Time")?></label>
</div>

<div class="float_margin">
	<div class="contain">
		<input id="<?=$filter_field_id?>_end" type="text" name="<?=$id?>[end]" value="" autocomplete="off" class="date_time_picker" />
		<span class="icon_small icon_small_calendar date_picker_icon"></span>
	</div>
	<label for="<?=$filter_field_id?>_end" class="note"><?=Text::translate("End Time")?></label>
</div>