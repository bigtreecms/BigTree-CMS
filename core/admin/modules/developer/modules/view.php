<?
	$groups = $admin->getModuleGroups();
	foreach ($groups as &$group) {
		$group["modules"] = $admin->getModulesByGroup($group["id"]);
	}
	
	$ungrouped_modules = $admin->getModulesByGroup(0);
?>
<h1><span class="icon_developer_modules"></span>Modules</h1>
<?
	include BigTree::path("admin/modules/developer/modules/_nav.php");
	foreach ($groups as $g) {
?>
<div class="table">
	<summary>
		<a href="<?=$developer_root?>foundry/package/choose-files/group/<?=$g["id"]?>/" class="export"></a>
		<h2><?=$g["name"]?></h2>
	</summary>
	<header>
		<span class="developer_modules_name">Module Name</span>
		<span class="view_action">Export</span>
		<span class="view_action">Edit</span>
		<span class="view_action">Delete</span>
	</header>
	<ul id="group_<?=$g["id"]?>">
		<? foreach ($g["modules"] as $item) { ?>
		<li id="row_<?=$item["id"]?>">
			<section class="developer_modules_name">
				<span class="icon_sort"></span>
				<a href="<?=$section_root?>edit/<?=$item["id"]?>/"><?=$item["name"]?></a>
			</section>
			<section class="view_action">
				<a href="<?=$developer_root?>foundry/package/choose-files/module/<?=$item["id"]?>/" class="icon_export"></a>
			</section>
			<section class="view_action">
				<a href="<?=$section_root?>edit/<?=$item["id"]?>/" class="icon_edit"></a>
			</section>
			<section class="view_action">
				<a href="<?=$section_root?>delete/<?=$item["id"]?>/" class="icon_delete"></a>
			</section>
		</li>
		<? } ?>
	</ul>
</div>

<script type="text/javascript">
	$("#group_<?=$g["id"]?>").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: function() {
		$.ajax("<?=$admin_root?>ajax/developer/order-modules/?sort=" + escape($("#group_<?=$g["id"]?>").sortable("serialize")));
	}});
</script>
<?
	}
?>

<div class="table">
	<summary>
		<h2>Ungrouped Modules</h2>
	</summary>
	<header>
		<span class="developer_modules_name">Module Name</span>
		<span class="view_action">Export</span>
		<span class="view_action">Edit</span>
		<span class="view_action">Delete</span>
	</header>
	<ul id="group_0">
		<? foreach ($ungrouped_modules as $item) { ?>
		<li id="row_<?=$item["id"]?>">
			<section class="developer_modules_name">
				<span class="icon_sort"></span>
				<a href="<?=$section_root?>edit/<?=$item["id"]?>/"><?=$item["name"]?></a>
			</section>
			<section class="view_action">
				<a href="<?=$developer_root?>foundry/package/choose-files/module/<?=$item["id"]?>/" class="icon_export"></a>
			</section>
			<section class="view_action">
				<a href="<?=$section_root?>edit/<?=$item["id"]?>/" class="icon_edit"></a>
			</section>
			<section class="view_action">
				<a href="<?=$section_root?>delete/<?=$item["id"]?>/" class="icon_delete"></a>
			</section>
		</li>
		<? } ?>
	</ul>
</div>

<script type="text/javascript">
	$("#group_0").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: function() {
		$.ajax("<?=$admin_root?>ajax/developer/order-modules/?sort=" + escape($("#group_0").sortable("serialize")));
	}});

	$(".icon_delete").click(function() {
		new BigTreeDialog("Delete Module",'<p class="confirm">Are you sure you want to delete this module?',$.proxy(function() {
			document.location.href = $(this).attr("href");
		},this),"delete",false,"OK");
		
		return false;
	});
</script>