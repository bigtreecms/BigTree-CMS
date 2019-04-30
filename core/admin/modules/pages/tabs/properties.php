<?php
	namespace BigTree;
		
	/**
	 * @global array $bigtree
	 * @global Template $template
	 */
	
	// See if the user isn't allowed to use the currently in use template. If they can't, we hide the section altogether.
	if (is_object($template) && $template->Level > Auth::user()->Level) {
		$hide_template_section = true;
	} else {
		$hide_template_section = false;
	}
	
	$ages = [
		"0" => "No Limit",
		"15" => "15 Days",
		"30" => "30 Days",
		"60" => "60 Days",
		"90" => "90 Days",
		"180" => "180 Days",
		"365" => "1 Year"
	];

	$basic_templates = Template::allByRouted("", "position DESC, id ASC");
	$routed_templates = Template::allByRouted("on", "position DESC, id ASC");

	if (isset($bigtree["current_page"]["nav_title"])) {
		$parent_to_check = $bigtree["current_page"]["parent"];
	} else {
		$parent_to_check = $bigtree["current_page"]["id"];
		
		$bigtree["current_page"] = [
			"nav_title" => "",
			"title" => "",
			"publish_at" => "",
			"expire_at" => "",
			"max_age" => "",
			"trunk" => "",
			"in_nav" => ($parent_to_check > 0 || Auth::user()->Level > 1) ? "on" : "",
			"external" => "",
			"new_window" => "",
			"template" => isset($basic_templates[0]) ? $basic_templates[0]->ID : $routed_templates[0]->ID,
			"resources" => [],
			"tags" => false,
			"route" => "",
			"meta_description" => ""
		];
	}

	if ($bigtree["form_action"] == "create" && $_SESSION["bigtree_admin"]["post_max_hit"]) {
		unset($_SESSION["bigtree_admin"]["post_max_hit"]);
?>
<p class="warning_message"><?=Text::translate("The file(s) uploaded exceeded the web server's maximum upload size. If you uploaded multiple files, try uploading one at a time.")?></p>
<?php
	}
?>
<p class="error_message" style="display: none;"><?=Text::translate("Errors found! Please fix the highlighted fields before submitting.")?></p>

<div class="left">
	<fieldset>
		<label for="nav_title" class="required"><?=Text::translate("Navigation Title")?></label>
		<input type="text" name="nav_title" id="nav_title" value="<?=$bigtree["current_page"]["nav_title"]?>" tabindex="1" class="required" />
	</fieldset>
</div>
<div class="right">
	<fieldset>
		<label for="page_title" class="required"><?=Text::translate("Page Title")?> <small>(<?=Text::translate("web browsers use this for their title bar")?>)</small></label>
		<input type="text" name="title" id="page_title" tabindex="2" value="<?=$bigtree["current_page"]["title"]?>" class="required" />
	</fieldset>
</div>
<div class="contain">
	<div class="left date_pickers">
		<fieldset class="last">
			<label for="publish_at"><?=Text::translate("Publish Date")?> <small>(<?=Text::translate("blank = immediately")?>)</small></label>
			<input type="text" class="date_picker" id="publish_at" name="publish_at" autocomplete="off" tabindex="3" value="<?php if ($bigtree["current_page"]["publish_at"]) { echo Auth::user()->convertTimestampTo($bigtree["current_page"]["publish_at"]); } ?>" />
			<span class="icon_small icon_small_calendar date_picker_icon"></span>
		</fieldset>
		<fieldset class="right last">
			<label for="expire_at"><?=Text::translate("Expiration Date")?> <small>(<?=Text::translate("blank = never")?>)</small></label>
			<input type="text" class="date_picker" id="expire_at" name="expire_at" autocomplete="off" tabindex="4" value="<?php if ($bigtree["current_page"]["expire_at"]) { echo Auth::user()->convertTimestampTo($bigtree["current_page"]["expire_at"]); } ?>" />
			<span class="icon_small icon_small_calendar date_picker_icon"></span>
		</fieldset>
	</div>
	<div class="right">
		<fieldset class="last">
			<label for="page_field_max_age"><?=Text::translate("Content Max Age")?> <small>(<?=Text::translate("before alerts")?>)</small></label>
			<select id="page_field_max_age" name="max_age" tabindex="5">
				<?php foreach ($ages as $v => $age) { ?>
				<option value="<?=$v?>"<?php if ($v == $bigtree["current_page"]["max_age"]) { ?> selected="selected"<?php } ?>><?=$age?></option>
				<?php } ?>
			</select>
		</fieldset>
	</div>
</div>
<div class="contain">
	<fieldset class="float_margin">
		<?php if ($parent_to_check > 0 || Auth::user()->Level > 1) { ?>
		<input id="page_field_in_nav" type="checkbox" name="in_nav" <?php if ($bigtree["current_page"]["in_nav"]) { ?>checked="checked" <?php } ?>class="checkbox" tabindex="6" />
		<label for="page_field_in_nav" class="for_checkbox"><?=Text::translate("Visible In Navigation")?></label>
		<?php } else { ?>
		<input id="page_field_in_nav" type="checkbox" name="in_nav" <?php if ($bigtree["current_page"]["in_nav"]) { ?>checked="checked" <?php } ?>disabled="disabled" class="checkbox" tabindex="6" />
		<label for="page_field_in_nav" class="for_checkbox"><?=Text::translate("Visible In Navigation")?> <small>(<?=Text::translate("only developers can change the visibility of top level navigation")?>)</small></label>
		<?php } ?>
	</fieldset>
	<?php
		if (!$hide_template_section && ($bigtree["form_action"] == "create" || $bigtree["current_page"]["id"])) {
	?>
	<fieldset class="float_margin">
		<input id="redirect_lower" type="checkbox" name="redirect_lower" tabindex="7" <?php if ($bigtree["current_page"]["template"] == "!") { ?> checked="checked"<?php } ?> />
		<label for="redirect_lower" class="for_checkbox"><?=Text::translate("Redirect Lower")?></label>
	</fieldset>
	<?php
		}
		if (Auth::user()->Level > 1 && ($bigtree["form_action"] == "create" || $bigtree["current_page"]["id"])) {
	?>
	<fieldset class="float_margin">
		<input id="page_field_trunk" type="checkbox" name="trunk" <?php if ($bigtree["current_page"]["trunk"]) { ?>checked="checked" <?php } ?> tabindex="8" />
		<label for="page_field_trunk" class="for_checkbox"><?=Text::translate("Trunk")?></label>
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
		<label for="template_select"><?=Text::translate("Template")?></label>
		<select id="template_select" tabindex="9" name="template"<?php if ($bigtree["current_page"]["template"] == "!" || $bigtree["current_page"]["external"]) { ?> disabled="disabled"<?php } ?>>
			<optgroup label="<?=Text::translate("Flexible Templates", true)?>">
				<?php foreach ($basic_templates as $template) { ?>
				<option value="<?=$template->ID?>"<?php if ($template->ID == $bigtree["current_page"]["template"]) { ?> selected="selected"<?php } ?>><?=$template->Name?></option>
				<?php } ?>
			</optgroup>
			<optgroup label="<?=Text::translate("Special Templates", true)?>">
				<?php foreach ($routed_templates as $template) { ?>
				<option value="<?=$template->ID?>"<?php if ($template->ID == $bigtree["current_page"]["template"]) { ?> selected="selected"<?php } ?>><?=$template->Name?></option>
				<?php } ?>
			</optgroup>
		</select>
	</fieldset>
	<?php if ($bigtree["form_action"] == "create" || $bigtree["current_page"]["id"]) { ?>
	<fieldset class="external last">
		<label for="external_link"><?=Text::translate("External Link")?> <small>(<?=Text::translate("include http://, overrides template")?>)</small></label>
		<input id="external_link" type="text" tabindex="10" name="external" value="<?=$bigtree["current_page"]["external"]?>" id="external_link"<?php if ($bigtree["current_page"]["template"] == "!") { ?> disabled="disabled"<?php } ?> />
	</fieldset>
	<fieldset class="checkbox_bump last">
		<input id="new_window" type="checkbox" tabindex="11"  name="new_window"<?php if ($bigtree["current_page"]["new_window"]) { ?> checked="checked"<?php } ?><?php if ($bigtree["current_page"]["template"] == "!") { ?> disabled="disabled"<?php } ?> />
		<label for="new_window" class="for_checkbox"><?=Text::translate("Open in New Window")?></label>
	</fieldset>
	<?php } ?>
</div>
<?php } ?>