<?	
	BigTree::globalizeArray($view);
		
	$m = BigTreeAutoModule::getModuleForView($view);
	$perm = $admin->checkAccess($m);
	
	$suffix = $suffix ? "-".$suffix : "";
?>
<div class="table auto_modules">
	<summary>
		<input type="search" class="form_search" id="search" placeholder="Search" />
		<ul id="view_paging" class="view_paging"></ul>
	</summary>
	<header>
		<?
			$x = 0;
			foreach ($fields as $key => $field) {
				$x++;
				
				if ($key == $options["sort_column"]) {
					$active = " ".strtolower($options["sort_direction"]);
					$dir = $options["sort_direction"];
					if ($dir == "ASC") {
						$achar = "&#9650;";
					} else {
						$achar = "&#9660;";
					}
				} else {
					$active = "";
					$dir = "ASC";
					$achar = "";
				}
		?>
		<span class="view_column" style="width: <?=$field["width"]?>px;"><a class="sort_column<?=$active?>" href="<?=$dir?>" name="<?=$key?>"><?=$field["title"]?> <em><?=$achar?></em></a></span>
		<?
			}
		?>
		<span class="view_status">Status</span>
		<?
			foreach ($actions as $action => $status) {
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
<script type="text/javascript">
	var mpage = 0;
	var sort = "<?=$view["options"]["sort_column"]?>";
	var sortdir = "<?=$view["options"]["sort_direction"]?>";
	var search = "";
	
	function reSearch() {
		search = escape($("#search").val());
		$("#results").load("<?=$admin_root?>ajax/auto-modules/views/searchable-page/?sort=" + escape(sort) + "&sort_direction=" + escape(sortdir) + "&page=0&view=<?=$view["id"]?>&module=<?=$module["route"]?>&search=" + search);
	}
	
	$(".sort_column").live("click",function() {
		sortdir = BigTree.CleanHref($(this).attr("href"));
		sort = $(this).attr("name");
		mpage = 0;
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
		$("#results").load("<?=$admin_root?>ajax/auto-modules/views/searchable-page/?sort=" + escape(sort) + "&sort_direction=" + escape(sortdir) + "&view=<?=$view["id"]?>&module=<?=$module["route"]?>&search=" + search + "&page=" + mpage);
		return false;
	});
	
	$("#view_paging a").live("click",function() {
		mpage = BigTree.CleanHref($(this).attr("href"));
		if ($(this).hasClass("active") || $(this).hasClass("disabled"))
			return false;
		$("#results").load("<?=$admin_root?>ajax/auto-modules/views/searchable-page/?sort=" + escape(sort) + "&sort_direction=" + escape(sortdir) + "&view=<?=$view["id"]?>&module=<?=$module["route"]?>&search=" + search + "&page=" + mpage);

		return false;
	});
</script>