<?
	$admin->requireLevel(1);
	$pages = $admin->getPageIds();
	$modules = $admin->getModuleForms();
	// Get the ids of items that are in each module.
	foreach ($modules as &$m) {
		$action = $admin->getModuleActionForForm($m);
		$module = $admin->getModule($action["module"]);
		$m["module_name"] = $module["name"];
	    $m["items"] = array();
	    $q = sqlquery("SELECT id FROM `".$m["table"]."`");
	    while ($f = sqlfetch($q)) {
	    	$m["items"][] = $f["id"];
	    }
	}
?>
<div class="table">
	<summary>
		<div class="integrity_progress"></div>
		<p>Running site integrity check with external link checking disabled.</p>
	</summary>
	<header>
		<span class="integrity_errors">Errors</span>
	</header>
	<header class="group"><span class="integrity_progress" id="pages_progress">0%</span>Pages</header>
	<ul id="pages_updates"></ul>
	<? foreach ($modules as $module) { ?>
	<header class="group"><span class="integrity_progress" id="module_<?=$module["id"]?>_progress">0%</span><?=$module["module_name"]?></header>
	<ul id="module_<?=$module["id"]?>_updates"></ul>
	<? } ?>
</div>

<script>
	var pages = [<? echo implode(",",$pages) ?>];
	var modules = <?=json_encode($modules)?>;
	
	var total_pages,current_page;
	total_pages = pages.length;
	current_page = 0;
	
	function download_page() {
		$.ajax("<?=ADMIN_ROOT?>ajax/dashboard/check-page-integrity/?external=<?=$external?>&id=" + pages[current_page], {
			complete: function(response) {
				if (response.responseText) {
					$("#pages_updates").append(response.responseText);
				}
				current_page = current_page + 1;
				$("#pages_progress").html((Math.round(current_page / total_pages * 10000) / 100) + "%");
				if (current_page < total_pages) {
					download_page();
				} else {
					$("#pages_progress").addClass("complete");
					if (!$("#pages_updates").html()) {
						$("#pages_updates").append($('<li><section class="integrity_errors"><span class="icon_small icon_small_done"></span>No errors found in Pages.</section></li>'));
					}
					download_module(0);
				}
			}
		});
	}
	download_page();
	
	var current_module = 0;
	var total_modules = modules.length;
	var current_item = 0;
	var total_items;
	
	function download_module(number) {
		current_module = number;
		total_items = modules[number].items.length;
		current_item = 0;
		if (total_items > 0) {
			download_item(0);
		} else {
			$("#module_" + modules[current_module].id + "_progress").html("100%").addClass("complete");
			$("#module_" + modules[current_module].id + "_updates").append($('<li><section class="integrity_errors"><span class="icon_small icon_small_done"></span> No errors found in ' + modules[current_module].module_name + '.</section></li>'));
			download_module(current_module + 1);
		}
	}
	
	function download_item(number) {
		$.ajax("<?=ADMIN_ROOT?>ajax/dashboard/check-module-integrity/?external=<?=$external?>&form=" + modules[current_module].id + "&id=" + modules[current_module].items[number], {
			complete: function(response) {
				if (response.responseText) {
					$("#module_" + modules[current_module].id + "_updates").append(response.responseText);
				}
				current_item = current_item + 1;
				$("#module_" + modules[current_module].id + "_progress").html((Math.round(current_item / total_items * 10000) / 100) + "%");
				if (current_item < total_items) {
					download_item(current_item);
				} else {
					$("#module_" + modules[current_module].id + "_progress").addClass("complete");
					if (!$("#module_" + modules[current_module].id + "_updates").html()) {
						$("#module_" + modules[current_module].id + "_updates").append($('<li><section class="integrity_errors"><span class="icon_small icon_small_done"></span> No errors found in ' + modules[current_module].module_name + '.</section></li>'));
					}
					if (current_module + 1 < total_modules) {
						download_module(current_module + 1);
					} else {
					}
				}
			}
		});
	}
</script>