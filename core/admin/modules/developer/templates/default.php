<?
	$templates = $admin->getTemplates();
	
	// Need to create a ridiculous hack because jQuery's sortable is stupid.
	$x = 0;
	$rel_table = array();
?>
<div class="table">
	<summary><h2>Basic Templates</h2></summary>
	<header>
		<span class="developer_templates_name">Template Name</span>
		<span class="view_action" style="width: 80px;">Actions</span>
	</header>
	<ul id="basic_templates">
		<?
			foreach ($templates as $template) {
				if (!$template["routed"]) {
					$x++;
					$rel_table[$x] = $template["id"];
		?>
		<li id="row_<?=$x?>">
			<section class="developer_templates_name">
				<span class="icon_sort"></span>
				<a href="<?=DEVELOPER_ROOT?>templates/edit/<?=$template["id"]?>/"><?=$template["name"]?></a>
			</section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>templates/edit/<?=$template["id"]?>/" class="icon_edit"></a>
			</section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>templates/delete/<?=$template["id"]?>/" class="icon_delete"></a>
			</section>
		</li>
		<?
				}
			}
		?>
	</ul>
</div>

<div class="table">
	<summary><h2>Routed Templates</h2></summary>
	<header>
		<span class="developer_templates_name">Template Name</span>
		<span class="view_action" style="width: 80px;">Actions</span>
	</header>
	<ul id="routed_templates">
		<?
			foreach ($templates as $template) {
				if ($template["routed"]) {
					$x++;
					$rel_table[$x] = $template["id"];
		?>
		<li id="row_<?=$x?>">
			<section class="developer_templates_name">
				<span class="icon_sort"></span>
				<a href="<?=DEVELOPER_ROOT?>templates/edit/<?=$template["id"]?>/"><?=$template["name"]?></a>
			</section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>templates/edit/<?=$template["id"]?>/" class="icon_edit"></a>
			</section>
			<section class="view_action">
				<a href="<?=DEVELOPER_ROOT?>templates/delete/<?=$template["id"]?>/" class="icon_delete"></a>
			</section>
		</li>
		<?
				}
			}
		?>
	</ul>
</div>

<script>
	$("#basic_templates").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/developer/order-templates/", { type: "POST", data: { sort: $("#basic_templates").sortable("serialize"), rel: <?=json_encode($rel_table)?> } });
	}});
	
	$("#routed_templates").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/developer/order-templates/", { type: "POST", data: { sort: $("#routed_templates").sortable("serialize"), rel: <?=json_encode($rel_table)?> } });
	}});
	
	$(".icon_delete").click(function() {
		new BigTreeDialog("Delete Template",'<p class="confirm">Are you sure you want to delete this template?',$.proxy(function() {
			document.location.href = $(this).attr("href");
		},this),"delete",false,"OK");
		
		return false;
	});
</script>