<div class="table">
	<summary>
		<input type="search" name="query" id="query" placeholder="Search" class="form_search" autocomplete="off" />
		<span class="form_search_icon"></span>
		<nav id="view_paging" class="view_paging"></nav>
	</summary>
	<header>
		<span class="view_column users_name"><a class="sort_column asc" href="ASC" name="name">Name <em>&#9650;</em></a></span>
		<span class="view_column users_email"><a class="sort_column" href="ASC" name="email">Email <em></em></a></span>
		<span class="view_column users_company"><a class="sort_column" href="ASC" name="company">Company <em></em></a></span>
		<span class="view_action" style="width: 80px;">Actions</span>
	</header>
	<ul id="results">
		<?php include BigTree::path('admin/ajax/users/get-page.php') ?>	
	</ul>
</div>

<script>
	BigTree.localSortColumn = "name";
	BigTree.localSortDirection = "ASC";
	BigTree.localSearchTimer = false;
	BigTree.localSearch = function() {
		$("#results").load("<?=ADMIN_ROOT?>ajax/users/get-page/?sort=" + escape(BigTree.localSortColumn) + "&sort_direction=" + escape(BigTree.localSortDirection) + "&page=1&query=" + escape($("#query").val()));
	}

	$("#query").keyup(function() {
		if (BigTree.localSearchTimer) {
			clearTimeout(BigTree.localSearchTimer);
		}
		BigTree.localSearchTimer = setTimeout("BigTree.localSearch()",400);
	});
	
	$(".table").on("click",".icon_delete",function() {
		if ($(this).hasClass("disabled_icon")) {
			return false;
		}

		BigTreeDialog({
			title: "Delete User",
			content: '<p class="confirm">Are you sure you want to delete this user?</p>',
			icon: "delete",
			alternateSaveText: "OK",
			callback: $.proxy(function() {
				$.ajax("<?=ADMIN_ROOT?>ajax/users/delete/", { type: "POST", data: { id: $(this).attr("href").substr(1) } });
			},this)
		});
		
		return false;
	}).on("click",".sort_column",function() {
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
			if (BigTree.localSortDirection == "ASC") {
				var dchar = "&#9650;";
			} else {
				var dchar = "&#9660;";
			}
			$(this).parents("header").find(".sort_column").removeClass("asc").removeClass("desc").find("em").html("");
			$(this).addClass(BigTree.localSortDirection.toLowerCase()).find("em").html(dchar);
		}
		$("#results").load("<?=ADMIN_ROOT?>ajax/users/get-page/?sort=" + escape(BigTree.localSortColumn) + "&sort_direction=" + escape(BigTree.localSortDirection) + "&page=1&query=" + escape($("#query").val()));
		return false;
	}).on("click","#view_paging a",function() {
		if ($(this).hasClass("active") || $(this).hasClass("disabled")) {
			return false;
		}
		$("#results").load("<?=ADMIN_ROOT?>ajax/users/get-page/?sort=" + escape(BigTree.localSortColumn) + "&sort_direction=" + escape(BigTree.localSortDirection) + "&page=" + BigTree.cleanHref($(this).attr("href")) + "&query=" + escape($("#query").val()));

		return false;
	});
</script>