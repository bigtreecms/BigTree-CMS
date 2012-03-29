<?
	$type = $admin->getFieldType(end($commands));
	$field_types[] = $type;
	
	if (file_exists($server_root."custom/admin/form-field-types/draw/".$type["id"].".php")) {
		$other_files[] = "custom/admin/form-field-types/draw/".$type["id"].".php";
	}
	if (file_exists($server_root."custom/admin/form-field-types/process/".$type["id"].".php")) {
		$other_files[] = "custom/admin/form-field-types/process/".$type["id"].".php";
	}
	if (file_exists($server_root."custom/admin/ajax/developer/field-options/".$type["id"].".php")) {
		$other_files[] = "custom/admin/ajax/developer/field-options/".$type["id"].".php";
	}
	
	$default_name = $type["name"];
?>
<h1><span class="package"></span>Create Package</h1>
<div class="form_container">
	<header><p>Please select all the files required for the Field Type &ldquo;<?=$type["name"]?>&rdquo;</p></header>
	<form method="post" action="<?=$admin_root?>developer/foundry/package/process/" class="module">
		<section>