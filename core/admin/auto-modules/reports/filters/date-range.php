<?php
	namespace BigTree;

	/**
	 * @global string $id
	 */
?>
<div class="float_margin">
	<div class="contain">
		<input type="text" name="<?=$id?>[start]" value="" autocomplete="off" class="date_time_picker" />
		<span class="icon_small icon_small_calendar date_picker_icon"></span>
	</div>
	<p class="note"><?=Text::translate("Start Time")?></p>
</div>
<div class="float_margin">
	<div class="contain">
		<input type="text" name="<?=$id?>[end]" value="" autocomplete="off" class="date_time_picker" />
		<span class="icon_small icon_small_calendar date_picker_icon"></span>
	</div>
	<p class="note"><?=Text::translate("End Time")?></p>
</div>