<h1><span class="settings"></span>Settings</h1>

<div class="table">
	<summary>
		<input type="search" name="query" id="query" placeholder="Search" class="form_search" autocomplete="off" />
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

<script type="text/javascript">
	var deleteTimer,searchTimer;
	
	$("#query").keyup(function() {
		if (searchTimer) {
			clearTimeout(searchTimer);
		}
		searchTimer = setTimeout("reSearch()",400);
	});

	function reSearch() {
		$("#results").load("<?=$admin_root?>ajax/settings/get-page/?page=0&query=" + escape($("#query").val()));
	}
</script>