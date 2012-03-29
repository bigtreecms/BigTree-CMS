<?
	$total = $admin->get404Total("301");
	$type = "301";
	$breadcrumb[] = array("link" => "dashboard/vitals-statistics/404/301/", "title" => "301 Redirects");
	$delete_action = "ignore";
?>
<h1>
	<span class="page_404"></span>301 Redirects
	<? include BigTree::path("admin/modules/dashboard/vitals-statistics/_jump.php"); ?>
</h1>
<? include BigTree::path("admin/modules/dashboard/vitals-statistics/404/_nav.php") ?>
<div class="table">
	<summary class="taller">
		<input type="search" class="form_search" placeholder="Search" id="404_search" />
		<p><?=$total?> URL<? if ($total != 1) { ?>s<? } ?> have 301 redirects &mdash; Redirect URLs save automatically as you type them.</p>
	</summary>
	<header>
		<span class="requests_404">Requests</span>
		<span class="url_404">404 URL</span>
		<span class="redirect_404">Redirect</span>
		<span class="ignore_404">Ignore</span>
	</header>
	<ul id="results">
		<? include BigTree::path("admin/ajax/dashboard/404/search.php") ?>
	</ul>
</div>