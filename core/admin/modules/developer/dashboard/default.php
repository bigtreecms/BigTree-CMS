<?php
	$settings = $cms->getSetting("bigtree-internal-dashboard-settings");
	$panes = array();
	$positions = array();

	// We're going to get the position setups and the multi-sort the whole shebang
	foreach (BigTreeAdmin::$DashboardPlugins["core"] as $id => $name) {
		$panes[] = array(
			"id" => $id,
			"name" => $name,
			"disabled" => isset($settings[$id]["disabled"]) ? $settings[$id]["disabled"] : ""
		);
		$positions[] = isset($settings[$id]["position"]) ? $settings[$id]["position"] : 0;
	}
	foreach (BigTreeAdmin::$DashboardPlugins["extension"] as $extension => $set) {
		foreach ($set as $id => $name) {
			$id = $extension."*".$id;
			$panes[] = array(
				"id" => $id,
				"name" => $name,
				"disabled" => isset($settings[$id]["disabled"]) ? $settings[$id]["disabled"] : ""
			);
			$positions[] = isset($settings[$id]["position"]) ? $settings[$id]["position"] : 0;
		}
	}
	array_multisort($positions,SORT_DESC,$panes);

	// Need to create a ridiculous hack because jQuery's sortable won't read complicated ids
	$x = 0;
	$rel_table = array();
?>
<div class="table">
	<summary>
		<h2>Dashboard Panes</h2>
	</summary>
	<header>
		<span class="developer_templates_name">Pane</span>
		<span class="view_action" style="width: 80px;">Enabled</span>
	</header>
	<ul id="pane_list">
		<?php
			foreach ($panes as $pane) {
				$x++;
				$rel_table[$x] = $pane["id"];
		?>
		<li id="row_<?=$x?>">
			<section class="developer_pane_name">
				<span class="icon_sort"></span>
				<?=$pane["name"]?>
			</section>
			<section class="view_action">
				<a class="icon_approve<?php if (!$pane["disabled"]) { ?> icon_approve_on<?php } ?>" data-id="<?=BigTree::safeEncode($pane["id"])?>" href="#"></a>
			</section>
		</li>
		<?php
			}
		?>
	</ul>
</div>
<script>
	$(".table").on("click",".icon_approve",function(ev) {
		ev.preventDefault();
		$.ajax("<?=ADMIN_ROOT?>ajax/developer/dashboard/toggle-pane/", { data: { id: $(this).attr("data-id") } });
		$(this).toggleClass("icon_approve_on");
	});

	$("#pane_list").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/developer/dashboard/order-panes/", { type: "POST", data: { sort: $("#pane_list").sortable("serialize"), rel: <?=json_encode($rel_table)?> } });
	}});
</script>