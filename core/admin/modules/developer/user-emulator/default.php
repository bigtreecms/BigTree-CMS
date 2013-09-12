<section class="inset_block">
	<p>The User Emulator allows you to login as another user of the CMS without knowing their password.<br />Upon clicking on the tool icon you will be logged in as the selected user.</p>
</section>
<div class="table">
	<summary>
		<input type="search" name="query" id="query" placeholder="Search" class="form_search" autocomplete="off" />
		<span class="form_search_icon"></span>
		<nav id="view_paging" class="view_paging"></nav>
	</summary>
	<header>
		<span class="view_column users_name_emulate"><a class="sort_column asc" href="ASC" name="name">Name <em>&#9650;</em></a></span>
		<span class="view_column users_email"><a class="sort_column" href="ASC" name="email">Email <em></em></a></span>
		<span class="view_column users_company"><a class="sort_column" href="ASC" name="company">Company <em></em></a></span>
		<span class="view_action"></span>
	</header>
	<ul id="results">
		<? include BigTree::path("admin/ajax/users/get-emulate-page.php") ?>	
	</ul>
</div>

<script>
	BigTree.localSortColumn = "name";
	BigTree.localSortDirection = "ASC";
	BigTree.localSearchTimer = false;
	BigTree.localSearch = function() {
		$("#results").load("<?=ADMIN_ROOT?>ajax/users/get-emulate-page/?sort=" + escape(BigTree.localSortColumn) + "&sort_direction=" + escape(BigTree.localSortDirection) + "&page=1&query=" + escape($("#query").val()));
	}

	$("#query").keyup(function() {
		if (BigTree.localSearchTimer) {
			clearTimeout(BigTree.localSearchTimer);
		}
		BigTree.localSearchTimer = setTimeout("BigTree.localSearch()",400);
	});
	
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
		$("#results").load("<?=ADMIN_ROOT?>ajax/users/get-emulate-page/?sort=" + escape(BigTree.localSortColumn) + "&sort_direction=" + escape(BigTree.localSortDirection) + "&page=1&query=" + escape($("#query").val()));
		return false;
	}).on("click","#view_paging a",function() {
		if ($(this).hasClass("active") || $(this).hasClass("disabled")) {
			return false;
		}
		$("#results").load("<?=ADMIN_ROOT?>ajax/users/get-emulate-page/?sort=" + escape(BigTree.localSortColumn) + "&sort_direction=" + escape(BigTree.localSortDirection) + "&page=" + BigTree.CleanHref($(this).attr("href")) + "&query=" + escape($("#query").val()));

		return false;
	});
</script>