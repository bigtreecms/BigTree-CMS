<?php
	namespace BigTree;

	/**
	 * @global string $id
	 * @global string $filter_field_id
	 */
?>
<select id="<?=$filter_field_id?>" name="<?=$id?>">
	<option><?=Text::translate("Both")?></option>
	<option><?=Text::translate("Yes")?></option>
	<option><?=Text::translate("No")?></option>
</select>