<?
	$external = ($_GET["external"] == "true") ? true : false;
	$admin->requireLevel(1);
	$pages = $admin->getPageIds();
	$modules = $admin->getModuleForms();
	// Get the ids of items that are in each module.
	foreach ($modules as &$m) {
		$action = $admin->getModuleActionForForm($m);
		$module = $admin->getModule($action["module"]);
		if ($module["group"]) {
			$group = $admin->getModuleGroup($module["group"]);
			$m["module_name"] = "Modules&nbsp;&nbsp;&rsaquo;&nbsp;&nbsp;".$group["name"]."&nbsp;&nbsp;&rsaquo;&nbsp;&nbsp;".$module["name"]."&nbsp;&nbsp;&rsaquo;&nbsp;&nbsp;".$m["title"];
		} else {
			$m["module_name"] = "Modules&nbsp;&nbsp;&rsaquo;&nbsp;&nbsp;".$module["name"]."&nbsp;&nbsp;&rsaquo;&nbsp;&nbsp;".$m["title"];
		}

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
		<p>Running site integrity check with external link checking <? if ($external) { ?>enabled<? } else { ?>disabled<? } ?>.</p>
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
	BigTree.localPageList = [<? echo implode(",",$pages) ?>];
	BigTree.localModuleList = <?=json_encode($modules)?>;
	BigTree.localTotalPages = BigTree.localPageList.length;
	BigTree.localCurrentPage = 0;
	BigTree.localCurrentModule = 0;
	BigTree.localTotalModules = BigTree.localModuleList.length;
	BigTree.localCurrentItem = 0;
	
	BigTree.localDownloadPage = function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/dashboard/check-page-integrity/?external=<?=$external?>&id=" + BigTree.localPageList[BigTree.localCurrentPage], {
			complete: function(response) {
				if (response.responseText) {
					$("#pages_updates").append(response.responseText);
				}
				BigTree.localCurrentPage++;
				$("#pages_progress").html((Math.round(BigTree.localCurrentPage / BigTree.localTotalPages * 10000) / 100) + "%");
				if (BigTree.localCurrentPage < BigTree.localTotalPages) {
					BigTree.localDownloadPage();
				} else {
					$("#pages_progress").addClass("complete");
					if (!$("#pages_updates").html()) {
						$("#pages_updates").append($('<li><section class="integrity_errors"><span class="icon_small icon_small_done"></span>No errors found in Pages.</section></li>'));
					}
					BigTree.localDownloadModule(0);
				}
			}
		});
	};

	BigTree.localDownloadPage();
	
	
	BigTree.localDownloadModule = function(number) {
		BigTree.localCurrentModule = number;
		BigTree.localTotalItems = BigTree.localModuleList[number].items.length;
		BigTree.localCurrentItem = 0;
		if (BigTree.localTotalItems > 0) {
			BigTree.localDownloadItem(0);
		} else {
			$("#module_" + BigTree.localModuleList[BigTree.localCurrentModule].id + "_progress").html("100%").addClass("complete");
			$("#module_" + BigTree.localModuleList[BigTree.localCurrentModule].id + "_updates").append($('<li><section class="integrity_errors"><span class="icon_small icon_small_done"></span> No errors found in ' + BigTree.localModuleList[BigTree.localCurrentModule].module_name + '.</section></li>'));
			BigTree.localDownloadModule(BigTree.localCurrentModule + 1);
		}
	};
	
	BigTree.localDownloadItem = function(number) {
		$.ajax("<?=ADMIN_ROOT?>ajax/dashboard/check-module-integrity/?external=<?=$external?>&form=" + BigTree.localModuleList[BigTree.localCurrentModule].id + "&id=" + BigTree.localModuleList[BigTree.localCurrentModule].items[number], {
			complete: function(response) {
				if (response.responseText) {
					$("#module_" + BigTree.localModuleList[BigTree.localCurrentModule].id + "_updates").append(response.responseText);
				}
				BigTree.localCurrentItem++;
				$("#module_" + BigTree.localModuleList[BigTree.localCurrentModule].id + "_progress").html((Math.round(BigTree.localCurrentItem / BigTree.localTotalItems * 10000) / 100) + "%");
				if (BigTree.localCurrentItem < BigTree.localTotalItems) {
					BigTree.localDownloadItem(BigTree.localCurrentItem);
				} else {
					$("#module_" + BigTree.localModuleList[BigTree.localCurrentModule].id + "_progress").addClass("complete");
					if (!$("#module_" + BigTree.localModuleList[BigTree.localCurrentModule].id + "_updates").html()) {
						$("#module_" + BigTree.localModuleList[BigTree.localCurrentModule].id + "_updates").append($('<li><section class="integrity_errors"><span class="icon_small icon_small_done"></span> No errors found in ' + BigTree.localModuleList[BigTree.localCurrentModule].module_name + '.</section></li>'));
					}
					if (BigTree.localCurrentModule + 1 < BigTree.localTotalModules) {
						BigTree.localDownloadModule(BigTree.localCurrentModule + 1);
					}
				}
			}
		});
	};
</script>