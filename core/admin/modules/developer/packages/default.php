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
				<?=$package["name"]?> <?=$package["version"]?>
			</section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>packages/edit/<?=$package["id"]?>/" class="icon_edit"></a>
			</section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>packages/delete/<?=$package["id"]?>/" class="icon_delete"></a>
			</section>
		</li>
		<? } ?>
	</ul>
</div>
<script>
	$(".icon_delete").click(function() {
		new BigTreeDialog("Delete Item",'<p class="confirm">Are you sure you want to uninstall this package?<br />Related components will also be removed.</p>',$.proxy(function() {
			window.location.href = $(this).attr("href");
		},this),"delete",false,"OK");
		return false;
	});
</script>