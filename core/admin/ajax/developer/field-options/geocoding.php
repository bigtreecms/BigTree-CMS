<?php
	namespace BigTree;
	
	/**
	 * @global array $options
	 */
?>
<fieldset class="last">
	<label for="options_field_fields"><?=Text::translate("Fields To Pull Address From <small>(comma separated)</small>")?></label>
	<input id="options_field_fields" type="text" name="fields" value="<?=$options["fields"]?>" />
</fieldset>