<?php
	// Defaults
	$nesting_column = isset($options['nesting_column']) ? $options['nesting_column'] : '';
?>
<fieldset>
	<label>Nesting Column <small>(i.e. "parent")</small></label>
	<select name="nesting_column">
		<?php BigTree::getFieldSelectOptions($table, $nesting_column) ?>
	</select>
</fieldset>