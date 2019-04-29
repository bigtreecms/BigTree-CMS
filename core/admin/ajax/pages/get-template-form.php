<?php
	namespace BigTree;

	/**
	 * @global array $bigtree
	 */
	
	$template_id = $bigtree["current_page"]["template"];

	if (isset($_POST["page"])) {
		$template_id = $_POST["template"];
		$page = Page::getPageDraft($_POST["page"]);
		$bigtree["current_page"] = $page->Array; // Backwards compat
		$bigtree["resources"] = $page->Resources;
	} elseif (isset($_POST["template"])) {
		$template_id = $_POST["template"];
		$bigtree["resources"] = array();
	} elseif (!isset($bigtree["resources"]) && !isset($bigtree["callouts"])) {
		$bigtree["resources"] = array();
	}
	
	if (!empty($template_id) && $template_id != "!") {
		$template = new Template($template_id);
		$bigtree["template"] = $template->Array; // Backwards compat
	} else {
		$template = null;
	}
	
	// See if we have an editing hook
	if (!empty($template->Hooks["edit"])) {
		$bigtree["resources"] = call_user_func($template->Hooks["edit"], $bigtree["resources"], $template->Array, true);
	}
	
	if (isset($_POST["page"]) && $template_id != $bigtree["current_page"]["template"]) {
		if (Template::exists($bigtree["current_page"]["template"])) {
			$original_template = new Template($bigtree["current_page"]["template"]);
			$forced_recrops = Field::rectifyTypeChange($bigtree["resources"], $template->Fields, $original_template->Fields);
		} else {
			$bigtree["resources"] = [];
		}
	} else {
		$forced_recrops = [];
	}
?>
<div class="alert template_message">
	<label><?=Text::translate("Template")?>:</label>
	<p>
		<?php
			if ($template_id == "") {
				echo Text::translate("External Link");
			} elseif ($template_id == "!") {
				echo Text::translate("Redirect Lower"); 
			} else { 
				echo $template->Name;
			}
		?>
	</p>
</div>
<?php
	if ($_SESSION["bigtree_admin"]["post_max_hit"]) {
		unset($_SESSION["bigtree_admin"]["post_max_hit"]);
?>
<p class="warning_message"><?=Text::translate("The file(s) uploaded exceeded the web server's maximum upload size. If you uploaded multiple files, try uploading one at a time.")?></p>
<?php
	}
?>
<p class="error_message" style="display: none;"><?=Text::translate("Errors found! Please fix the highlighted fields before submitting.")?></p>
<div class="form_fields">
	<?php
		$bigtree["html_fields"] = array();
		$bigtree["simple_html_fields"] = array();
		$bigtree["tabindex"] = 11;
		$bigtree["field_types"] = FieldType::reference(false,"templates");

		Field::$Namespace = uniqid("template_field_");

		// We alias $bigtree["entry"] to $bigtree["resources"] so that information is in the same place for field types.
		$bigtree["entry"] = &$bigtree["resources"];
		$drawn = false;
	
		if (!is_null($template)) {
			$template->Fields = Extension::runHooks("fields", "template", $template->Fields, [
				"template" => $template,
				"step" => "draw",
				"page" => $bigtree["current_page"]
			]);
		
			if (count($template->Fields)) {
				foreach ($template->Fields as $resource) {
					$field = new Field(array(
						"type" => $resource["type"],
						"title" => $resource["title"],
						"subtitle" => $resource["subtitle"],
						"key" => "resources[".$resource["id"]."]",
						"has_value" => isset($bigtree["resources"][$resource["id"]]),
						"value" => isset($bigtree["resources"][$resource["id"]]) ? $bigtree["resources"][$resource["id"]] : "",
						"tabindex" => $bigtree["tabindex"],
						"settings" => $resource["settings"],
						"forced_recrop" => isset($forced_recrops[$resource["id"]]) ? true : false
					));

					$field->draw();
					$drawn = true
				}
			}
		}

		if (!$drawn) {
			echo '<p>'.Text::translate("There are no fields for the selected template.").'</p>';
		}
	?>
</div>
<?php
	$bigtree["html_editor_width"] = 898;
	$bigtree["html_editor_height"] = 365;
	include Router::getIncludePath("admin/layouts/_html-field-loader.php");
	$bigtree["tinymce_fields"] = array_merge($bigtree["html_fields"],$bigtree["simple_html_fields"]);
?>
<script>
	BigTree.TinyMCEFields = <?=json_encode($bigtree["tinymce_fields"])?>;
</script>