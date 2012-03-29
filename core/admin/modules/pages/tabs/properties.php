<?
	$ages = array(
		"0" => "No Limit",
		"15" => "15 Days",
		"30" => "30 Days",
		"60" => "60 Days",
		"90" => "90 Days",
		"180" => "180 Days",
		"365" => "1 Year"
	);
	
	$parent_to_check = $pdata ? $pdata["parent"] : $parent;
?>
<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>

<div class="left">
	<fieldset>
		<label class="required">Navigation Title</label>
		<input type="text" name="nav_title" id="nav_title" value="<?=$pdata["nav_title"]?>" tabindex="1" class="required" />
	</fieldset>
</div>
<div class="right">
	<fieldset>
		<label class="required">Page Title <small>(web browsers use this for their title bar)</small></label>
		<input type="text" name="title" id="page_title" tabindex="2" value="<?=$pdata["title"]?>" class="required" />
	</fieldset>
</div>
<!-- <div class="triplets"> -->
<div class="left date_pickers">
	<fieldset>
		<label>Publish Date <small>(blank = immediately)</small></label>
		<input type="text" class="date" id="publish_at" name="publish_at" tabindex="3" value="<? if ($pdata["publish_at"]) { echo date("Y-m-d",strtotime($pdata["publish_at"])); } ?>" />
	</fieldset>
	<fieldset class="right">
		<label>Expiration Date <small>(blank = never)</small></label>
		<input type="text" class="date" id="expire_at" name="expire_at" tabindex="4" value="<? if ($pdata["expire_at"]) { echo date("Y-m-d",strtotime($pdata["expire_at"])); } ?>" />
	</fieldset>
</div>
<div class="right">
	<fieldset>
		<label>Content Max Age <small>(before alerts)</small></label>
		<select name="max_age" tabindex="5">
			<? foreach ($ages as $v => $age) { ?>
			<option value="<?=$v?>"<? if ($v == $pdata["max_age"]) { ?> selected="selected"<? } ?>><?=$age?></option>
			<? } ?>
		</select>
	</fieldset>
</div>
<fieldset class="visible clear">
	<? if ($parent_to_check > 0 || $admin->Level > 1) { ?>
	<input type="checkbox" name="in_nav" <? if (!$pdata || $pdata["in_nav"]) { ?>checked="checked" <? } ?>class="checkbox" tabindex="6" /> <label class="for_checkbox">Visible In Navigation</label>
	<? } else { ?>
	<input type="checkbox" name="in_nav" <? if ($pdata["in_nav"]) { ?>checked="checked" <? } ?>disabled="disabled" class="checkbox" tabindex="6" /> <label class="for_checkbox">Visible In Navigation <small>(you may not add visible top level navigation)</small></label>
	<? } ?>
</fieldset>

