<?php
	namespace BigTree;

	$external = ($_GET["external"] == "true") ? true : false;
	$pages = Page::allIDs();
	$forms = ModuleInterface::allByModuleAndType(false, "form", "title ASC", true);

	// Get the ids of items that are in each module.
	foreach ($forms as &$form) {
		$module = new Module($form->Module);
		
		if ($module->Group) {
			$group = new ModuleGroup($module->Group);
			$form->ModuleName = Text::translate("Modules")."&nbsp;&nbsp;&rsaquo;&nbsp;&nbsp;".$group->Name."&nbsp;&nbsp;&rsaquo;&nbsp;&nbsp;".$module->Name."&nbsp;&nbsp;&rsaquo;&nbsp;&nbsp;".$form->Title;
		} else {
			$form->ModuleName = Text::translate("Modules")."&nbsp;&nbsp;&rsaquo;&nbsp;&nbsp;".$module->Name."&nbsp;&nbsp;&rsaquo;&nbsp;&nbsp;".$form->Title;
		}

	    $form->Items = SQL::fetchAllSingle("SELECT id FROM `".$form->Table."`");
	}
?>
<div class="table">
	<summary>
		<p><?=Text::translate("Running site integrity check with external link checking")?> <?=Text::translate($external ? "enabled" : "disabled")?>.</p>
	</summary>
	
	<header>
		<span class="integrity_errors"><?=Text::translate("Errors")?></span>
	</header>
	
	<header class="group"><span class="integrity_progress" id="pages_progress">0%</span><?=Text::translate("Pages")?></header>
	
	<ul id="pages_updates"></ul>
	
	<?php foreach ($forms as $form) { ?>
	<header class="group"><span class="integrity_progress" id="module_<?=$form->ID?>_progress">0%</span><?=$form->ModuleName?></header>
	<ul id="module_<?=$form->ID?>_updates"></ul>
	<?php } ?>
</div>

<script>
	(function() {
		var CurrentItem = 0;
		var CurrentModule = 0;
		var CurrentPage = 0;
		var ModuleList = <?=json_encode($forms)?>;
		var PageList = [<?php echo implode(",", $pages) ?>];
		var PageProgressContainer = $("#pages_progress");
		var PageUpdatesContainer = $("#pages_updates");
		var TotalModules = ModuleList.length;
		var TotalPages = PageList.length;
		
		function checkPages() {
			$.ajax("<?=ADMIN_ROOT?>ajax/dashboard/integrity-check/page/?external=<?=$external?>&id=" + PageList[CurrentPage], {
				complete: function(response) {
					if (response.responseText) {
						$("#pages_updates").append(response.responseText);
					}
					
					CurrentPage++;
					PageProgressContainer.html((Math.round(CurrentPage / TotalPages * 10000) / 100) + "%");
					
					if (CurrentPage < TotalPages) {
						checkPages();
					} else {
						PageProgressContainer.addClass("complete");
						
						if (!PageUpdatesContainer.html()) {
							PageUpdatesContainer.append($('<li><section class="integrity_errors"><span class="icon_small icon_small_done"></span><?=Text::translate("No errors found in Pages.")?></section></li>'));
						}
						
						checkModule(0);
					}
				}
			});
		}
		
		function checkModule(number) {
			var TotalItems = ModuleList[number].items.length;
			
			CurrentModule = number;
			CurrentItem = 0;
			
			if (TotalItems > 0) {
				checkModuleEntry(0);
			} else {
				$("#module_" + ModuleList[CurrentModule].id + "_progress").html("100%").addClass("complete");
				$("#module_" + ModuleList[CurrentModule].id + "_updates").append($('<li><section class="integrity_errors"><span class="icon_small icon_small_done"></span> <?=Text::translate("No errors found in")?> ' + ModuleList[CurrentModule].ModuleName + '.</section></li>'));
				
				checkModule(CurrentModule + 1);
			}
		}
		
		function checkModuleEntry(number) {
			$.ajax("<?=ADMIN_ROOT?>ajax/dashboard/integrity-check/module/?external=<?=$external?>&form=" + ModuleList[CurrentModule].id + "&id=" + ModuleList[CurrentModule].items[number], {
				complete: function(response) {
					var updates_container = $("#module_" + ModuleList[CurrentModule].id + "_updates");
					var progress_container = $("#module_" + ModuleList[CurrentModule].id + "_progress");
					
					if (response.responseText) {
						updates_container.append(response.responseText);
					}
					
					CurrentItem++;
					progress_container.html((Math.round(CurrentItem / TotalItems * 10000) / 100) + "%");
					
					if (CurrentItem < TotalItems) {
						checkModuleEntry(CurrentItem);
					} else {
						progress_container.addClass("complete");
						
						if (!updates_container.html()) {
							updates_container.append($('<li><section class="integrity_errors"><span class="icon_small icon_small_done"></span> <?=Text::translate("No errors found in")?> ' + ModuleList[CurrentModule].ModuleName + '.</section></li>'));
						}
						
						if (CurrentModule + 1 < TotalModules) {
							DownloadModule(CurrentModule + 1);
						}
					}
				}
			});
		}
		
		checkPages();
	})();
</script>