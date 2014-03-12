<?
	BigTree::globalizeArray($bigtree["view"]);
		
	$m = BigTreeAutoModule::getModuleForView($bigtree["view"]);
	$perm = $admin->checkAccess($m);
	
	if (isset($_GET["sort"])) {
		$sort = $_GET["sort"]." ".$_GET["sort_direction"];
	} elseif (isset($options["sort_column"])) {
		$sort = $options["sort_column"]." ".$options["sort_direction"];
	} elseif (isset($options["sort"])) {
		$sort = $options["sort"];
	} else {
		$sort = "id DESC";
	}
	// Retrieve the column and the sort direction from the consolidated ORDER BY statement.
	$sort = ltrim($sort,"`");
	$sort_column = BigTree::nextSQLColumnDefinition($sort);
	$sort_pieces = explode(" ",$sort);
	$sort_direction = end($sort_pieces);
	// See if we're searching for anything.
	$search = isset($_GET["search"]) ? $_GET["search"] : "";
?>
<div class="table auto_modules">
	<summary>
		<input type="search" class="form_search" id="search" placeholder="Search" value="<?=htmlspecialchars($search)?>" />
		<span class="form_search_icon"></span>
		<nav id="view_paging" class="view_paging"></nav>
	</summary>
	<header>
		<?
			$x = 0;
			foreach ($fields as $key => $field) {
				$x++;
				
				if ($key == $sort_column) {
					$active = " ".strtolower($sort_direction);
					if ($sort_direction == "ASC") {
						$achar = "&#9650;";
						$s_direction = "ASC";
					} else {
						$s_direction = "DESC";
						$achar = "&#9660;";
					}
				} else {
					$active = "";
					$s_direction = "ASC";
					$achar = "";
				}
		?>
		<span class="view_column" style="width: <?=$field["width"]?>px;"><a class="sort_column<?=$active?>" href="<?=$s_direction?>" name="<?=$key?>"><?=$field["title"]?> <em><?=$achar?></em></a></span>
		<?
			}
		?>
		<span class="view_status">Status</span>
		<span class="view_action" style="width: <?=(count($bigtree["view"]["actions"]) * 40)?>px;"><? if (count($bigtree["view"]["actions"]) > 1) { ?>Actions<? } ?></span>
	</header>
	<ul id="results">
		<? include BigTree::path("admin/ajax/auto-modules/views/searchable-page.php") ?>
	</ul>
</div>

<? include BigTree::path("admin/auto-modules/views/_common-js.php") ?>
<script>
	BigTree.localSortColumn = "<?=$sort_column?>";
	BigTree.localSortDirection = "<?=$sort_direction?>";
	BigTree.localSearchQuery = "";
	BigTree.localSearch = function() {
		BigTree.localSearchQuery = escape($("#search").val());
		$("#results").load("<?=ADMIN_ROOT?>ajax/auto-modules/views/searchable-page/?sort=" + escape(BigTree.localSortColumn) + "&sort_direction=" + escape(BigTree.localSortDirection) + "&page=1&view=<?=$bigtree["view"]["id"]?>&module=<?=$bigtree["module"]["route"]?>&search=" + BigTree.localSearchQuery);
	};
	
	$(".table").on("click",".sort_column",function() {
		BigTree.localSortDirection = BigTree.CleanHref($(this).attr("href"));
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
			if (BigTree.localSortDirection == "ASC") {
				dchar = "&#9650;";
			} else {
				dchar = "&#9660;";
			}
			$(this).parents("header").find(".sort_column").removeClass("asc").removeClass("desc").find("em").html("");
			$(this).addClass(BigTree.localSortDirection.toLowerCase()).find("em").html(dchar);
		}
		$("#results").load("<?=ADMIN_ROOT?>ajax/auto-modules/views/searchable-page/?sort=" + escape(BigTree.localSortColumn) + "&sort_direction=" + escape(BigTree.localSortDirection) + "&view=<?=$bigtree["view"]["id"]?>&module=<?=$bigtree["module"]["route"]?>&search=" + BigTree.localSearchQuery + "&page=1");
		return false;
	}).on("click","#view_paging a",function() {
		if ($(this).hasClass("active") || $(this).hasClass("disabled")) {
			return false;
		}
		$("#results").load("<?=ADMIN_ROOT?>ajax/auto-modules/views/searchable-page/?sort=" + escape(BigTree.localSortColumn) + "&sort_direction=" + escape(BigTree.localSortDirection) + "&view=<?=$bigtree["view"]["id"]?>&module=<?=$bigtree["module"]["route"]?>&search=" + BigTree.localSearchQuery + "&page=" + BigTree.CleanHref($(this).attr("href")));

		return false;
	});
</script>