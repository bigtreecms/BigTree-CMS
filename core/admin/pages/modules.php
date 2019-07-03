<?php
	namespace BigTree;
	
	Router::setLayout("new");
	Admin::registerRuntimeJavascript("api.js");
	Admin::setState([
		"page_title" => "Modules"
	]);
?>
<page-module-listing></page-module-listing>
