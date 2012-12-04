<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>
<fieldset>
	<label>URL Route <small>(leave blank to auto generate)</small></label>
	<input type="text" name="route" value="<?=$page["route"]?>" tabindex="2" />
</fieldset>
<div class="left">
	<fieldset>
		<label>Meta Keywords</label>
		<textarea name="meta_keywords"><?=$page["meta_keywords"]?></textarea>
	</fieldset>
</div>
<div class="right">
	<fieldset>
		<label>Meta Description</label>
		<textarea name="meta_description"><?=$page["meta_description"]?></textarea>
	</fieldset>
</div>