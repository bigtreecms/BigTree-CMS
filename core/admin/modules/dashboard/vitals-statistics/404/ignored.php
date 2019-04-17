<?php
	namespace BigTree;
	
	$type = "ignored";
	$delete_action = "unignore";
?>
<div class="table">
	<div class="table_summary">
		<?php include Router::getIncludePath("admin/modules/dashboard/vitals-statistics/404/_site-key-switcher.php"); ?>
		<input type="search" class="form_search" placeholder="<?=Text::translate("Search")?>" id="404_search" />
		<span class="form_search_icon"></span>
		<nav id="view_paging" class="view_paging"></nav>
	</div>
	<header>
		<span class="requests_404"><?=Text::translate("Requests")?></span>
		<span class="url_404"><?=Text::translate("404 URL")?></span>
		<span class="redirect_404"><?=Text::translate("Redirect")?></span>
		<span class="ignore_404"><?=Text::translate("Unignore")?></span>
		<span class="ignore_404"><?=Text::translate("Delete")?></span>
	</header>
	<ul id="results">
		<?php include Router::getIncludePath("admin/ajax/dashboard/404/search.php"); ?>
	</ul>
</div>