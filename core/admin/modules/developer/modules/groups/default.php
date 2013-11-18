<?
	$groups = $admin->getModuleGroups();
?>
<div class="table">
	<summary>
		<h2>Module Groups</h2>
	</summary>
	<header>
		<span class="developer_templates_name">Group Name</span>
		<span class="view_action" style="width: 80px;">Actions</span>
	</header>
	<ul id="groups">
		<? foreach ($groups as $item) { ?>
		<li id="row_<?=$item["id"]?>">
			<section class="developer_templates_name">
				<span class="icon_sort"></span>
				<?=$item["name"]?>
			</section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>modules/groups/edit/<?=$item["id"]?>/" class="icon_edit"></a>
			</section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>modules/groups/delete/<?=$item["id"]?>/" class="icon_delete"></a>
			</section>
		</li>
		<? } ?>
	</ul>
</div>

<script>
	$("#groups").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/developer/order-module-groups/", { type: "POST", data: { sort: $("#groups").sortable("serialize") } }); 
	}});

	$(".icon_delete").click(function() {
		new BigTreeDialog("Delete Module Group",'<p class="confirm">Are you sure you want to delete this module group?<br /><br />Modules in this group will become uncategorized.</p>',$.proxy(function() {
			document.location.href = $(this).attr("href");
		},this),"delete",false,"OK");
		
		return false;
	});
</script>