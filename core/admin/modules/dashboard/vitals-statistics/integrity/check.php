<?php
	$external = ($_GET["external"] == "true") ? true : false;
	$admin->requireLevel(1);
	
	// See if an integrity check session currently exists
	$session_key = "session.".($external ? "external" : "internal");
	$existing_session = BigTreeCMS::cacheGet("org.bigtreecms.integritycheck", $session_key);
	
	if ($existing_session) {
		$has_existing_session = true;
		$pages = $existing_session["pages"];
		$modules = $existing_session["modules"];
		$current_page = $existing_session["current_page"];
		$current_module = $existing_session["current_module"];
		$current_item = $existing_session["current_item"];
	} else {
		$has_existing_session = false;
		$pages = $admin->getPageIds();
		$modules = $admin->getModuleForms();
		$current_page = 0;
		$current_module = 0;
		$current_item = 0;
		
		// Get the ids of items that are in each module.
		foreach ($modules as &$module_form) {
			$action = $admin->getModuleActionForForm($module_form);
			$module = $admin->getModule($action["module"]);
			
			// If there's a single view that has a table that matches the form's table and it has a filter we need to apply it
			$view_match_count = 0;
			$filter = null;
			
			if (!empty($module["views"])) {
				foreach ($module["views"] as $view) {
					if ($view["table"] == $module_form["table"]) {
						$view_match_count++;
						
						if (!empty($view["settings"]["filter"])) {
							$filter = $view["settings"]["filter"];
						}
					}
				}
			}
			
			if ($module["group"]) {
				$group = $admin->getModuleGroup($module["group"]);
				$module_form["module_name"] = "Modules&nbsp;&nbsp;&rsaquo;&nbsp;&nbsp;".$group["name"]."&nbsp;&nbsp;&rsaquo;&nbsp;&nbsp;".$module["name"]."&nbsp;&nbsp;&rsaquo;&nbsp;&nbsp;".$module_form["title"];
			} else {
				$module_form["module_name"] = "Modules&nbsp;&nbsp;&rsaquo;&nbsp;&nbsp;".$module["name"]."&nbsp;&nbsp;&rsaquo;&nbsp;&nbsp;".$module_form["title"];
			}
			
			$module_form["module_route"] = $module["route"];
			$module_form["items"] = array();
			
			if ($view_match_count === 1 && $filter) {
				$query = SQL::query("SELECT * FROM `".$module_form["table"]."`");
				
				while ($item = $query->fetch()) {
					if (call_user_func($filter,$item)) {
						$module_form["items"][] = $item["id"];
					}
				}
			} else {
				$module_form["items"] = SQL::fetchAllSingle("SELECT id FROM `".$module_form["table"]."`");
			}
		}
		
		BigTreeCMS::cachePut("org.bigtreecms.integritycheck", $session_key, [
			"pages" => $pages,
			"modules" => $modules,
			"current_page" => $current_page,
			"current_module" => $current_module,
			"current_item" => $current_item,
		]);
	}
?>
<div class="table">
	<summary>
		<a class="button small" href="<?=ADMIN_ROOT?>ajax/dashboard/integrity-check/export/?external=<?=($external ? "true" : "false")?>">Export CSV</a>
		<div class="integrity_progress"></div>
		<p>
			Running site integrity check with external link checking <?php if ($external) { ?>enabled<?php } else { ?>disabled<?php } ?>. <?php if ($existing_session) { ?><strong>Found existing session, resuming...</strong><?php } ?>
		</p>
	</summary>
	<header>
		<span class="integrity_errors">Errors</span>
	</header>
	<header class="group"><span class="integrity_progress" id="pages_progress"><?=($current_item > 0 ? 100 : 0)?>%</span>Pages</header>
	<ul id="pages_updates">
		<?php
			if ($has_existing_session) {
				if (!empty($existing_session["errors"]["pages"])) {
					foreach ($existing_session["errors"]["pages"] as $id => $page_errors) {
						$page = SQL::fetch("SELECT nav_title FROM bigtree_pages WHERE id = ?", $id);
						
						foreach ($page_errors as $title => $error_types) {
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
		<?php
								}
							}
						}
					}
				}
			}
		?>
	</ul>
	<?php
		$x = 0;
		
		foreach ($modules as $module) {
	?>
	<header class="group"><span class="integrity_progress" id="module_<?=$module["id"]?>_progress"><?=($current_module > $x ? 100 : 0)?>%</span><?=$module["module_name"]?></header>
	<ul id="module_<?=$module["id"]?>_updates">
		<?php
			if (!empty($existing_session["errors"][$module["id"]])) {
				$action = $admin->getModuleActionForForm($module);
				
				foreach ($existing_session["errors"][$module["id"]] as $entry_id => $module_errors) {
					foreach ($module_errors as $field  => $error_types) {
						foreach ($error_types as $type => $errors) {
							foreach ($errors as $error) {
				?>
				<li>
					<section class="integrity_errors">
						<a href="<?=ADMIN_ROOT.$module["module_route"]."/".$action["route"]."/".$entry_id?>/" target="_blank">Edit</a>
						<span class="icon_small icon_small_warning"></span>
						<p>Broken <?=(($type == "img") ? "Image" : "Link")?>: <?=BigTree::safeEncode($error)?> in field &ldquo;<?=$field?>&rdquo;</p>
					</section>
				</li>
				<?php
							}
						}
					}
				}
			}
		?>
	</ul>
	<?php
			$x++;
		}
	?>
</div>

<script>
	BigTree.localPageList = [<?php echo implode(",",$pages) ?>];
	BigTree.localModuleList = <?=json_encode($modules)?>;
	BigTree.localTotalPages = BigTree.localPageList.length;
	BigTree.localCurrentPage = <?=$current_page?>;
	BigTree.localCurrentModule = <?=$current_module?>;
	BigTree.localTotalModules = BigTree.localModuleList.length;
	BigTree.localCurrentItem = <?=$current_item?>;
	
	BigTree.localDownloadPage = function() {
		$.ajax({
			complete: function(response) {
				if (response.status == 200 && response.responseText) {
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
			},
			data: {
				external: <?=$external?>,
				id: BigTree.localPageList[BigTree.localCurrentPage],
				index: BigTree.localCurrentPage
			},
			method: "POST",
			url: "<?=ADMIN_ROOT?>ajax/dashboard/integrity-check/page/",
		});
	};
	
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
		$.ajax({
			complete: function(response) {
				if (response.status == 200 && response.responseText) {
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
			},
			data: {
				external: <?=$external?>,
				form: BigTree.localModuleList[BigTree.localCurrentModule].id,
				id: BigTree.localModuleList[BigTree.localCurrentModule].items[number],
				index: number,
				module: BigTree.localCurrentModule,
			},
			method: "POST",
			url: "<?=ADMIN_ROOT?>ajax/dashboard/integrity-check/module/",
		});
	};

	// Allow for resuming from completed page state
	if (BigTree.localCurrentModule === 0 && BigTree.localCurrentItem === 0) {
		BigTree.localDownloadPage();
	} else {
		BigTree.localTotalItems = BigTree.localModuleList[BigTree.localCurrentModule].items.length;
		BigTree.localDownloadItem(BigTree.localCurrentItem);
	}
</script>