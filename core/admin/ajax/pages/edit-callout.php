<?
	$count = $_POST["count"];
	$items = $admin->getCallouts();
	
	$type = $items[0]["id"];
	
	$callout = json_decode(base64_decode($_POST["data"]),true);
?>
<div id="callout_type">
	<fieldset>
		<label>Callout Type</label>
		<select name="callouts[<?=$count?>][type]">
			<? foreach ($items as $item) { ?>
			<option value="<?=htmlspecialchars($item["id"])?>"<? if ($item["id"] == $callout["type"]) { ?> selected="selected"<? } ?>><?=htmlspecialchars($item["name"])?></option>
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
		$("#callout_resources").load("<?=ADMIN_ROOT?>ajax/pages/callout-resources/", { type: data.value, count: <?=$count?>, resources: "<?=$_POST["data"]?>" });
	});
</script>