<?
	if ($_POST["groups"]) {
		$items = $admin->getCalloutsInGroups($_POST["groups"]);
	} else {
		$items = $admin->getCalloutsAllowed("name ASC");
	}

	$bigtree["callout_count"] = intval($_POST["count"]);
	$bigtree["callout_key"] = htmlspecialchars($_POST["key"]);
	$bigtree["resources"] = json_decode(base64_decode($_POST["data"]),true);
?>
<div id="callout_type">
	<fieldset>
		<label>Callout Type</label>
		<select name="<?=$bigtree["callout_key"]?>[<?=$bigtree["callout_count"]?>][type]">
			<? foreach ($items as $item) { ?>
			<option value="<?=$item["id"]?>"<? if ($item["id"] == $bigtree["resources"]["type"]) { ?> selected="selected"<? } ?>><?=$item["name"]?></option>
			<? } ?>
		</select>
	</fieldset>
</div>
<div id="callout_resources" class="callout_fields">
	<? include BigTree::path("admin/ajax/callouts/resources.php") ?>
</div>

<script>
	$("#callout_type select").change(function(event,data) {
		// TinyMCE tooltips and menus sometimes get stuck
		$(".mce-tooltip, .mce-menu").remove();

		$("#callout_resources").load("<?=ADMIN_ROOT?>ajax/callouts/resources/", {
			count: <?=$bigtree["callout_count"]?>,
			key: "<?=$bigtree["callout_key"]?>",
			resources: "<?=htmlspecialchars($_POST["data"])?>",
			type: data.value,
			tab_depth: <?=intval($_POST["tab_depth"])?>
		}, BigTreeCustomControls).scrollTop(0);
	});
</script>