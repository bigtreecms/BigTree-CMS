<?php
	/**
	 * @global BigTreeAdmin $admin
	 * @global BigTreeCMS $cms
	 * @global callable $check_data
	 * @global array $integrity_errors
	 */
	
	// Get the form so we can walk through its fields
	$form_id = (string) $_POST["form"];
	$form = BigTreeAutoModule::getForm($form_id);
	$external = !empty($_POST["external"]) ? true : false;

	// Create a generic module class to get the decoded item data
	$m = new BigTreeModule;
	$m->Table = $form["table"];
	$item = BigTree::translateArray($m->get($_POST["id"]));
	
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
		<a href="<?=ADMIN_ROOT.$module["route"]."/".$action["route"]."/".htmlspecialchars($_POST["id"])?>/" target="_blank">Edit</a>
		<span class="icon_small icon_small_warning"></span>
		<p>Broken <?=(($type == "img") ? "Image" : "Link")?>: <?=BigTree::safeEncode($error)?> in field &ldquo;<?=$field?>&rdquo;</p>
	</section>
</li>
<?php
			}
		}
	}
	
	$session = BigTreeCMS::cacheGet("org.bigtreecms.integritycheck", "session.".($external ? "external" : "internal"));
	$session["current_module"] = $_POST["module"];
	$session["current_item"] = $_POST["index"];
	
	if ($has_errors) {
		if (empty($session["errors"])) {
			$session["errors"] = [];
		}
		
		if (empty($session["errors"][$form_id])) {
			$session["errors"][$form_id] = [];
		}
		
		$session["errors"][$form_id][$_POST["id"]] = $integrity_errors;
	}
	
	BigTreeCMS::cachePut("org.bigtreecms.integritycheck", "session.".($external ? "external" : "internal"), $session);
?>