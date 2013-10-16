<section>
	<div class="left last">
		<fieldset>
			<label class="required">Title <small>(for reference only, not shown in the embed)</small></label>
			<input type="text" class="required" name="title" value="<?=$title?>" />
		</fieldset>

		<fieldset>
			<label class="required">Data Table</label>
			<select name="table" id="form_table" class="required">
				<option></option>
				<? BigTree::getTableSelectOptions($table); ?>
			</select>
		</fieldset>

		<fieldset>
			<label>Thank You Message</label>
			<textarea name="thank_you_message" id="thank_you_message"><?=htmlspecialchars($thank_you_message)?></textarea>
		</fieldset>
	</div>
	<div class="right last">
		<fieldset>
			<label>Preprocessing Function <small>(passes in post data, returns keyed array of adds/edits)</small></label>
			<input type="text" name="preprocess" value="<?=htmlspecialchars($preprocess)?>" />
		</fieldset>

		<fieldset>
			<label>Function Callback <small>(passes in ID and parsed post data, and publish state)</small></label>
			<input type="text" name="callback" value="<?=htmlspecialchars($callback)?>" />
		</fieldset>

		<fieldset>
			<label>Custom CSS File <small>(full URL)</small></label>
			<input type="text" name="css" value="<?=$css?>" />
		</fieldset>

		<fieldset>
			<label>Redirect URL <small>(overrides "Thank You Message")</small></label>
			<input type="text" name="redirect_url" value="<?=$redirect_url?>" />
		</fieldset>

		<fieldset>
			<input type="checkbox" name="default_pending"<? if ($default_pending) { ?> checked="checked"<? } ?> />
			<label class="for_checkbox">Default Submissions to Pending</label>
		</fieldset>
	</div>
</section>
<?
	$bigtree["simple_html_fields"] = array("thank_you_message");
	$bigtree["html_editor_width"] = 435;
	include BigTree::path("admin/layouts/_html-field-loader.php");
?>