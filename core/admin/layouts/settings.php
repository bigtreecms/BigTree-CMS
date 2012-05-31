<? include BigTree::path("admin/layouts/_header.php") ?>
<div id="page">
	<div id="breadcrumb">
		<ul>
			<li><a href="<?=ADMIN_ROOT?>settings/">Settings</a></li>
			<li>&raquo;</li>
			<li><a href="<?=ADMIN_ROOT?>settings/" class="active"><? if ($bigtree["path"][2]) { ?><?=ucwords(str_replace("-"," ",$bigtree["path"][2]))?><? } else { ?>Landing<? } ?></a></li>
		</ul>
		<br class="clear" />
	</div>
	<div>
		<h2>Settings</h2>
		<br />
		<?=$bigtree["content"]?>
	</div>
</div>
<? include BigTree::path("admin/layouts/_footer.php") ?>