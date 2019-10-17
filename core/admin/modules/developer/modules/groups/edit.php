<?php
	namespace BigTree;
	
	Router::setLayout("new");
	
	$group = new ModuleGroup(end(Router::$Path));
?>
<module-group-form id="<?=$group->ID?>" name="<?=$group->Name?>" action="update"></module-group-form>