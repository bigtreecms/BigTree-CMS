<?php
	/**
	 * @global BigTreeCMS $cms
	 * @global callable $check_data
	 * @global array $integrity_errors
	 */

	$id = intval($_POST["id"]);
	$external = !empty($_POST["external"]) && $_POST["external"] !== "false";
	$page = $cms->getPage($id);
	$template = $cms->getTemplate($page["template"]);
	$local_path = $cms->getLink($id);
	$resources = BigTree::translateArray($page["resources"]);

	// Loop through template resources and see if we have related page data, only check html and text fields
	if (!empty($template["resources"]) && is_array($template["resources"])) {
		$check_data($local_path,$external,$template["resources"],$resources);
	}

	// Loop through the errors
	$has_errors = false;

	foreach ($integrity_errors as $title => $error_types) {
		foreach ($error_types as $type => $errors) {
			foreach ($errors as $error) {
				$has_errors = true;
?>
<li>
	<section class="integrity_errors">
		<a href="<?=ADMIN_ROOT?>pages/edit/<?=$id?>/" target="_blank">Edit</a>
		<span class="icon_small icon_small_warning"></span>
		<p>Broken <?=(($type == "img") ? "Image" : "Link")?>: <?=BigTree::safeEncode($error)?> on page &ldquo;<?=$page["nav_title"]?>&rdquo; in field &ldquo;<?=$title?>&rdquo;</p>
	</section>
</li>
<?php
			}
		}
	}

	$session = BigTreeCMS::cacheGet("org.bigtreecms.integritycheck", "session.".($external ? "external" : "internal"));
	$session["current_page"] = $_POST["index"];

	if ($has_errors) {
		if (empty($session["errors"])) {
			$session["errors"] = [];
		}

		if (empty($session["errors"]["pages"])) {
			$session["errors"]["pages"] = [];
		}

		$session["errors"]["pages"][$id] = $integrity_errors;
	}

	BigTreeCMS::cachePut("org.bigtreecms.integritycheck", "session.".($external ? "external" : "internal"), $session);
?>