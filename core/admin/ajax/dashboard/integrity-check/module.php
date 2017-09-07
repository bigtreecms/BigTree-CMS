<?php
	namespace BigTree;

	/**
	 * @global callable $check_data
	 * @global bool $external
	 * @global array $integrity_errors
	 */
	
	// Get the form so we can walk through its fields
	$form = new ModuleForm($_GET["form"]);
	$item = SQL::fetch("SELECT * FROM `".$form->Table."` WHERE id = ?", $_GET["id"]);
	
	foreach ($item as $key => $val) {
		if (is_array(json_decode($val, true))) {
			$item[$key] = json_decode($val, true);
		}
	}
	
	// Loop through form resources and see if we have related page data, only check html and text fields
	if (is_array($form["fields"])) {
		$check_data("", $external, $form["fields"], $item);
	}

	// Only retrieve these if we have errors as we only need them for URL generation
	if (array_filter($integrity_errors)) {
		$action = ModuleAction::getByInterface($form->Interface->ID);
		$module = new Module($action->Module);

		foreach ($integrity_errors as $field => $error_types) {
			foreach ($error_types as $type => $errors) {
				foreach ($errors as $url) {
					if ($type == "img") {
						$message = "Broken Image: :url: in field &ldquo;:field:&rdquo;";
					} else {
						$message = "Broken Link: :url: in field &ldquo;:field:&rdquo;";
					}
?>
<li>
	<section class="integrity_errors">
		<a href="<?=ADMIN_ROOT.$module->Route."/".$action->Route."/".htmlspecialchars($_GET["id"])?>/" target="_blank"><?=Text::translate("Edit")?></a>
		<span class="icon_small icon_small_warning"></span>
		<p><?=Text::translate($message, false, array(":url:" => $url, ":field:" => $form->Fields[$field]["title"]))?></p>
	</section>
</li>
<?php
				}
			}
		}
	}
?>