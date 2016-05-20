<?php
	namespace BigTree;
?>
<p class="error_message" style="display: none;"><?=Text::translate("Errors found! Please fix the highlighted fields before submitting.")?></p>
<fieldset>
	<label><?=Text::translate("URL Route")?> <small>(leave blank to auto generate)</small></label>
	<input type="text" name="route" value="<?=$bigtree["current_page"]["route"]?>" tabindex="2" />
</fieldset>
<fieldset>
	<label><?=Text::translate("Meta Description")?></label>
	<textarea name="meta_description"><?=$bigtree["current_page"]["meta_description"]?></textarea>
</fieldset>
<fieldset class="last">
	<input type="checkbox" name="seo_invisible"<?php if ($bigtree["current_page"]["seo_invisible"]) { ?> checked="checked"<?php } ?> />
	<label class="for_checkbox"><?=Text::translate("Hide From Search Engines")?></label>
</fieldset>