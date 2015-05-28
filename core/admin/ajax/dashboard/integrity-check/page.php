<?
	$id = intval($_GET["id"]);
	$page = $cms->getPage($id);
	$template = $cms->getTemplate($page["template"]);
	$local_path = $cms->getLink($id);
	$resources = $page["resources"];

	// Loop through template resources and see if we have related page data, only check html and text fields
	if (is_array($template["resources"])) {
		$check_data($local_path,$external,$template["resources"],$resources);
	}
	
	// Loop through the errors
	foreach ($integrity_errors as $title => $error_types) {
		foreach ($error_types as $type => $errors) {
			foreach ($errors as $error) {
?>
<li>
	<section class="integrity_errors">
		<a href="<?=ADMIN_ROOT?>pages/edit/<?=$id?>/" target="_blank">Edit</a>
		<span class="icon_small icon_small_warning"></span>
		<p>Broken <?=(($type == "img") ? "Image" : "Link")?>: <?=BigTree::safeEncode($error)?> on page &ldquo;<?=$page["nav_title"]?>&rdquo; in field &ldquo;<?=$title?>&rdquo;</p>
	</section>
</li>
<?
			}
		}
	}
?>