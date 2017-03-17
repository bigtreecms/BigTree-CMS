<?
	$types = $admin->getFieldTypes();
?>
<div class="table">
	<summary><h2>Field Types</h2></summary>
	<header>
		<span class="developer_templates_name">Name</span>
		<span class="view_action" style="width: 80px;">Actions</span>
	</header>
	<ul>
		<? foreach ($types as $type) { ?>
		<li>
			<section class="developer_templates_name">
				<a href="<?=DEVELOPER_ROOT?>field-types/edit/<?=$type["id"]?>/"><?=$type["name"]?></a>
			</section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>field-types/edit/<?=$type["id"]?>/" class="icon_edit"></a>
			</section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>field-types/delete/?id=<?=$type["id"]?><? $admin->drawCSRFTokenGET() ?>" class="icon_delete"></a>
			</section>
		</li>
		<? } ?>
	</ul>
</div>

<script>
	$(".icon_delete").click(function() {
		BigTreeDialog({
			title: "Delete Field Type",
			content: '<p class="confirm">Are you sure you want to delete this field type?<br /><br />Deleting a field type also deletes its draw, process, and options files.<br /><br />Fields using this type will revert to text fields.</p>',
			icon: "delete",
			alternateSaveText: "OK",
			callback: $.proxy(function() {
				document.location.href = $(this).attr("href");
			},this)
		});
		
		return false;
	});
</script>