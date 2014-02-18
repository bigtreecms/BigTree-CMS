<?
	$extensions = $admin->getExtensions();
?>
<div class="table">
	<summary><h2>Extensions</h2></summary>
	<header>
		<span class="developer_templates_name">Extension Name</span>
		<span style="width: 80px;">Actions</span>
	</header>
	<ul>
		<? foreach ($extensions as $extension) { ?>
		<li>
			<section class="developer_templates_name">
				<?=$extension["name"]?> v<?=$extension["version"]?>
			</section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>extensions/edit/<?=$extension["id"]?>/" class="icon_edit"></a>
			</section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>extensions/delete/<?=$extension["id"]?>/" class="icon_delete"></a>
			</section>
		</li>
		<? } ?>
	</ul>
</div>
<script>
	$(".icon_delete").click(function() {
		new BigTreeDialog("Delete Item",'<p class="confirm">Are you sure you want to uninstall this extension?</p>',$.proxy(function() {
			window.location.href = $(this).attr("href");
		},this),"delete",false,"OK");
		return false;
	});
</script>