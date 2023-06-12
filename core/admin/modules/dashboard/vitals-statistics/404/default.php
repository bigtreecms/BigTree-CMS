<?php
	$total = $admin->get404Total("404");
	$type = "404";
	$delete_action = "ignore";
?>
<form method="POST" action="<?=MODULE_ROOT?>export/" id="redirects_table">
	<div class="table">
		<div class="developer_buttons">
			<button type="submit" title="Export 404s">
				Export 404s
				<span class="icon_small icon_small_export"></span>
			</button>
		</div>
		
		<summary>
			<input type="hidden" name="type" value="404" />
			<?php include BigTree::path("admin/modules/dashboard/vitals-statistics/404/_site-key-switcher.php"); ?>
			<input type="search" class="form_search" placeholder="Search" id="404_search" />
			<span class="form_search_icon"></span>
			<nav id="view_paging" class="view_paging"></nav>
		</summary>

		<header>
			<span class="requests_404">Requests</span>
			<span class="url_404">404 URL</span>
			<span class="redirect_404">Redirect</span>
			<span class="ignore_404">Ignore</span>
			<span class="ignore_404">Delete</span>
		</header>

		<ul id="results">
			<?php include BigTree::path("admin/ajax/dashboard/404/search.php"); ?>
		</ul>
	</div>
</form>

<script>
	// Prevent enter from submitting form
	$("#redirects_table input[type=search]").on("keydown", function(ev) {
		if (ev.keyCode == 13) {
			ev.preventDefault();

			return false;
		}
	});
</script>