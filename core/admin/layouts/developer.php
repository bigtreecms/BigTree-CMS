<?
	include BigTree::path("admin/layouts/_header.php");
	
	$subpath = BigTree::path("admin/modules/developer/".$path[2]."/_subnav.php");
	if (file_exists($subpath)) {
		include $subpath;
	}
?>
<div id="page">
	<div id="breadcrumb">
		<ul>
			<li><a href="<?=$admin_root?>">Developer</a></li> 
			<li>&raquo;</li>
			<? if ($path[2]) { ?>
			<li><a class="active" href="#"><?=ucwords(str_replace("-"," ",$path[2]))?></a></li>
			<? } else { ?>
			<li><a class="active" href="#">Landing</a></li>
			<? } ?>
		</ul>
		<br class="clear" />
	</div>
	<div>
		<h2>Developer</h2>
		<div class="add_level">
			<a href="<?=$admin_root?>developer/" class="home">Home</a>
			<a href="<?=$admin_root?>developer/templates/view/" class="templates">Templates</a>
			<a href="<?=$admin_root?>developer/modules/view/" class="modules">Modules</a>
			<a href="<?=$admin_root?>developer/modules/groups/view/" class="groups">Module Groups</a>
			<a href="<?=$admin_root?>developer/field-types/view/" class="page">Field Types</a>
			<a href="<?=$admin_root?>developer/callouts/view/" class="callouts">Callouts</a>
			<a href="<?=$admin_root?>developer/settings/view/" class="settings">Settings</a>
			<a href="<?=$admin_root?>developer/feeds/view/" class="rss">Feeds</a>
			<a href="<?=$admin_root?>developer/foundry/view/" class="foundry">Foundry</a>
		</div>
		<?=$content?>
	</div>
</div>
<? include BigTree::path("admin/layouts/_footer.php") ?>