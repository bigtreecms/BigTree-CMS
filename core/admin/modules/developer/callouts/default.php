<?
	$callouts = $admin->getCallouts("name ASC");	
?>
<div class="table">
	<summary>
		<h2>Callouts</h2>
	</summary>
	<header>
		<span class="developer_templates_name">Name</span>
		<span class="view_action" style="width: 80px;">Actions</span>
	</header>
	<ul>
		<? foreach ($callouts as $item) { ?>
		<li>
			<section class="developer_templates_name">
				<a href="<?=DEVELOPER_ROOT?>callouts/edit/<?=$item["id"]?>/"><?=$item["name"]?></a>
			</section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>callouts/edit/<?=$item["id"]?>/" class="icon_edit"></a>
			</section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>callouts/delete/?id=<?=$item["id"]?><? $admin->drawCSRFTokenGET() ?>" class="icon_delete"></a>
			</section>
		</li>
		<? } ?>
	</ul>
</div>
<script>
	$(".icon_delete").click(function() {
		BigTreeDialog({
			title: "Delete Callout",
			content: '<p class="confirm">Are you sure you want to delete this callout?<br /><br />Deleting a callout also deletes its file in /templates/callouts/.</p>',
			icon: "delete",
			alternateSaveText: "OK",
			callback: $.proxy(function() {
				document.location.href = $(this).attr("href");
			},this)
		});

		return false;
	});
</script>