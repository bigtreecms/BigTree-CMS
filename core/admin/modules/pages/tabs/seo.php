<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
?>
<p class="error_message" style="display: none;"><?=Text::translate("Errors found! Please fix the highlighted fields before submitting.")?></p>
<fieldset>
	<label for="page_field_route"><?=Text::translate("URL Route")?> <small>(leave blank to auto generate)</small></label>
	<input id="page_field_route" type="text" name="route" value="<?=$bigtree["current_page"]["route"]?>" tabindex="2" />
</fieldset>
<fieldset>
	<label for="page_field_meta_description"><?=Text::translate("Meta Description")?></label>
	<textarea id="page_field_meta_description" name="meta_description"><?=$bigtree["current_page"]["meta_description"]?></textarea>
</fieldset>
<fieldset class="last">
	<input id="page_field_seo_invisible" type="checkbox" name="seo_invisible"<?php if ($bigtree["current_page"]["seo_invisible"]) { ?> checked="checked"<?php } ?> />
	<label for="page_field_seo_invisible" class="for_checkbox"><?=Text::translate("Hide From Search Engines")?></label>
</fieldset>