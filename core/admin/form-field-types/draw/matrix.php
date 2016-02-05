<?
	if (!is_array($field["value"])) {
		$field["value"] = array();
	}
	$max = !empty($field["options"]["max"]) ? $field["options"]["max"] : 0;

	// Callout style
	if ($field["options"]["style"] == "callout") {
		$field["type"] = "callouts"; // Pretend to be callouts to work back-to-back
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
			<? BigTreeAdmin::drawArrayLevel(array($x),$item,$field) ?>
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
			max: <?=$max?>,
			style: "callout"
		});
	</script>
</fieldset>
<?
	} else {
?>
<fieldset>
	<label<?=$label_validation_class?>><?=$field["title"]?><? if ($field["subtitle"]) { ?> <small><?=$field["subtitle"]?></small><? } ?></label>
	<div class="multi_widget matrix_list" id="<?=$field["id"]?>">
		<section<? if (count($field["value"])) { ?> style="display: none;"<? } ?>>
			<p>Click "Add Item" to add an item to this list.</p>
		</section>
		<ul>
			<?
				$x = 0;
				foreach ($field["value"] as $item) {
			?>
			<li>
				<input type="hidden" class="bigtree_matrix_data" value="<?=base64_encode(json_encode($item))?>" />
				<? BigTreeAdmin::drawArrayLevel(array($x),$item,$field) ?>
				<input type="hidden" name="<?=$field["key"]?>[<?=$x?>][__internal-title]" value="<?=BigTree::safeEncode($item["__internal-title"])?>" />
				<input type="hidden" name="<?=$field["key"]?>[<?=$x?>][__internal-subtitle]" value="<?=BigTree::safeEncode($item["__internal-subtitle"])?>" />
				<span class="icon_sort"></span>
				<p>
					<?=BigTree::trimLength(BigTree::safeEncode($item["__internal-title"]),100)?>
					<small><?=BigTree::trimLength(BigTree::safeEncode($item["__internal-subtitle"]),100)?></small>
				</p>
				<a href="#" class="icon_delete"></a>
				<a href="#" class="icon_edit"></a>
			</li>
			<?
					$x++;
				}
			?>
		</ul>
		<footer>
			<a href="#" class="add_item button"><span class="icon_small icon_small_add"></span>Add Item</a>
			<? if ($max) { ?>
			<small class="max">LIMIT <?=$max?></small>
			<? } ?>
		</footer>
		<script>
			BigTreeMatrix({
				selector: "#<?=$field["id"]?>",
				key: "<?=$field["key"]?>",
				columns: <?=json_encode($field["options"]["columns"])?>,
				max: <?=$max?>,
				style: "list"
			});
		</script>
	</div>
</fieldset>
<?
	}
?>