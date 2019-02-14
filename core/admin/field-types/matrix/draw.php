<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	if (!is_array($this->Value)) {
		$this->Value = [];
	}
	
	$max = !empty($this->Settings["max"]) ? $this->Settings["max"] : 0;

	// Callout style
	if ($this->Settings["style"] == "callout") {
		$this->Type = "callouts"; // Pretend to be callouts to work back-to-back
?>
<fieldset class="callouts<?php if ($bigtree["last_resource_type"] == "callouts") { ?> callouts_no_margin<?php } ?>" id="<?=$this->ID?>">
	<label<?php if ($this->LabelClass) { ?> class="<?=trim($this->LabelClass)?>"<?php } ?>>
		<?=$this->Title?>
		<?php if ($this->Subtitle) { ?> <small><?=$this->Subtitle?></small><?php } ?>
	</label>
	<div class="contain">
		<?php
			$x = 0;
			foreach ($this->Value as $item) {
		?>
		<article>
			<input type="hidden" class="bigtree_matrix_data" value="<?=base64_encode(json_encode($item))?>" />
			<?php $this->drawArrayLevel(array($x), $item) ?>
			<h4>
				<?=Text::htmlEncode($item["__internal-title"])?>
				<input type="hidden" name="<?=$this->Key?>[<?=$x?>][__internal-title]" value="<?=Text::htmlEncode($item["__internal-title"])?>" />
			</h4>
			<p>
				<?=Text::htmlEncode($item["__internal-subtitle"])?>
				<input type="hidden" name="<?=$this->Key?>[<?=$x?>][__internal-subtitle]" value="<?=Text::htmlEncode($item["__internal-subtitle"])?>" />
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
	<a href="#" class="add_item button"><span class="icon_small icon_small_add"></span><?=Text::translate("Add Item")?></a>
	<?php if ($max) { ?>
	<small class="max"><?=Text::translate("LIMIT :max:", false, [":max:" => $max])?></small>
	<?php } ?>
	<script>
		BigTreeMatrix({
			selector: "#<?=$this->ID?>",
			key: "<?=$this->Key?>",
			columns: <?=json_encode($this->Settings["columns"])?>,
			max: <?=$max?>,
			style: "callout"
		});
	</script>
</fieldset>
<?php
	} else {
?>
<fieldset>
	<label<?php if ($this->LabelClass) { ?> class="<?=trim($this->LabelClass)?>"<?php } ?>>
		<?php
			echo $this->Title;

			if ($this->Subtitle) {
				echo ' <small>'.$this->Subtitle.'</small>';
			}
		?>
	</label>
	<div class="multi_widget matrix_list" id="<?=$this->ID?>">
		<section<?php if (count($this->Value)) { ?> style="display: none;"<?php } ?>>
			<p><?=Text::translate('Click "Add Item" to add an item to this list.')?></p>
		</section>
		<ul>
			<?php
				$x = 0;
			
				foreach ($this->Value as $item) {
			?>
			<li>
				<input type="hidden" class="bigtree_matrix_data" value="<?=base64_encode(json_encode($item))?>" />
				<?php $this->drawArrayLevel(array($x), $item) ?>
				<input type="hidden" name="<?=$this->Key?>[<?=$x?>][__internal-title]" value="<?=Text::htmlEncode($item["__internal-title"])?>" />
				<input type="hidden" name="<?=$this->Key?>[<?=$x?>][__internal-subtitle]" value="<?=Text::htmlEncode($item["__internal-subtitle"])?>" />
				<span class="icon_sort"></span>
				<p>
					<?=Text::trimLength(Text::htmlEncode($item["__internal-title"]),100)?>
					<small><?=Text::trimLength(Text::htmlEncode($item["__internal-subtitle"]),100)?></small>
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
			<a href="#" class="add_item button"><span class="icon_small icon_small_add"></span><?=Text::translate("Add Item")?></a>
			<?php if ($max) { ?>
			<small class="max"><?=Text::translate("LIMIT :max:", false, [":max:" => $max])?></small>
			<?php } ?>
		</footer>
		<script>
			BigTreeMatrix({
				selector: "#<?=$this->ID?>",
				key: "<?=$this->Key?>",
				columns: <?=json_encode($this->Settings["columns"])?>,
				max: <?=$max?>,
				style: "list"
			});
		</script>
	</div>
</fieldset>
<?php
	}
