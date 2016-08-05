<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$field_type = new FieldType(end($bigtree["path"]));
	$field_type->delete();
	
	Utils::growl("Developer","Deleted Field Type");
	Router::redirect(DEVELOPER_ROOT."field-types/");
	