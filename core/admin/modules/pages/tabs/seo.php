<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>
<fieldset>
	<label>URL Route <small>(leave blank to auto generate)</small></label>
	<input type="text" name="route" value="<?=$bigtree["current_page"]["route"]?>" tabindex="2" />
</fieldset>
<div class="contain">
	<fieldset class="left">
		<label>Meta Keywords</label>
		<textarea name="meta_keywords"><?=$bigtree["current_page"]["meta_keywords"]?></textarea>
	</fieldset>
	<fieldset class="right">
		<label>Meta Description</label>
		<textarea name="meta_description"><?=$bigtree["current_page"]["meta_description"]?></textarea>
	</fieldset>
</div>
<fieldset class="last">
	<input type="checkbox" name="seo_invisible"<? if ($bigtree["current_page"]["seo_invisible"]) { ?> checked="checked"<? } ?> />
	<label class="for_checkbox">Hide From Search Engines</label>
</fieldset>