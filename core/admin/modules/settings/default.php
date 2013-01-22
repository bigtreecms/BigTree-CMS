<? $admin->requireLevel(1) ?>
<div class="table">
	<summary>
		<input type="search" name="query" id="query" placeholder="Search" class="form_search" autocomplete="off" />
		<span class="form_search_icon"></span>
		<ul id="view_paging" class="view_paging"></ul>
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
	var deleteTimer,searchTimer;
	
	$("#query").keyup(function() {
		if (searchTimer) {
			clearTimeout(searchTimer);
		}
		searchTimer = setTimeout("_local_search()",400);
	});

	function _local_search() {
		$("#results").load("<?=ADMIN_ROOT?>ajax/settings/get-page/?page=1&query=" + escape($("#query").val()));
	}
	
	$("#view_paging a").live("click",function() {
		current_page = BigTree.CleanHref($(this).attr("href"));
		if ($(this).hasClass("active") || $(this).hasClass("disabled")) {
			return false;
		}
		$("#results").load("<?=ADMIN_ROOT?>ajax/settings/get-page/?page=" + current_page + "&query=" + escape($("#query").val()));

		return false;
	});
</script>