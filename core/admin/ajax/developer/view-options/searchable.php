<?php
	namespace BigTree;

	// Defaults
	$sort = isset($options["sort"]) ? $options["sort"] : "id DESC";
	$per_page = isset($options["per_page"]) ? $options["per_page"] : 15;
?>
<fieldset>
	<label><?=Text::translate("Sort By")?></label>
	<?php if ($table) { ?>
	<select name="sort">
		<?php \BigTree::getFieldSelectOptions($table,$sort,true) ?>
	</select>
	<?php } else { ?>
	<input name="sort" type="text" disabled="disabled" placeholder="<?=Text::translate("Choose a Data Table first.", true)?>" />
	<?php } ?>
</fieldset>
<fieldset>
	<label><?=Text::translate("Items Per Page")?></label>
	<input type="text" name="per_page" value="<?=htmlspecialchars($per_page)?>" />
</fieldset>