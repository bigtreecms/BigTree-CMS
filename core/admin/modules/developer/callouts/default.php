<?
	$callouts = $admin->getCallouts();
	
	// Need to create a ridiculous hack because jQuery's sortable is retarded.
	$x = 0;
	$rel_table = array();
?>
<div class="table">
	<summary><h2>Callouts</h2></summary>
	<header>
		<span class="developer_templates_name">Name</span>
		<span class="view_action">Edit</span>
		<span class="view_action">Delete</span>
	</header>
	<ul id="callouts">
		<?
			foreach ($callouts as $callout) {
				$x++;
				$rel_table[$x] = $callout["id"];
		?>
		<li id="row_<?=$x?>">
			<section class="developer_templates_name">
				<span class="icon_sort"></span>
				<a href="<?=$section_root?>edit/<?=$callout["id"]?>/"><?=$callout["name"]?></a>
			</section>
			<section class="view_action">
				<a href="<?=$section_root?>edit/<?=$callout["id"]?>/" class="icon_edit"></a>
			</section>
			<section class="view_action">
				<a href="<?=$section_root?>delete/<?=$callout["id"]?>/" class="icon_delete"></a>
			</section>
		</li>
		<? } ?>
	</ul>
</div>

<script>
	$("#callouts").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/developer/order-callouts/", { type: "POST", data: { sort: $("#callouts").sortable("serialize"), rel: <?=json_encode($rel_table)?> } });
	}});
	
	$(".icon_delete").click(function() {
		new BigTreeDialog("Delete Callout",'<p class="confirm">Are you sure you want to delete this callout?',$.proxy(function() {
			document.location.href = $(this).attr("href");
		},this),"delete",false,"OK");
		
		return false;
	});
</script>