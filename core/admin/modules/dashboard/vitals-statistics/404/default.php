<?
	$admin->requireLevel(1);	
	$total = $admin->get404Total("404");
	$type = "404";
	$delete_action = "ignore";
?>
<div class="table">
	<summary>
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
		<? include BigTree::path("admin/ajax/dashboard/404/search.php") ?>
	</ul>
</div>