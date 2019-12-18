<?php
	namespace BigTree;
	
	Router::setLayout("new");
	Admin::doNotCache();

	$field_type = new FieldType(end(Router::$Commands));
?>
<field-types-form action="update" self_draw="<?=$field_type->SelfDraw?>"
				  id="<?=$field_type->ID?>" name="<?=$field_type->Name?>"
				  use_cases="<?=htmlspecialchars(json_encode($field_type->UseCases))?>"></field-types-form>