<?
	$count = $_POST["count"];
	$items = $admin->getCallouts();
	
	$type = $items[0]["id"];
?>
<div id="callout_type">
	<fieldset>
		<label>Callout Type</label>
		<? if (count($items) > 0) { ?>
		<select name="callouts[<?=$count?>][type]">
			<? foreach ($items as $item) { ?>
			<option value="<?=htmlspecialchars($item["id"])?>"><?=htmlspecialchars($item["name"])?></option>
			<? } ?>
		</select>
		<? } else { ?>
		<p>(No callouts available)</p>
		<? } ?>
	</fieldset>
</div>
<div id="callout_resources">
	<? include BigTree::path("admin/ajax/pages/callout-resources.php") ?>
</div>

<script>
	BigTreeCustomControls();
	
	$("#callout_type select").change(function(event,data) {
		$("#callout_resources").load("<?=ADMIN_ROOT?>ajax/pages/callout-resources/", { type: data.value, count: <?=$count?> });
	});
</script>