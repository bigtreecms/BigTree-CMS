<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();
	
	$field_type = new FieldType($_GET["id"]);
	$field_type->delete();
	
	Utils::growl("Developer","Deleted Field Type");
	Router::redirect(DEVELOPER_ROOT."field-types/");
	