<?
	$bigtree["callout_count"] = $_POST["count"];
	$bigtree["resources"] = json_decode(base64_decode($_POST["data"]),true);
	$callouts = $admin->getCallouts();
?>
<div id="callout_type">
	<fieldset>
		<label>Callout Type</label>
		<select name="callouts[<?=$bigtree["callout_count"]?>][type]">
			<? foreach ($callouts as $item) { ?>
			<option value="<?=htmlspecialchars($item["id"])?>"<? if ($item["id"] == $bigtree["resources"]["type"]) { ?> selected="selected"<? } ?>><?=htmlspecialchars($item["name"])?></option>
			<? } ?>
		</select>
	</fieldset>
</div>
<div id="callout_resources">
	<? include BigTree::path("admin/ajax/pages/callout-resources.php") ?>
</div>

<script>
	BigTreeCustomControls();
	
	$("#callout_type select").change(function(event,data) {
		$("#callout_resources").load("<?=ADMIN_ROOT?>ajax/pages/callout-resources/", { type: data.value, count: <?=$bigtree["callout_count"]?>, resources: "<?=$_POST["data"]?>" });
	});
</script>