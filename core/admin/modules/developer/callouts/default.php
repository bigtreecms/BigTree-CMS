<?
	$groups = $admin->getCalloutGroups();
	foreach ($groups as &$group) {
		$group["callouts"] = $admin->getCalloutsByGroup($group["id"]);
	}
	
	$ungrouped_callouts = $admin->getCalloutsByGroup(0);


	foreach ($groups as $g) {
		if (count($g["callouts"])) {
?>
<div class="table">
	<summary>
		<h2><?=$g["name"]?></h2>
	</summary>
	<header>
		<span class="developer_templates_name">Callout Name</span>
		<span class="view_action" style="width: 80px;">Actions</span>
	</header>
	<ul id="group_<?=$g["id"]?>">
		<? foreach ($g["callouts"] as $item) { ?>
		<li id="row_<?=$item["id"]?>">
			<section class="developer_templates_name">
				<span class="icon_sort"></span>
				<a href="<?=DEVELOPER_ROOT?>callouts/edit/<?=$item["id"]?>/"><?=$item["name"]?></a>
			</section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>callouts/edit/<?=$item["id"]?>/" class="icon_edit"></a>
			</section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>callouts/delete/<?=$item["id"]?>/" class="icon_delete"></a>
			</section>
		</li>
		<? } ?>
	</ul>
</div>

<script>
	$("#group_<?=$g["id"]?>").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/developer/order-callouts/", { type: "POST", data: { sort: $("#group_<?=$g["id"]?>").sortable("serialize") } });
	}});
</script>
<?
		}
	}
	
	if (count($ungrouped_callouts)) {
?>
<div class="table">
	<summary>
		<h2>Ungrouped Callouts</h2>
	</summary>
	<header>
		<span class="developer_templates_name">Callout Name</span>
		<span class="view_action" style="width: 80px;">Actions</span>
	</header>
	<ul id="group_0">
		<? foreach ($ungrouped_callouts as $item) { ?>
		<li id="row_<?=$item["id"]?>">
			<section class="developer_templates_name">
				<span class="icon_sort"></span>
				<a href="<?=DEVELOPER_ROOT?>callouts/edit/<?=$item["id"]?>/"><?=$item["name"]?></a>
			</section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>callouts/edit/<?=$item["id"]?>/" class="icon_edit"></a>
			</section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>callouts/delete/<?=$item["id"]?>/" class="icon_delete"></a>
			</section>
		</li>
		<? } ?>
	</ul>
</div>
<?
	}
?>

<script>
	$("#group_0").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/developer/order-callouts/", { type: "POST", data: { sort: $("#group_0").sortable("serialize") } });
	}});

	$(".icon_delete").click(function() {
		new BigTreeDialog({
			title: "Delete Callout",
			content: '<p class="confirm">Are you sure you want to delete this callout?',
			icon: "delete",
			alternateSaveText: "OK",
			callback: $.proxy(function() { document.location.href = $(this).attr("href"); },this)
		});

		return false;
	});
</script>