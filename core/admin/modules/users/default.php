<div class="table">
	<summary>
		<input type="search" name="query" id="query" placeholder="Search" class="form_search" autocomplete="off" />
		<span class="form_search_icon"></span>
		<ul id="view_paging" class="view_paging"></ul>
	</summary>
	<header>
		<span class="view_column users_name"><a class="sort_column asc" href="ASC" name="name">Name <em>&#9650;</em></a></span>
		<span class="view_column users_email"><a class="sort_column" href="ASC" name="email">Email <em></em></a></span>
		<span class="view_column users_company"><a class="sort_column" href="ASC" name="company">Company <em></em></a></span>
		<span class="view_action">Edit</span>
		<span class="view_action">Delete</span>
	</header>
	<ul id="results">
		<? include BigTree::path("admin/ajax/users/get-page.php") ?>	
	</ul>
</div>

<script>
	var current_page = 1;
	var sort = "name";
	var sortdir = "ASC";
	var search = "";
	var searchTimer;
	
	$("#query").keyup(function() {
		if (searchTimer) {
			clearTimeout(searchTimer);
		}
		searchTimer = setTimeout("_local_search()",400);
	});

	function _local_search() {
		$("#results").load("<?=ADMIN_ROOT?>ajax/users/get-page/?sort=" + escape(sort) + "&sort_direction=" + escape(sortdir) + "&page=1&query=" + escape($("#query").val()));
	}
	
	$(".icon_delete").live("click",function() {
		new BigTreeDialog("Delete User",'<p class="confirm">Are you sure you want to delete this user?',$.proxy(function() {
			$.ajax("<?=ADMIN_ROOT?>ajax/users/delete/", { type: "POST", data: { id: $(this).attr("href").substr(1) } });
		},this),"delete",false,"OK");
		
		return false;
	});
	
	$(".sort_column").live("click",function() {
		sortdir = BigTree.CleanHref($(this).attr("href"));
		sort = $(this).attr("name");
		current_page = 0;
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
		$("#results").load("<?=ADMIN_ROOT?>ajax/users/get-page/?sort=" + escape(sort) + "&sort_direction=" + escape(sortdir) + "&page=1&query=" + escape($("#query").val()));
		return false;
	});
	
	$("#view_paging a").live("click",function() {
		current_page = BigTree.CleanHref($(this).attr("href"));
		if ($(this).hasClass("active") || $(this).hasClass("disabled")) {
			return false;
		}
		$("#results").load("<?=ADMIN_ROOT?>ajax/users/get-page/?sort=" + escape(sort) + "&sort_direction=" + escape(sortdir) + "&page=" + current_page + "&query=" + escape($("#query").val()));

		return false;
	});
</script>