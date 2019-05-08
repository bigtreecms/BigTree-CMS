<?php
	namespace BigTree;

	/**
	 * @global string $id
	 * @global string $filter_field_id
	 */
?>
<input id="<?=$filter_field_id?>" type="text" name="<?=$id?>" placeholder="<?=Text::translate("Search Query", true)?>" />