<?php
	namespace BigTree;

	if (!$table) {
?>
<p><?=Text::translate("Please select a table first.")?></p>
<?php
	} else {
?>
<fieldset>
	<label><?=Text::translate("Order By")?></label>
	<select name="sort">
		<?php SQL::drawColumnSelectOptions($table,$data["sort"],true); ?>
	</select>
</fieldset>
<fieldset>
	<label><?=Text::translate("Limit <small>(defaults to 15)</small>")?></label>
	<input type="text" name="limit" value="<?=$data["limit"]?>" />
</fieldset>
<?php
	} 
?>