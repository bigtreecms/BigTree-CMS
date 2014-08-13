<?
	if (!is_array($field["value"])) {
		$field["value"] = array();
	}
	$field["type"] = "callouts"; // Pretend to be callouts to work back-to-back
	$max = !empty($field["options"]["max"]) ? $field["options"]["max"] : 0;
?>
<fieldset class="callouts<? if ($bigtree["last_resource_type"] == "callouts") { ?> callouts_no_margin<? } ?>" id="<?=$field["id"]?>">
	<label<?=$label_validation_class?>><?=$field["title"]?><? if ($field["subtitle"]) { ?> <small><?=$field["subtitle"]?></small><? } ?></label>
	<div class="contain">
		<?
			$x = 0;
			foreach ($field["value"] as $item) {
		?>
		<article>
			<input type="hidden" class="bigtree_matrix_data" value="<?=base64_encode(json_encode($item))?>" />
			<? BigTreeAdmin::drawArrayLevel(array($x),$item) ?>
			<h4>
				<?=BigTree::safeEncode($item["__internal-title"])?>
				<input type="hidden" name="<?=$field["key"]?>[<?=$x?>][__internal-title]" value="<?=BigTree::safeEncode($item["__internal-title"])?>" />
			</h4>
			<p>
				<?=BigTree::safeEncode($item["__internal-subtitle"])?>
				<input type="hidden" name="<?=$field["key"]?>[<?=$x?>][__internal-subtitle]" value="<?=BigTree::safeEncode($item["__internal-subtitle"])?>" />
			</p>
			<div class="bottom">
				<span class="icon_drag"></span>
				<a href="#" class="icon_edit"></a>
				<a href="#" class="icon_delete"></a>
			</div>
		</article>
		<?
				$x++;
			}
		?>
	</div>
	<a href="#" class="add_item button"><span class="icon_small icon_small_add"></span>Add Item</a>
	<? if ($max) { ?>
	<small class="max">LIMIT <?=$max?></small>
	<? } ?>
	<script>
		BigTreeMatrix({
			selector: "#<?=$field["id"]?>",
			key: "<?=$field["key"]?>",
			columns: <?=json_encode($field["options"]["columns"])?>,
			max: <?=$max?>
		});
	</script>
</fieldset>