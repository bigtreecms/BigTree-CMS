<?php
	namespace BigTree;

	/**
	 * @global \BigTreeAdmin $admin
	 * @global Module $module
	 * @global string $module_permission (set in ajax file)
	 * @global ModuleView $view
	 */
	
	if (isset($_GET["sort"])) {
		$sort = "`".$_GET["sort"]."` ".$_GET["sort_direction"];
	} elseif (isset($view->Settings["sort_column"])) {
		$sort = $view->Settings["sort_column"]." ".$view->Settings["sort_direction"];
	} elseif (isset($view->Settings["sort"])) {
		$sort = $view->Settings["sort"];
	} else {
		$sort = "`id` DESC";
	}
	
	// Retrieve the column and the sort direction from the consolidated ORDER BY statement.
	$sort = ltrim($sort,"`");
	$sort_column = SQL::nextColumnDefinition($sort);
	$sort_pieces = explode(" ",$sort);
	$sort_direction = end($sort_pieces);

	// See if we're searching for anything.
	$query = isset($_GET["search"]) ? htmlspecialchars($_GET["search"]) : "";
?>
<div class="table auto_modules">
	<summary>
		<input type="search" class="form_search" id="search" placeholder="<?=Text::translate("Search", true)?>" value="<?=$query?>" />
		<span class="form_search_icon"></span>
		<nav id="view_paging" class="view_paging"></nav>
	</summary>
	<header>
		<?php
			$x = 0;
			foreach ($view->Fields as $key => $field) {
				$x++;
				
				if ($key == $sort_column) {
					$active = " ".strtolower($sort_direction);
					if ($sort_direction == "ASC") {
						$achar = "&#9650;";
						$s_direction = "ASC";
					} else {
						$achar = "&#9660;";
						$s_direction = "DESC";
					}
				} else {
					$active = "";
					$s_direction = "ASC";
					$achar = "";
				}
		?>
		<span class="view_column" style="width: <?=$field["width"]?>px;"><a class="sort_column<?=$active?>" href="<?=$s_direction?>" name="<?=$key?>"><?=$field["title"]?> <em><?=$achar?></em></a></span>
		<?php
			}
		?>
		<span class="view_status"><?=Text::translate("Status")?></span>
		<span class="view_action" style="width: <?=(count($view->Actions) * 40)?>px;"><?php if (count($view->Actions) > 1) { echo Text::translate("Actions"); } ?></span>
	</header>
	<ul id="results">
		<?php include Router::getIncludePath("admin/ajax/auto-modules/views/searchable-page.php") ?>
	</ul>
</div>

<?php include Router::getIncludePath("admin/auto-modules/views/_common-js.php") ?>
<script>
	BigTree.localSortColumn = "<?=htmlspecialchars($sort_column)?>";
	BigTree.localSortDirection = "<?=htmlspecialchars($sort_direction)?>";
	BigTree.localSearchQuery = "<?=$query?>";
	BigTree.localSearch = function() {
		BigTree.localSearchQuery = encodeURIComponent($("#search").val());
		$("#results").load("<?=ADMIN_ROOT?>ajax/auto-modules/views/searchable-page/?sort=" + encodeURIComponent(BigTree.localSortColumn) + "&sort_direction=" + encodeURIComponent(BigTree.localSortDirection) + "&page=1&view=<?=$view->ID?>&module=<?=$module->Route?>&search=" + BigTree.localSearchQuery);
	};
	
	$(".table").on("click",".sort_column",function() {
		BigTree.localSortDirection = BigTree.cleanHref($(this).attr("href"));
		BigTree.localSortColumn = $(this).attr("name");
		if ($(this).hasClass("asc") || $(this).hasClass("desc")) {
			$(this).toggleClass("asc").toggleClass("desc");
			if (BigTree.localSortDirection == "DESC") {
				$(this).attr("href","ASC");
				BigTree.localSortDirection = "ASC";
		   		$(this).find("em").html("&#9650;");
			} else {
				$(this).attr("href","DESC");
				BigTree.localSortDirection = "DESC";
		   		$(this).find("em").html("&#9660;");
			}
		} else {
			var direction_char;

			if (BigTree.localSortDirection == "ASC") {
				direction_char = "&#9650;";
			} else {
				direction_char = "&#9660;";
			}

			$(this).parents("header").find(".sort_column").removeClass("asc").removeClass("desc").find("em").html("");
			$(this).addClass(BigTree.localSortDirection.toLowerCase()).find("em").html(direction_char);
		}
		$("#results").load("<?=ADMIN_ROOT?>ajax/auto-modules/views/searchable-page/?sort=" + encodeURIComponent(BigTree.localSortColumn) + "&sort_direction=" + encodeURIComponent(BigTree.localSortDirection) + "&view=<?=$view->ID?>&module=<?=$module->Route?>&search=" + BigTree.localSearchQuery + "&page=1");
		return false;
	}).on("click","#view_paging a",function() {
		if ($(this).hasClass("active") || $(this).hasClass("disabled")) {
			return false;
		}
		$("#results").load("<?=ADMIN_ROOT?>ajax/auto-modules/views/searchable-page/?sort=" + encodeURIComponent(BigTree.localSortColumn) + "&sort_direction=" + encodeURIComponent(BigTree.localSortDirection) + "&view=<?=$view->ID?>&module=<?=$module->Route?>&search=" + BigTree.localSearchQuery + "&page=" + BigTree.cleanHref($(this).attr("href")));

		return false;
	});
</script>