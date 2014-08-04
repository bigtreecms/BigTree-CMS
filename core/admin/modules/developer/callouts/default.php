<?
	$groups = $admin->getCalloutGroups();	
	$ungrouped_callouts = $admin->getCalloutsByGroup(0);

	// Need to create a ridiculous hack because jQuery's sortable is stupid.
	$x = 0;
	$rel_table = array();

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
		<?
			foreach ($g["callouts"] as $id) {
				$item = $admin->getCallout($id);
				$x++;
				$rel_table[$x] = $item["id"];
		?>
		<li id="row_<?=$x?>">
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
		<?
			}
		?>
	</ul>
</div>

<script>
	$("#group_<?=$g["id"]?>").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/developer/order-callouts/", { type: "POST", data: { group: <?=$g["id"]?>, sort: $("#group_<?=$g["id"]?>").sortable("serialize"), rel: <?=json_encode($rel_table)?> } });
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
	<ul>
		<? foreach ($ungrouped_callouts as $item) { ?>
		<li>
			<section class="developer_templates_name">
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
	$(".icon_delete").click(function() {
		new BigTreeDialog({
			title: "Delete Callout",
			content: '<p class="confirm">Are you sure you want to delete this callout?</p>',
			icon: "delete",
			alternateSaveText: "OK",
			callback: $.proxy(function() {
				document.location.href = $(this).attr("href");
			},this)
		});

		return false;
	});
</script>