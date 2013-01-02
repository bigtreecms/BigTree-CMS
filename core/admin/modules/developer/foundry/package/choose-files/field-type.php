<?
	$type = $admin->getFieldType(end($bigtree["commands"]));
	$field_types[] = $type;
	
	if (file_exists(SERVER_ROOT."custom/admin/form-field-types/draw/".$type["id"].".php")) {
		$other_files[] = "custom/admin/form-field-types/draw/".$type["id"].".php";
	}
	if (file_exists(SERVER_ROOT."custom/admin/form-field-types/process/".$type["id"].".php")) {
		$other_files[] = "custom/admin/form-field-types/process/".$type["id"].".php";
	}
	if (file_exists(SERVER_ROOT."custom/admin/ajax/developer/field-options/".$type["id"].".php")) {
		$other_files[] = "custom/admin/ajax/developer/field-options/".$type["id"].".php";
	}
	
	$default_name = $type["name"];
?>
<div class="container">
	<header><p>Please select all the files required for the Field Type &ldquo;<?=$type["name"]?>&rdquo;</p></header>
	<form method="post" action="<?=ADMIN_ROOT?>developer/foundry/package/process/" class="module">
		<section>