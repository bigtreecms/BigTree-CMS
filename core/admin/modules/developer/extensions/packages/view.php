<?
	$packages = $admin->getPackages();
?>
<div class="table">
	<summary><h2>Packages</h2></summary>
	<header>
		<span class="developer_templates_name">Package Name</span>
		<span style="width: 80px;">Actions</span>
	</header>
	<ul>
		<? foreach ($packages as $package) { ?>
		<li>
			<section class="developer_templates_name">
				<?=$package["name"]?> v<?=$package["version"]?>
			</section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>extensions/packages/edit/<?=$package["id"]?>/" class="icon_edit"></a>
			</section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>extensions/packages/delete/<?=$package["id"]?>/" class="icon_delete"></a>
			</section>
		</li>
		<? } ?>
	</ul>
</div>