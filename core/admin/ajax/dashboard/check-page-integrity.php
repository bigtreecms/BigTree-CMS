<?	
	// Determine whether we should check external links
	$external = $_GET["external"] ? true : false;
	$id = $_GET["id"];
	
	$page = $cms->getPage($id);
	$template = $cms->getTemplate($page["template"]);
	$local_path = $cms->getLink($id);
	$resources = $page["resources"];
	$integrity_errors = array();

	// Loop through template resources and see if we have related page data, only check html and text fields
	if (is_array($template["resources"])) {
		foreach ($template["resources"] as $resource) {
			$field = $resource["title"];
	    	$data = $resources[$resource["id"]];
	    	// Text types could be URLs
			if ($resource["type"] == "text" && is_string($data)) {
				// External link
				if (substr($data,0,4) == "http" && strpos($data,WWW_ROOT) === false) {
					// Only check external links if we've requested them
					if ($external) {
						// Strip out hashes, they conflict with urlExists
						if (strpos($data,"#") !== false) {
							$data = substr($data,0,strpos($data,"#")-1);
						}
						if (!$admin->urlExists($data)) {
							$integrity_errors[$field] = array("a" => array($data));
						}
					}
				// Internal link
				} elseif (substr($data,0,4) == "http") {
					if (!$admin->urlExists($data)) {
						$integrity_errors[$field] = array("a" => array($data));
					}
				}
		    // HTML we just run through checkHTML
		    } elseif ($resource["type"] == "html") {
		    	$integrity_errors[$field] = $admin->checkHTML($local_path,$data,$external);
		    }
		}
	}
	
	// Loop through the errors
	foreach ($integrity_errors as $title => $error_types) {
		foreach ($error_types as $type => $errors) {
			foreach ($errors as $error) {
?>
<li>
	<section class="integrity_errors">
		<a href="<?=ADMIN_ROOT."pages/edit/$id/"?>" target="_blank">Edit</a>
		<span class="icon_small icon_small_warning"></span>
		<p>Broken <?=(($type == "img") ? "Image" : "Link")?>: <?=$error?> on page &ldquo;<?=$page["nav_title"]?>&rdquo; in field &ldquo;<?=$title?>&rdquo;</p>
	</section>
</li>
<?
			}
		}
	}
?>