<div class="table">
	<summary>
		<input type="search" name="query" id="query" placeholder="Search" class="form_search" autocomplete="off" />
		<span class="form_search_icon"></span>
		<nav id="view_paging" class="view_paging"></nav>
	</summary>
	<header>
		<span class="tag_name view_column"><a href="#" name="tag" class="js-sort-column sort_column">Name <em>&#9650;</em></a></span>
		<span class="tag_relationships view_column"><a href="#" name="usage_count" class="js-sort-column sort_column">Number of Relationships</a></span>
		<span class="view_action view_action_merge">Merge</span>
		<span class="view_action">Delete</span>
	</header>
	<ul id="results">
		<?php include BigTree::path("admin/ajax/tags/get-page.php"); ?>	
	</ul>
</div>

<script>
	(function() {
		var Current;
		var Page = 1;
		var Query = $("#query");
		var Results = $("#results");
		var Sort = "tag";
		var SortDirection = "ASC";
		var Timer = false;

		function switchPage(page) {
			Results.load("<?=ADMIN_ROOT?>ajax/tags/get-page/?sort=" + Sort + "&sort_dir=" + SortDirection + "&page=" + page + "&query=" + escape(Query.val()));			
		}

		function search() {
			Results.load("<?=ADMIN_ROOT?>ajax/tags/get-page/?page=1&sort=" + Sort + "&sort_dir=" + SortDirection + "&query=" + escape(Query.val()));
		}

		Query.keyup(function() {
			if (Timer) {
				clearTimeout(Timer);
			}

			Timer = setTimeout(search, 400);
		});

		Results.on("click", ".icon_delete", function(ev) {
			Current = $(this);
			BigTreeDialog({
				title: "Delete Tag",
				content: '<p class="confirm">Are you sure you want to delete this tag?</p>',
				icon: "delete",
				alternateSaveText: "OK",
				callback: function() {
					var id = Current.data("id");

					if (parseInt(id)) {
						var row = Current.parents("li");
						var list = row.parents("ul");
						
						$.secureAjax("<?=ADMIN_ROOT?>ajax/tags/delete/?id=" + id);
						
						row.remove();
					}
				}
			});
		});

		$(".js-sort-column").click(function(ev) {
			ev.preventDefault();
			ev.stopPropagation();

			var sort_field = $(this).attr("name");
			var direction = $(this).attr("href");
			var direction_char = "&#9650;";

			if (sort_field != Sort) {
				Sort = sort_field;
				SortDirection = "ASC";
			} else {
				if (SortDirection == "ASC") {
					SortDirection = "DESC";
					direction_char = "&#9660;";
				} else {
					SortDirection = "ASC";
				}
			}

			$(".js-sort-column em").remove();
			$(this).append("<em>" + direction_char + "</em>");

			switchPage(1);
		});

		$(".table").on("click", "#view_paging a", function(ev) {
			ev.preventDefault();
			ev.stopPropagation();

			if ($(this).hasClass("active") || $(this).hasClass("disabled")) {
				return;
			}

			switchPage(BigTree.cleanHref($(this).attr("href")));
		});
	})();	
</script>