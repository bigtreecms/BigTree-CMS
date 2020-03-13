<?php
	namespace BigTree;
	
	Router::setLayout("new");
	Admin::doNotCache();
	Admin::setTheme("grid");
	
	$group = new ModuleGroup(Router::$Command, ["BigTree\Admin", "catch404"]);
?>
<developer-module-group-form id="<?=$group->ID?>" name="<?=$group->Name?>" action="update"></developer-module-group-form>