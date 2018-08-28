<?php
	if (!is_array($field["value"])) {
		$field["value"] = array();
	}
	
	$max = !empty($field["settings"]["max"]) ? $field["settings"]["max"] : 0;

	// Callout style
	if ($field["settings"]["style"] == "callout") {
		$field["type"] = "callouts"; // Pretend to be callouts to work back-to-back
?>
<fieldset class="callouts<?php if ($bigtree["last_resource_type"] == "callouts") { ?> callouts_no_margin<?php } ?>" id="<?=$field["id"]?>">
	<label<?=$label_validation_class?>><?=$field["title"]?><?php if ($field["subtitle"]) { ?> <small><?=$field["subtitle"]?></small><?php } ?></label>
	<div class="contain">
		<?php
			$x = 0;
			foreach ($field["value"] as $item) {
		?>
		<article>
			<input type="hidden" class="bigtree_matrix_data" value="<?=base64_encode(json_encode($item))?>" />
			<?php BigTreeAdmin::drawArrayLevel(array($x),$item,$field) ?>
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
		<?php
				$x++;
			}
		?>
	</div>
	<a href="#" class="add_item add_item_button button"><span class="icon_small icon_small_add"></span>Add Item</a>
	<?php if ($max) { ?>
	<small class="max">LIMIT <?=$max?></small>
	<?php } ?>
	<script>
		BigTreeMatrix({
			selector: "#<?=$field["id"]?>",
			key: "<?=$field["key"]?>",
			columns: <?=json_encode($field["settings"]["columns"])?>,
			max: <?=$max?>,
			style: "callout",
			front_end_editor: <?=(defined("BIGTREE_FRONT_END_EDITOR") ? "true" : "false")?>
		});
	</script>
</fieldset>
<?php
	} else {
?>
<fieldset>
	<label<?=$label_validation_class?>><?=$field["title"]?><?php if ($field["subtitle"]) { ?> <small><?=$field["subtitle"]?></small><?php } ?></label>
	<div class="multi_widget matrix_list" id="<?=$field["id"]?>">
		<section<?php if (count($field["value"])) { ?> style="display: none;"<?php } ?>>
			<p>Click "Add Item" to add an item to this list.</p>
		</section>
		<ul>
			<?php
				$x = 0;
				foreach ($field["value"] as $item) {
			?>
			<li>
				<input type="hidden" class="bigtree_matrix_data" value="<?=base64_encode(json_encode($item))?>" />
				<?php BigTreeAdmin::drawArrayLevel(array($x),$item,$field) ?>
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
			<?php
					$x++;
				}
			?>
		</ul>
		<footer>
			<a href="#" class="add_item add_item_button button"><span class="icon_small icon_small_add"></span>Add Item</a>
			<?php if ($max) { ?>
			<small class="max">LIMIT <?=$max?></small>
			<?php } ?>
		</footer>
		<script>
			BigTreeMatrix({
				selector: "#<?=$field["id"]?>",
				key: "<?=$field["key"]?>",
				columns: <?=json_encode($field["settings"]["columns"])?>,
				max: <?=$max?>,
				style: "list",
				front_end_editor: <?=(defined("BIGTREE_FRONT_END_EDITOR") ? "true" : "false")?>
			});
		</script>
	</div>
</fieldset>
<?php
	}
?>