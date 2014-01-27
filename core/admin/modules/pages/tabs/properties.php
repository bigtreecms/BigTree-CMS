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
			"resources" => array(),
			"tags" => false,
			"route" => "",
			"meta_keywords" => "",
			"meta_description" => ""
		);
	}

	$basic_templates = $admin->getBasicTemplates();
	$routed_templates = $admin->getRoutedTemplates();

	if ($bigtree["form_action"] == "create" && $_SESSION["bigtree_admin"]["post_max_hit"]) {
		unset($_SESSION["bigtree_admin"]["post_max_hit"]);
?>
<p class="warning_message">The file(s) uploaded exceeded the web server's maximum upload size. If you uploaded multiple files, try uploading one at a time.</p>
<?
	}
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
			<label>Publish Date <small>(blank = immediately)</small></label>
			<input type="text" class="date_picker" id="publish_at" name="publish_at" tabindex="3" value="<? if ($bigtree["current_page"]["publish_at"]) { echo date("Y-m-d",strtotime($bigtree["current_page"]["publish_at"])); } ?>" />
			<span class="icon_small icon_small_calendar date_picker_icon"></span>
		</fieldset>
		<fieldset class="right last">
			<label>Expiration Date <small>(blank = never)</small></label>
			<input type="text" class="date_picker" id="expire_at" name="expire_at" tabindex="4" value="<? if ($bigtree["current_page"]["expire_at"]) { echo date("Y-m-d",strtotime($bigtree["current_page"]["expire_at"])); } ?>" />
			<span class="icon_small icon_small_calendar date_picker_icon"></span>
		</fieldset>
	</div>
	<div class="right">
		<fieldset class="last">
			<label>Content Max Age <small>(before alerts)</small></label>
			<select name="max_age" tabindex="5">
				<? foreach ($ages as $v => $age) { ?>
				<option value="<?=$v?>"<? if ($v == $bigtree["current_page"]["max_age"]) { ?> selected="selected"<? } ?>><?=$age?></option>
				<? } ?>
			</select>
		</fieldset>
	</div>
</div>
<div class="contain">
	<fieldset class="float_margin">
		<? if ($parent_to_check > 0 || $admin->Level > 1) { ?>
		<input type="checkbox" name="in_nav" <? if ($bigtree["current_page"]["in_nav"]) { ?>checked="checked" <? } ?>class="checkbox" tabindex="7" />
		<label class="for_checkbox">Visible In Navigation</label>
		<? } else { ?>
		<input type="checkbox" name="in_nav" <? if ($bigtree["current_page"]["in_nav"]) { ?>checked="checked" <? } ?>disabled="disabled" class="checkbox" tabindex="7" />
		<label class="for_checkbox">Visible In Navigation <small>(only developers can change the visibility of top level navigation)</small></label>
		<? } ?>
	</fieldset>
	<?
		if (!$hide_template_section) {
	?>
	<fieldset class="float_margin">
		<input type="checkbox" name="redirect_lower" id="redirect_lower"<? if ($bigtree["current_page"]["template"] == "!") { ?> checked="checked"<? } ?> />
		<label class="for_checkbox">Redirect Lower</label>
	</fieldset>
	<?
		}
		if ($admin->Level > 1) {
	?>
	<fieldset class="float_margin">
		<input type="checkbox" name="trunk" <? if ($bigtree["current_page"]["trunk"]) { ?>checked="checked" <? } ?> tabindex="6" />
		<label class="for_checkbox">Trunk</label>
	</fieldset>
	<?
		}
	?>
</div>
<? if ($hide_template_section) { ?>
<input type="hidden" name="template" id="template" value="<?=$bigtree["current_page"]["template"]?>" />
<? } else { ?>
<hr />
<div class="contain">
	<fieldset class="template last">
		<label>Template</label>
		<select id="template_select" name="template"<? if ($bigtree["current_page"]["template"] == "!" || $bigtree["current_page"]["external"]) { ?> disabled="disabled"<? } ?>>
			<optgroup label="Flexible Templates">
				<? foreach ($basic_templates as $t) { ?>
				<option value="<?=$t["id"]?>"<? if ($t["id"] == $bigtree["current_page"]["template"]) { ?> selected="selected"<? } ?>><?=$t["name"]?></option>
				<? } ?>
			</optgroup>
			<optgroup label="Special Templates">
				<? foreach ($routed_templates as $t) { ?>
				<option value="<?=$t["id"]?>"<? if ($t["id"] == $bigtree["current_page"]["template"]) { ?> selected="selected"<? } ?>><?=$t["name"]?></option>
				<? } ?>
			</optgroup>
		</select>
	</fieldset>
	<fieldset class="external last">
		<label>External Link <small>(include http://, overrides template)</small></label>
		<input id="external_link" type="text" name="external" value="<?=$bigtree["current_page"]["external"]?>" id="external_link"<? if ($bigtree["current_page"]["template"] == "!") { ?> disabled="disabled"<? } ?> />
	</fieldset>
	<fieldset class="checkbox_bump last">
		<input id="new_window" type="checkbox" name="new_window" value="Yes"<? if ($bigtree["current_page"]["new_window"] == "Yes") { ?> checked="checked"<? } ?><? if ($bigtree["current_page"]["template"] == "!") { ?> disabled="disabled"<? } ?> />
		<label class="for_checkbox">Open in New Window</label>
	</fieldset>
</div>
<? } ?>