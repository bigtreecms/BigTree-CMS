<?
	$types = $admin->getFieldTypes();
?>
<div class="table">
	<summary><h2>Field Types</h2></summary>
	<header>
		<span class="developer_modules_name">Name</span>
		<span class="view_action">Export</span>
		<span class="view_action">Edit</span>
		<span class="view_action">Delete</span>
	</header>
	<ul>
		<? foreach ($types as $type) { ?>
		<li>
			<section class="developer_modules_name">
				<a href="<?=$section_root?>edit/<?=$type["id"]?>/"><?=$type["name"]?></a>
			</section>
			<section class="view_action">
				<a href="<?=$developer_root?>foundry/package/choose-files/field-type/<?=$type["id"]?>/" class="icon_export"></a>
			</section>
			<section class="view_action">
				<a href="<?=$section_root?>edit/<?=$type["id"]?>/" class="icon_edit"></a>
			</section>
			<section class="view_action">
				<a href="<?=$section_root?>delete/<?=$type["id"]?>/" class="icon_delete"></a>
			</section>
		</li>
		<? } ?>
	</ul>
</div>

<script>
	$(".icon_delete").click(function() {
		new BigTreeDialog("Delete Field Type",'<p class="confirm">Are you sure you want to delete this field type?<br /><br />Fields using this type will revert to text fields and your source files will be deleted.</p>',$.proxy(function() {
			document.location.href = $(this).attr("href");
		},this),"delete",false,"OK");
		
		return false;
	});
</script>