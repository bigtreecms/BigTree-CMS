<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>
<fieldset>
	<label>URL Route <small>(leave blank to auto generate)</small></label>
	<input type="text" name="route" value="<?=$bigtree["current_page"]["route"]?>" tabindex="2" />
</fieldset>
<div class="left last">
	<fieldset>
		<label>Meta Keywords</label>
		<textarea name="meta_keywords"><?=$bigtree["current_page"]["meta_keywords"]?></textarea>
	</fieldset>
</div>
<div class="right last">
	<fieldset>
		<label>Meta Description</label>
		<textarea name="meta_description"><?=$bigtree["current_page"]["meta_description"]?></textarea>
	</fieldset>
</div>