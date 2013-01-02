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
	
	if (isset($page["nav_title"])) {
		$parent_to_check = $page["parent"];
	} else {
		$parent_to_check = $id;
		// Stop the notices for new pages, batman!
		$page = array(
			"nav_title" => "",
			"title" => "",
			"resources" => "",
			"publish_at" => "",
			"expire_at" => "",
			"max_age" => "",
			"trunk" => "",
			"in_nav" => "on",
			"external" => "",
			"new_window" => "",
			"resources" => array(),
			"callouts" => array(),
			"tags" => false,
			"route" => "",
			"meta_keywords" => "",
			"meta_description" => ""
		);
	}
?>
<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>

<div class="left">
	<fieldset>
		<label class="required">Navigation Title</label>
		<input type="text" name="nav_title" id="nav_title" value="<?=$page["nav_title"]?>" tabindex="1" class="required" />
	</fieldset>
</div>
<div class="right">
	<fieldset>
		<label class="required">Page Title <small>(web browsers use this for their title bar)</small></label>
		<input type="text" name="title" id="page_title" tabindex="2" value="<?=$page["title"]?>" class="required" />
	</fieldset>
</div>
<div class="left date_pickers">
	<fieldset>
		<label>Publish Date <small>(blank = immediately)</small></label>
		<input type="text" class="date_picker" id="publish_at" name="publish_at" tabindex="3" value="<? if ($page["publish_at"]) { echo date("Y-m-d",strtotime($page["publish_at"])); } ?>" />
		<span class="icon_small icon_small_calendar date_picker_icon"></span>
	</fieldset>
	<fieldset class="right">
		<label>Expiration Date <small>(blank = never)</small></label>
		<input type="text" class="date_picker" id="expire_at" name="expire_at" tabindex="4" value="<? if ($page["expire_at"]) { echo date("Y-m-d",strtotime($page["expire_at"])); } ?>" />
		<span class="icon_small icon_small_calendar date_picker_icon"></span>
	</fieldset>
</div>
<div class="right">
	<fieldset>
		<label>Content Max Age <small>(before alerts)</small></label>
		<select name="max_age" tabindex="5">
			<? foreach ($ages as $v => $age) { ?>
			<option value="<?=$v?>"<? if ($v == $page["max_age"]) { ?> selected="selected"<? } ?>><?=$age?></option>
			<? } ?>
		</select>
	</fieldset>
</div>
<? if ($admin->Level > 1) { ?>
<fieldset class="clear">
	<input type="checkbox" name="trunk" <? if ($page["trunk"]) { ?>checked="checked" <? } ?> tabindex="6" /> <label class="for_checkbox">Trunk</label>
</fieldset>
<? } ?>
<fieldset class="visible clear">
	<? if ($parent_to_check > 0 || $admin->Level > 1) { ?>
	<input type="checkbox" name="in_nav" <? if ($page["in_nav"]) { ?>checked="checked" <? } ?>class="checkbox" tabindex="7" /> <label class="for_checkbox">Visible In Navigation</label>
	<? } else { ?>
	<input type="checkbox" name="in_nav" <? if ($page["in_nav"]) { ?>checked="checked" <? } ?>disabled="disabled" class="checkbox" tabindex="7" /> <label class="for_checkbox">Visible In Navigation <small>(only developers can change the visibility of top level navigation)</small></label>
	<? } ?>
</fieldset>