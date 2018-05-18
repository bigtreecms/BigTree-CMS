<?php
	// Defaults
	$sort = isset($settings["sort"]) ? $settings["sort"] : "id DESC";
	$per_page = isset($settings["per_page"]) ? $settings["per_page"] : 15;
?>
<fieldset>
	<label>Sort By</label>
	<?php if ($table) { ?>
	<select name="sort">
		<?php BigTree::getFieldSelectOptions($table,$sort,true) ?>
	</select>
	<?php } else { ?>
	<input name="sort" type="text" disabled="disabled" placeholder="Choose a Data Table first." />
	<?php } ?>
</fieldset>
<fieldset>
	<label>Items Per Page</label>
	<input type="text" name="per_page" value="<?=htmlspecialchars($per_page)?>" />
</fieldset>