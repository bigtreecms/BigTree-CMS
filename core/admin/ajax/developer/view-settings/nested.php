<?php
	// Defaults
	$nesting_column = isset($settings["nesting_column"]) ? $settings["nesting_column"] : "";
?>
<fieldset>
	<label>Nesting Column <small>(i.e. "parent")</small></label>
	<select name="nesting_column">
		<?php BigTree::getFieldSelectOptions($table,$nesting_column) ?>
	</select>
</fieldset>