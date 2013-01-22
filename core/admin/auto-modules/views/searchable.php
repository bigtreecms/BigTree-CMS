<?	
	BigTree::globalizeArray($view);
		
	$m = BigTreeAutoModule::getModuleForView($view);
	$perm = $admin->checkAccess($m);
	
	$suffix = $suffix ? "-".$suffix : "";
	
	
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
		<ul id="view_paging" class="view_paging"></ul>
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
					} else {
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
		<?
			foreach ($actions as $action => $data) {
				if ($data != "on") {
					$data = json_decode($data,true);
					$action = $data["name"];
				}
		?>
		<span class="view_action"><?=$action?></span>
		<?
			}
		?>
	</header>
	<ul id="results">
		<? include BigTree::path("admin/ajax/auto-modules/views/searchable-page.php") ?>
	</ul>
</div>

<? include BigTree::path("admin/auto-modules/views/_common-js.php") ?>
<script>
	var current_page = 1;
	var sort = "<?=$sort_column?>";
	var sortdir = "<?=$sort_direction?>";
	var search = "";
	
	function _local_search() {
		search = escape($("#search").val());
		$("#results").load("<?=ADMIN_ROOT?>ajax/auto-modules/views/searchable-page/?sort=" + escape(sort) + "&sort_direction=" + escape(sortdir) + "&page=1&view=<?=$view["id"]?>&module=<?=$module["route"]?>&search=" + search);
	}
	
	$(".sort_column").live("click",function() {
		sortdir = BigTree.CleanHref($(this).attr("href"));
		sort = $(this).attr("name");
		current_page = 1;
		if ($(this).hasClass("asc") || $(this).hasClass("desc")) {
			$(this).toggleClass("asc").toggleClass("desc");
			if (sortdir == "DESC") {
				$(this).attr("href","ASC");
				sortdir = "ASC";
		   		$(this).find("em").html("&#9650;");
			} else {
				$(this).attr("href","DESC");
				sortdir = "DESC";
		   		$(this).find("em").html("&#9660;");
			}
		} else {
			if (sortdir == "ASC") {
				dchar = "&#9650;";
			} else {
				dchar = "&#9660;";
			}
			$(this).parents("header").find(".sort_column").removeClass("asc").removeClass("desc").find("em").html("");
			$(this).addClass(sortdir.toLowerCase()).find("em").html(dchar);
		}
		$("#results").load("<?=ADMIN_ROOT?>ajax/auto-modules/views/searchable-page/?sort=" + escape(sort) + "&sort_direction=" + escape(sortdir) + "&view=<?=$view["id"]?>&module=<?=$module["route"]?>&search=" + search + "&page=" + current_page);
		return false;
	});
	
	$("#view_paging a").live("click",function() {
		current_page = BigTree.CleanHref($(this).attr("href"));
		if ($(this).hasClass("active") || $(this).hasClass("disabled")) {
			return false;
		}
		$("#results").load("<?=ADMIN_ROOT?>ajax/auto-modules/views/searchable-page/?sort=" + escape(sort) + "&sort_direction=" + escape(sortdir) + "&view=<?=$view["id"]?>&module=<?=$module["route"]?>&search=" + search + "&page=" + current_page);

		return false;
	});
</script>