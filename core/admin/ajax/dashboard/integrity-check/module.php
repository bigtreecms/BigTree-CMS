<?php
	/**
	 * @global BigTreeAdmin $admin
	 * @global BigTreeCMS $cms
	 * @global callable $check_data
	 * @global array $integrity_errors
	 */

	// Get the form so we can walk through its fields
	$form_id = (string) $_POST["form"];
    $id = $_POST["id"];
	$form = BigTreeAutoModule::getForm($form_id);
	$external = !empty($_POST["external"]) && $_POST["external"] !== "false";

	// Create a generic module class to get the decoded item data
	$m = new BigTreeModule;
	$m->Table = $form["table"];
	$item = BigTree::translateArray($m->get($id));

	// Loop through form resources and see if we have related page data, only check html and text fields
	if (is_array($form["fields"])) {
		$check_data("",$external,$form["fields"],$item);
	}

	// Only retrieve these if we have errors as we only need them for URL generation
	if (array_filter($integrity_errors)) {
		$action = $admin->getModuleActionForForm($form);
		$module = $admin->getModule($action["module"]);
	}

	$has_errors = false;

	foreach ($integrity_errors as $field => $error_types) {
		foreach ($error_types as $type => $errors) {
			foreach ($errors as $error) {
				$has_errors = true;
?>
<li>
	<section class="integrity_errors">
		<a href="<?=ADMIN_ROOT.$module["route"]."/".$action["route"]."/".htmlspecialchars($id)?>/" target="_blank">Edit</a>
		<span class="icon_small icon_small_warning"></span>
		<p>Broken <?=(($type == "img") ? "Image" : "Link")?>: <?=BigTree::safeEncode($error)?> in field &ldquo;<?=$field?>&rdquo;</p>
	</section>
</li>
<?php
			}
		}
	}

	if ($has_errors) {
		BigTreeCMS::cachePut("org.bigtreecms.integritycheck","errors.".($external ? "external" : "internal").".modules.$form_id.$id", $integrity_errors);
	}

	BigTreeCMS::cachePut("org.bigtreecms.integritycheck", "current_module.".($external ? "external" : "internal"), $_POST["module"]);
	BigTreeCMS::cachePut("org.bigtreecms.integritycheck", "current_module_item.".($external ? "external" : "internal"), $_POST["index"]);
