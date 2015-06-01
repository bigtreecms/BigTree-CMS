<?php
	if (!is_array($field["value"])) {
		$field["value"] = array();
	}

	$noun = $field["options"]["noun"] ? htmlspecialchars($field["options"]["noun"]) : "Callout";
	$max = !empty($field["options"]["max"]) ? $field["options"]["max"] : 0;
?>
<fieldset class="callouts<?php if ($bigtree["last_resource_type"] == "callouts") { ?> callouts_no_margin<?php } ?>" id="<?=$field["id"]?>">
	<label<?=$label_validation_class?>><?=$field["title"]?><?php if ($field["subtitle"]) { ?> <small><?=$field["subtitle"]?></small><?php } ?></label>
	<div class="contain">
		<?php
			$x = 0;
			foreach ($field["value"] as $callout) {
				$type = $admin->getCallout($callout["type"]);
		?>
		<article>
			<input type="hidden" class="callout_data" value="<?=base64_encode(json_encode($callout))?>" />
			<?php BigTreeAdmin::drawArrayLevel(array($x),$callout) ?>
			<h4>
				<?=BigTree::safeEncode($callout["display_title"])?>
				<input type="hidden" name="<?=$field["key"]?>[<?=$x?>][display_title]" value="<?=BigTree::safeEncode($callout["display_title"])?>" />
			</h4>
			<p><?=$type["name"]?></p>
			<div class="bottom">
				<span class="icon_drag"></span>
				<a href="#" class="icon_edit"></a>
				<a href="#" class="icon_delete"></a>
			</div>
		</article>
		<?php
				$x++;
			}
		?>
	</div>
	<a href="#" class="add_callout button"><span class="icon_small icon_small_add"></span>Add <?=$noun?></a>
	<?php if ($max) { ?>
	<small class="max">LIMIT <?=$max?></small>
	<?php } ?>
	<script>
		BigTreeCallouts({
			selector: "#<?=$field["id"]?>",
			key: "<?=$field["key"]?>",
			noun: "<?=$noun?>",
			groups: <?=json_encode($field["options"]["groups"])?>,
			max: <?=$max?>
		});
	</script>
</fieldset>