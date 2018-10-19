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
<style>
	#<?=$field["id"]?>_results { background: #FFF; border: 1px solid #AAA; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15); max-height: 200px; overflow-y: auto; margin: -2px 0 0 0; width: 896px; }
	#<?=$field["id"]?>_results div { background: #59A8E9; color: #FFF; font-size: 13px; height: auto; line-height: 20px; padding: 4px 10px 3px; }
	#<?=$field["id"]?>_results a { border-bottom: 1px solid #DDD; color: #333; display: block; font-size: 10px; height: auto; line-height: 14px; padding: 3px 10px; }
	#<?=$field["id"]?>_results a:nth-child(odd) { background: #FAFAFA; }
	#<?=$field["id"]?>_results a:last-child { border-bottom: none; }
	#<?=$field["id"]?>_results a:hover { background: #EEE; }
	#<?=$field["id"]?>_results em { color: #333; display: block; font-size: 10px; line-height: 14px; padding: 3px 10px; }
	#<?=$field["id"]?>_lf_results a:hover { background: #EEE; }
</style>

<div class="text_input" id="<?=$field["id"]?>_wrapper">
	<input type="hidden" name="<?=$field["key"]?>" value="<?=$field["value"]?>" id="<?=$field["id"]?>_value" />
	<input class="<?=$field["options"]["validation"]?>" type="text" tabindex="<?=$field["tabindex"]?>" placeholder="<?=$placeholder?>"<?php if ($show_value) { ?> value="<?=$field["value"]?>"<?php } ?> id="<?=$field["id"]?>" />

	<div id="<?=$field["id"]?>_results" style="display: none;"></div>
</div>

<script>
	(function() {
		var ValueField = $("#<?=$field["id"]?>_value")
		var QueryField = $("#<?=$field["id"]?>");
		var Results = $("#<?=$field["id"]?>_results");
		var ResultWidth = QueryField.outerWidth(true) - 2;

		QueryField.on("keyup", function() {
			var query = QueryField.val().trim();

			queryChange(query);
		});

		QueryField.on("paste", function(e) {
			var clipboard_data = e.originalEvent.clipboardData || window.clipboardData;
    		var pasted_data = clipboard_data.getData('Text');

    		queryChange(pasted_data);
		});

		QueryField.on("blur", function() {
			setTimeout(function() {
				Results.hide();
			}, 250);
		});

		QueryField.on("focus", function() {
			if (Results.html()) {
				Results.show();
			}
		});

		Results.on("click", "a", function(ev) {
			ev.preventDefault();
			ev.stopPropagation();

			ValueField.val($(this).attr("href"));
			QueryField.val("").attr("placeholder", $(this).attr("data-placeholder"));
			Results.hide().html("");
		});

		function queryChange(query) {

			Results.css({ width: ResultWidth }).scrollTop(0);
			ValueField.val(query);
			QueryField.attr("placeholder", "");

			if (!query.length) {
				Results.hide().html("");
			} else {
				if (query.substr(0, 7) == "http://" || query.substr(0, 8) == "https://") {
					Results.hide().html("");
				} else {
					Results.load("<?=ADMIN_ROOT?>*/com.fastspot.link-finder-field-type/ajax/search/", { query: query }, function() {
						Results.show();
					});
				}
			}
		}
	})();
</script>