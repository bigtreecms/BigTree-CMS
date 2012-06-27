<?
	include BigTree::path("admin/layouts/_header.php");
	
	$subpath = BigTree::path("admin/modules/developer/".$bigtree["path"][2]."/_subnav.php");
	if (file_exists($subpath)) {
		include $subpath;
	}
?>
<div id="page">
	<div id="breadcrumb">
		<ul>
			<li><a href="<?=ADMIN_ROOT?>">Developer</a></li> 
			<li>&raquo;</li>
			<? if ($bigtree["path"][2]) { ?>
			<li><a class="active" href="#"><?=ucwords(str_replace("-"," ",$bigtree["path"][2]))?></a></li>
			<? } else { ?>
			<li><a class="active" href="#">Landing</a></li>
			<? } ?>
		</ul>
		<br class="clear" />
	</div>
	<div>
		<h2>Developer</h2>
		<div class="add_level">
			<a href="<?=ADMIN_ROOT?>developer/" class="home">Home</a>
			<a href="<?=ADMIN_ROOT?>developer/templates/view/" class="templates">Templates</a>
			<a href="<?=ADMIN_ROOT?>developer/modules/view/" class="modules">Modules</a>
			<a href="<?=ADMIN_ROOT?>developer/modules/groups/view/" class="groups">Module Groups</a>
			<a href="<?=ADMIN_ROOT?>developer/field-types/view/" class="page">Field Types</a>
			<a href="<?=ADMIN_ROOT?>developer/callouts/view/" class="callouts">Callouts</a>
			<a href="<?=ADMIN_ROOT?>developer/settings/view/" class="settings">Settings</a>
			<a href="<?=ADMIN_ROOT?>developer/feeds/view/" class="rss">Feeds</a>
			<a href="<?=ADMIN_ROOT?>developer/foundry/view/" class="foundry">Foundry</a>
		</div>
		<?=$bigtree["content"]?>
	</div>
</div>
<? include BigTree::path("admin/layouts/_footer.php") ?>