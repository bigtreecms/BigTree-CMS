<?
	// Get the form so we can walk through its fields
	$form = BigTreeAutoModule::getForm($_GET["form"]);

	// Create a generic module class to get the decoded item data
	$m = new BigTreeModule;
	$m->Table = $form["table"];
	$item = $m->get($_GET["id"]);
	
	// Loop through form resources and see if we have related page data, only check html and text fields
	if (is_array($form["fields"])) {
		$check_data("",$external,$form["fields"],$item);
	}

	// Only retrieve these if we have errors as we only need them for URL generation
	if (array_filter($integrity_errors)) {
		$action = $admin->getModuleActionForForm($form);
		$module = $admin->getModule($action["module"]);
	}
	
	foreach ($integrity_errors as $field => $error_types) {
		foreach ($error_types as $type => $errors) {
			foreach ($errors as $error) {
?>
<li>
	<section class="integrity_errors">
		<a href="<?=ADMIN_ROOT.$module["route"]."/".$action["route"]."/".htmlspecialchars($_GET["id"])?>/" target="_blank">Edit</a>
		<span class="icon_small icon_small_warning"></span>
		<p>Broken <?=(($type == "img") ? "Image" : "Link")?>: <?=BigTree::safeEncode($error)?> in field &ldquo;<?=$field?>&rdquo;</p>
	</section>
</li>
<?
			}
		}
	}
?>