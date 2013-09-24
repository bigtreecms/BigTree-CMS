<?
	$bigtree["datepickers"][] = $start = uniqid("start-");
	$bigtree["datepickers"][] = $end = uniqid("end-");
?>
<div class="float_margin">
	<div class="contain">
		<input type="text" name="<?=$id?>[start]" value="" autocomplete="off" id="<?=$start?>" class="date_picker" />
		<span class="icon_small icon_small_calendar date_picker_icon"></span>
	</div>
	<p class="note">Start Time</p>
</div>
<div class="float_margin">
	<div class="contain">
		<input type="text" name="<?=$id?>[end]" value="" autocomplete="off" id="<?=$end?>" class="date_picker" />
		<span class="icon_small icon_small_calendar date_picker_icon"></span>
	</div>
	<p class="note">End Time</p>
</div>