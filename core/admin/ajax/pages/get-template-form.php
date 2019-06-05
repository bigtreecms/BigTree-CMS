<?php
	namespace BigTree;

	/**
	 * @global array $template_id
	 */
	
	$page = null;

	if (isset($_POST["page"])) {
		$template_id = $_POST["template"];
		$page = Page::getPageDraft($_POST["page"]);
		$content = $page->Content;
	} elseif (isset($_POST["template"])) {
		$template_id = $_POST["template"];
		$content = [];
	}
	
	if (!empty($template_id) && $template_id != "!") {
		$template = new Template($template_id);
	} else {
		$template = null;
	}
	
	// See if we have an editing hook
	if (!empty($template->Hooks["edit"])) {
		$content = call_user_func($template->Hooks["edit"], $content, $template->Array, true);
	}
	
	if (isset($_POST["page"]) && $template_id != $page->Template) {
		if (Template::exists($page->Template)) {
			$original_template = new Template($page->Template);
			$forced_recrops = Field::rectifyTypeChange($content, $template->Fields, $original_template->Fields);
		} else {
			$content = [];
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
	Utils::drawPOSTErrorMessage();
?>

<p class="error_message" style="display: none;"><?=Text::translate("Errors found! Please fix the highlighted fields before submitting.")?></p>

<div class="form_fields">
	<?php
		Field::$GlobalTabIndex = 11;
		Field::$Namespace = uniqid("template_field_");

		$drawn = false;
	
		if (!is_null($template)) {
			$template->Fields = Extension::runHooks("fields", "template", $template->Fields, [
				"template" => $template,
				"step" => "draw",
				"page" => $page
			]);
		
			if (count($template->Fields)) {
				foreach ($template->Fields as $resource) {
					$field = new Field([
						"type" => $resource["type"],
						"title" => $resource["title"],
						"subtitle" => $resource["subtitle"],
						"key" => "resources[".$resource["id"]."]",
						"has_value" => isset($content[$resource["id"]]),
						"value" => isset($content[$resource["id"]]) ? $content[$resource["id"]] : "",
						"settings" => $resource["settings"],
						"forced_recrop" => isset($forced_recrops[$resource["id"]]) ? true : false
					]);

					$field->draw();
					$drawn = true;
				}
			}
		}

		if (!$drawn) {
			echo '<p>'.Text::translate("There are no fields for the selected template.").'</p>';
		}
	?>
</div>
<?php
	include Router::getIncludePath("admin/layouts/_html-field-loader.php");
?>
<script>
	BigTree.TinyMCEFields = <?=json_encode(array_merge(Field::$HTMLFields, Field::$SimpleHTMLFields))?>;
</script>