<?php
	namespace BigTree;

	/**
	 * @global callable $check_data
	 * @global bool $external
	 * @global array $integrity_errors
	 */
	
	$page = new Page($_GET["id"]);
	$template = new Template($page->Template);

	// Loop through template resources and see if we have related page data, only check html and text fields
	if (count($template->Fields)) {
		$check_data(WWW_ROOT.$page->Path."/", $external, $template->Fields, $page->Resources);
	}
	
	// Loop through the errors
	foreach ($integrity_errors as $title => $error_types) {
		foreach ($error_types as $type => $errors) {
			foreach ($errors as $error) {
				if ($type == "img") {
					$message = "Broken Image: :url: on page &ldquo;:page:&rdquo; in field &ldquo;:field:&rdquo;";
				} else {
					$message = "Broken Link: :url: on page &ldquo;:page:&rdquo; in field &ldquo;:field:&rdquo;";
				}
?>
<li>
	<section class="integrity_errors">
		<a href="<?=ADMIN_ROOT?>pages/edit/<?=$page->ID?>/" target="_blank"><?=Text::translate("Edit")?></a>
		<span class="icon_small icon_small_warning"></span>
		<p><?=Text::translate($message, false, array(":url:" => $error, ":page:" => $page->NavigationTitle, ":field:" => $title))?></p>
	</section>
</li>
<?php
			}
		}
	}
?>