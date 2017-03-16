<?
	$groups = $admin->getCalloutGroups();
?>
<div class="table">
	<summary>
		<h2>Callout Groups</h2>
	</summary>
	<header>
		<span class="developer_templates_name">Group Name</span>
		<span class="view_action" style="width: 80px;">Actions</span>
	</header>
	<ul>
		<? foreach ($groups as $item) { ?>
		<li id="row_<?=$item["id"]?>">
			<section class="developer_templates_name">
				<?=$item["name"]?>
			</section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>callouts/groups/edit/<?=$item["id"]?>/" class="icon_edit"></a>
			</section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>callouts/groups/delete/?id=<?=$item["id"]?><? $admin->drawCSRFTokenGET() ?>" class="icon_delete"></a>
			</section>
		</li>
		<? } ?>
	</ul>
</div>

<script>
	$(".icon_delete").click(function() {
		BigTreeDialog({
			title: "Delete Callout Group",
			content: '<p class="confirm">Are you sure you want to delete this callout group?</p>',
			icon: "delete",
			alternateSaveText: "OK",
			callback: $.proxy(function() {
				document.location.href = $(this).attr("href");
			},this)
		});
		
		return false;
	});
</script>