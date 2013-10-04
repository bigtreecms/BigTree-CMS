<? $admin->requireLevel(1) ?>
<div class="table">
	<summary>
		<input type="search" name="query" id="query" placeholder="Search" class="form_search" autocomplete="off" />
		<span class="form_search_icon"></span>
		<nav id="view_paging" class="view_paging"></nav>
	</summary>
	<header>
		<span class="settings_name">Name</span>
		<span class="settings_value">Value</span>
		<span class="view_action">Edit</span>
	</header>
	<ul id="results">
		<? include BigTree::path("admin/ajax/settings/get-page.php") ?>	
	</ul>
</div>

<script>
	BigTree.localSearchTimer = false;
	BigTree.localSearch = function() {
		$("#results").load("<?=ADMIN_ROOT?>ajax/settings/get-page/?page=1&query=" + escape($("#query").val()));
	};
	
	$("#query").keyup(function() {
		if (BigTree.localSearchTimer) {
			clearTimeout(BigTree.localSearchTimer);
		}
		BigTree.localSearchTimer = setTimeout("BigTree.localSearch()",400);
	});

	$(".table").on("click","#view_paging a",function() {
		if ($(this).hasClass("active") || $(this).hasClass("disabled")) {
			return false;
		}
		$("#results").load("<?=ADMIN_ROOT?>ajax/settings/get-page/?page=" + BigTree.CleanHref($(this).attr("href")) + "&query=" + escape($("#query").val()));

		return false;
	});
</script>