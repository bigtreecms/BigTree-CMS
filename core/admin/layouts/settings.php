<? include BigTree::path("admin/layouts/_header.php") ?>
<div id="page">
	<div id="breadcrumb">
		<ul>
			<li><a href="<?=$admin_root?>settings/">Settings</a></li>
			<li>&raquo;</li>
			<li><a href="<?=$admin_root?>settings/" class="active"><? if ($path[2]) { ?><?=ucwords(str_replace("-"," ",$path[2]))?><? } else { ?>Landing<? } ?></a></li>
		</ul>
		<br class="clear" />
	</div>
	<div>
		<h2>Settings</h2>
		<br />
		<?=$content?>
	</div>
</div>
<? include BigTree::path("admin/layouts/_footer.php") ?>