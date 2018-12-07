<?php
	$ipl_value = BigTreeAdmin::makeIPL($field["value"]);
	$placeholder = $field["value"];
	$show_value = false;

	// See if it's a page
	if (substr($ipl_value, 0, 6) == "ipl://") {
		list($protocol, $empty, $id) = explode("/", $ipl_value);

		// Get the page name for the placeholder
		$page = BigTreeCMS::getPage($id, false);

		if ($page["parent"]) {
			$parent = BigTreeCMS::getPage($page["parent"], false);
			$placeholder = "Page: ".$parent["nav_title"]."&nbsp;&nbsp;&raquo;&nbsp;&nbsp;".$page["nav_title"];
		} else {
			$placeholder = "Page: ".$page["nav_title"];
		}
	// It's a resource
	} elseif (substr($ipl_value, 0, 6) == "irl://") {
		list($protocol, $empty, $id) = explode("/", $ipl_value);

		// Get resource to get it's name
		$resource = BigTreeAdmin::getResource($id);
		$placeholder = "File: ".$resource["name"];
	} else {
		$show_value = true;
	}
?>
<div class="text_input">
	<input class="<?=$field["options"]["validation"]?>" type="hidden" name="<?=$field["key"]?>" value="<?=$field["value"]?>" />
	<input type="text" tabindex="<?=$field["tabindex"]?>" placeholder="<?=$placeholder?>"<?php if ($show_value) { ?> value="<?=$field["value"]?>"<?php } ?> id="<?=$field["id"]?>" />
	<div class="link_field_results_container" style="display: none;"></div>
</div>

<script>
	new BigTreeLinkField("#<?=$field["id"]?>");
</script>