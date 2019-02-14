<?php
	namespace BigTree;
	
	$ipl_value = Link::encode($this->Value);
	$placeholder = $this->Value;
	$show_value = false;

	// See if it's a page
	if (substr($ipl_value, 0, 6) == "ipl://") {
		list(, , $id) = explode("/", $ipl_value);

		// Get the page name for the placeholder
		$page = new Page($id, false);

		if ($page->Parent) {
			$parent = new Page($page->Parent, false);
			$placeholder = "Page: ".$parent->NavigationTitle."&nbsp;&nbsp;&raquo;&nbsp;&nbsp;".$page->NavigationTitle;
		} else {
			$placeholder = "Page: ".$page->NavigationTitle;
		}
	// It's a resource
	} elseif (substr($ipl_value, 0, 6) == "irl://") {
		list(, , $id) = explode("/", $ipl_value);

		// Get resource to get it's name
		if (Resource::exists($id)) {
			$resource = new Resource($id);
			$placeholder = "File: ".$resource->Name;
		}
	} else {
		$show_value = true;
	}
?>
<div class="text_input">
	<input class="<?=$this->Settings["validation"]?>" type="hidden" name="<?=$this->Key?>" value="<?=$this->Value?>" />
	<input type="text" tabindex="<?=$this->TabIndex?>" placeholder="<?=$placeholder?>"<?php if ($show_value) { ?> value="<?=$this->Value?>"<?php } ?> id="<?=$this->ID?>" />
	<div class="link_field_results_container" style="display: none;"></div>
</div>

<script>
	new BigTreeLinkField("#<?=$this->ID?>");
</script>