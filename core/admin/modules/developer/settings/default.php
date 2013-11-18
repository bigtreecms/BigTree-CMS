<?
	$settings = $admin->getSettings();
?>
<div class="table">
	<summary><h2>Settings</h2></summary>
	<header>
		<span class="developer_settings_name">Name</span>
		<span class="developer_settings_id">ID</span>
		<span class="developer_settings_type">Type</span>
		<span class="view_action" style="width: 80px;">Actions</span>
	</header>
	<ul>
		<? foreach ($settings as $setting) { ?>
		<li>
			<section class="developer_settings_name">
				<a href="<?=DEVELOPER_ROOT?>settings/edit/<?=$setting["id"]?>/"><?=$setting["name"]?></a>
			</section>
			<section class="developer_settings_id"><?=$setting["id"]?></section>
			<section class="developer_settings_type"><?=$setting["type"]?></section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>settings/edit/<?=$setting["id"]?>/" class="icon_edit"></a>
			</section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>settings/delete/<?=$setting["id"]?>/" class="icon_delete"></a>
			</section>
		</li>
		<? } ?>
	</ul>
</div>

<script>	
	$(".icon_delete").click(function() {
		new BigTreeDialog("Delete Setting",'<p class="confirm">Are you sure you want to delete this setting?',$.proxy(function() {
			document.location.href = $(this).attr("href");
		},this),"delete",false,"OK");
		
		return false;
	});
</script>