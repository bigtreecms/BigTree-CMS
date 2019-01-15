<?php
	$ages = array(
		"0" => "No Limit",
		"15" => "15 Days",
		"30" => "30 Days",
		"60" => "60 Days",
		"90" => "90 Days",
		"180" => "180 Days",
		"365" => "1 Year"
	);

	$basic_templates = $admin->getBasicTemplates();
	$routed_templates = $admin->getRoutedTemplates();

	if (isset($bigtree["current_page"]["nav_title"])) {
		$parent_to_check = $bigtree["current_page"]["parent"];
	} else {
		$parent_to_check = $id;
		// Stop the notices for new pages, batman!
		$bigtree["current_page"] = array(
			"nav_title" => "",
			"title" => "",
			"resources" => "",
			"publish_at" => "",
			"expire_at" => "",
			"max_age" => "",
			"trunk" => "",
			"in_nav" => ($parent_to_check > 0 || $admin->Level > 1) ? "on" : "",
			"external" => "",
			"new_window" => "",
			"template" => isset($basic_templates[0]) ? $basic_templates[0]["id"] : $routed_templates[0]["id"],
			"resources" => array(),
			"tags" => false,
			"route" => "",
			"meta_description" => ""
		);
	}

	$admin->drawPOSTErrorMessage(true);
?>
<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>

<div class="left">
	<fieldset>
		<label class="required">Navigation Title</label>
		<input type="text" name="nav_title" id="nav_title" value="<?=$bigtree["current_page"]["nav_title"]?>" tabindex="1" class="required" />
	</fieldset>
</div>
<div class="right">
	<fieldset>
		<label class="required">Page Title <small>(web browsers use this for their title bar)</small></label>
		<input type="text" name="title" id="page_title" tabindex="2" value="<?=$bigtree["current_page"]["title"]?>" class="required" />
	</fieldset>
</div>
<div class="contain">
	<div class="left date_pickers">
		<fieldset class="last">
			<label>Publish At <small>(blank = immediately)</small></label>
			<input type="text" class="date_time_picker" id="publish_at" name="publish_at" autocomplete="off" tabindex="3" value="<?php if ($bigtree["current_page"]["publish_at"]) { echo $admin->convertTimestampToUser($bigtree["current_page"]["publish_at"], $bigtree["config"]["date_format"]." h:i a"); } ?>" />
			<span class="icon_small icon_small_calendar date_picker_icon"></span>
		</fieldset>
		<fieldset class="right last">
			<label>Expire At <small>(blank = never)</small></label>
			<input type="text" class="date_time_picker" id="expire_at" name="expire_at" autocomplete="off" tabindex="4" value="<?php if ($bigtree["current_page"]["expire_at"]) { echo $admin->convertTimestampToUser($bigtree["current_page"]["expire_at"], $bigtree["config"]["date_format"]." h:i a"); } ?>" />
			<span class="icon_small icon_small_calendar date_picker_icon"></span>
		</fieldset>
	</div>
	<div class="right">
		<fieldset class="last">
			<label>Content Max Age <small>(before alerts)</small></label>
			<select name="max_age" tabindex="5">
				<?php foreach ($ages as $v => $age) { ?>
				<option value="<?=$v?>"<?php if ($v == $bigtree["current_page"]["max_age"]) { ?> selected="selected"<?php } ?>><?=$age?></option>
				<?php } ?>
			</select>
		</fieldset>
	</div>
</div>
<div class="contain">
	<fieldset class="float_margin">
		<?php if ($parent_to_check > 0 || $admin->Level > 1) { ?>
		<input type="checkbox" name="in_nav" <?php if ($bigtree["current_page"]["in_nav"]) { ?>checked="checked" <?php } ?>class="checkbox" tabindex="6" />
		<label class="for_checkbox">Visible In Navigation</label>
		<?php } else { ?>
		<input type="checkbox" name="in_nav" <?php if ($bigtree["current_page"]["in_nav"]) { ?>checked="checked" <?php } ?>disabled="disabled" class="checkbox" tabindex="6" />
		<label class="for_checkbox">Visible In Navigation <small>(only developers can change the visibility of top level navigation)</small></label>
		<?php } ?>
	</fieldset>
	<?php
		if (!$hide_template_section && ($bigtree["form_action"] == "create" || $bigtree["current_page"]["id"])) {
	?>
	<fieldset class="float_margin">
		<input type="checkbox" name="redirect_lower" id="redirect_lower"<?php if ($bigtree["current_page"]["template"] == "!") { ?> checked="checked"<?php } ?> tabindex="7" />
		<label class="for_checkbox">Redirect Lower</label>
	</fieldset>
	<?php
		}

		if ($admin->Level > 1 && ($bigtree["form_action"] == "create" || $bigtree["current_page"]["id"])) {
	?>
	<fieldset class="float_margin">
		<input type="checkbox" name="trunk" id="trunk_field" <?php if ($bigtree["current_page"]["trunk"]) { ?>checked="checked" <?php } ?> tabindex="8" />
		<label class="for_checkbox">Trunk</label>
	</fieldset>
	<?php
		}
	?>
</div>
<?php if ($hide_template_section) { ?>
<input type="hidden" name="template" id="template" value="<?=$bigtree["current_page"]["template"]?>" />
<?php } else { ?>
<hr />
<div class="contain">
	<fieldset class="template last">
		<label>Template</label>
		<select id="template_select" name="template" tabindex="9"><?php if ($bigtree["current_page"]["template"] == "!" || $bigtree["current_page"]["external"]) { ?> disabled="disabled"<?php } ?>>
			<optgroup label="Flexible Templates">
				<?php foreach ($basic_templates as $t) { ?>
				<option value="<?=$t["id"]?>"<?php if ($t["id"] == $bigtree["current_page"]["template"]) { ?> selected="selected"<?php } ?>><?=$t["name"]?></option>
				<?php } ?>
			</optgroup>
			<optgroup label="Special Templates">
				<?php foreach ($routed_templates as $t) { ?>
				<option value="<?=$t["id"]?>"<?php if ($t["id"] == $bigtree["current_page"]["template"]) { ?> selected="selected"<?php } ?>><?=$t["name"]?></option>
				<?php } ?>
			</optgroup>
		</select>
	</fieldset>
	<?php if ($bigtree["form_action"] == "create" || $bigtree["current_page"]["id"]) { ?>
	<fieldset class="external last">
		<label>External Link <small>(include http://, overrides template)</small></label>
		<input id="external_link" type="text" name="external" tabindex="10" value="<?=$bigtree["current_page"]["external"]?>" id="external_link"<?php if ($bigtree["current_page"]["template"] == "!") { ?> disabled="disabled"<?php } ?> />
	</fieldset>
	<fieldset class="checkbox_bump last">
		<input id="new_window" type="checkbox" name="new_window" tabindex="11" value="Yes"<?php if ($bigtree["current_page"]["new_window"] == "Yes") { ?> checked="checked"<?php } ?><?php if ($bigtree["current_page"]["template"] == "!") { ?> disabled="disabled"<?php } ?> />
		<label class="for_checkbox">Open in New Window</label>
	</fieldset>
	<?php } ?>
</div>
<?php } ?>