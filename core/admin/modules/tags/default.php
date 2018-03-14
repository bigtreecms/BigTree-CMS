<div class="table">
	<summary>
		<input type="search" name="query" id="query" placeholder="Search" class="form_search" autocomplete="off" />
		<span class="form_search_icon"></span>
		<nav id="view_paging" class="view_paging"></nav>
	</summary>
	<header>
		<span class="tag_name">Name</span>
		<span class="tag_relationships">Number of Relationships</span>
		<span class="view_action view_action_merge">Merge</span>
	</header>
	<ul id="results">
		<?php include BigTree::path("admin/ajax/tags/get-page.php"); ?>	
	</ul>
</div>

<script>
	(function() {
		var Query = $("#query");
		var Results = $("#results");
		var Timer = false;

		function search() {
			Results.load("<?=ADMIN_ROOT?>ajax/tags/get-page/?page=1&query=" + escape(Query.val()));
		}

		Query.keyup(function() {
			if (Timer) {
				clearTimeout(Timer);
			}

			Timer = setTimeout(search, 400);
		});

		$(".table").on("click", "#view_paging a", function(ev) {
			ev.preventDefault();
			ev.stopPropagation();

			if ($(this).hasClass("active") || $(this).hasClass("disabled")) {
				return;
			}

			Results.load("<?=ADMIN_ROOT?>ajax/settings/get-page/?page=" + BigTree.cleanHref($(this).attr("href")) + "&query=" + escape(Query.val()));
		});
	})();	
</script>