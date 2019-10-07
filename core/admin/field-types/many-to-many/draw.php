<?php
	namespace BigTree;
	
	// Find out whether this is a draggable Many to Many.
	$table_description = SQL::describeTable($this->Settings["mtm-connecting-table"]);
	$cols = $table_description["columns"];
	$sortable = false;
	$max = !empty($this->Settings["max"]) ? $this->Settings["max"] : 0;

	if (isset($cols["position"])) {
		$sortable = true;
	}
	
	$entries = [];
	$list = [];
	
	// If we have existing data then this item is either pending or has pending changes so we use that data.
	if (is_array($this->Value)) {
		foreach ($this->Value as $oid) {
			$record = SQL::fetchSingle("SELECT `".$this->Settings["mtm-other-descriptor"]."`
										FROM `".$this->Settings["mtm-other-table"]."`
										WHERE id = ?", $oid);
			
			if ($record !== false) {
				$entries[$oid] = $record;
			}			
		}
	// No pending data, let's query the connecting table directly for the entries, but only if this isn't a new entry
	} elseif ($this->EntryID) {
		if ($sortable) {
			$query = SQL::query("SELECT * FROM `".$this->Settings["mtm-connecting-table"]."`
								 WHERE `".$this->Settings["mtm-my-id"]."` = ?
								 ORDER BY `position` DESC", $this->EntryID);
		} else {
			$query = SQL::query("SELECT * FROM `".$this->Settings["mtm-connecting-table"]."`
								 WHERE `".$this->Settings["mtm-my-id"]."` = ?", $this->EntryID);
		}
		
		while ($record = $query->fetch()) {
			// Get the title from the other table.
			$id = $record[$this->Settings["mtm-other-id"]];
			$title = SQL::fetchSingle("SELECT `".$this->Settings["mtm-other-descriptor"]."`
									   FROM `".$this->Settings["mtm-other-table"]."`
									   WHERE id = ?", $id);
			
			if ($title !== false) {
				$entries[$id] = $title;
			}
		}
	}

	// Gather a list of the items that could possibly be tagged.
	$query = SQL::query("SELECT id, `".$this->Settings["mtm-other-descriptor"]."`
						 FROM `".$this->Settings["mtm-other-table"]."`
						 ORDER BY ".$this->Settings["mtm-sort"]);
	
	while ($record = $query->fetch()) {
		$list[$record["id"]] = $record[$this->Settings["mtm-other-descriptor"]];
	}
	
	// If we have a parser, send a list of the entries and available items through it.
	if (isset($this->Settings["mtm-list-parser"]) && $this->Settings["mtm-list-parser"]) {
		$list = call_user_func($this->Settings["mtm-list-parser"], $list, true);
		$entries = call_user_func($this->Settings["mtm-list-parser"], $entries, false);
	}

	// Remove items from the list that have already been tagged.
	foreach ($entries as $k => $v) {
		unset($list[$k]);
	}
	
	// A count of the number of entries
	$x = 0;
	
	// Only show the field if there are items that could be tagged.
	if (count($list) || count($entries)) {
?>
<div class="multi_widget many_to_many" id="<?=$this->ID?>">
	<section<?php if (count($entries)) { ?> style="display: none;"<?php } ?>>
		<p><?=Text::translate('No items have been tagged. Click "Add Item" to add an item to this list.', true)?></p>
	</section>
	<ul>
		<?php
			foreach ($entries as $id => $description) {
		?>
		<li>
			<input type="hidden" name="<?=$this->Key?>[<?=$x?>]" value="<?=Text::htmlEncode($id)?>" />
			<?php if ($sortable) { ?>
			<span class="icon_sort"></span>
			<?php } ?>
			<p><?=Text::htmlEncode(Text::trimLength(strip_tags($description),100))?></p>
			<a href="#" class="icon_delete"></a>
		</li>
		<?php
				$x++;
			}
		?>
	</ul>
	<footer>
		<div class="many_to_many_add_container">
			<select>
				<?php foreach ($list as $k => $v) { ?>
				<option value="<?=Text::htmlEncode($k)?>"><?=Text::htmlEncode(Text::trimLength(strip_tags($v),100))?></option>
				<?php } ?>
			</select>
			<a href="#" class="add button"><span class="icon_small icon_small_add"></span><?=Text::translate("Add Item")?></a>
		</div>
		<?php
			if ($max) {
		?>
		<small class="max"><?=Text::translate("LIMIT :max:", false, [":max:" => $max])?></small>
		<?php
			} elseif ($this->Settings["show_add_all"]) {
		?>
		<a href="#" class="add_all button"><?=Text::translate("Add All")?></a>
		<?php
			}
			if ($this->Settings["show_reset"]) {
		?>
		<a href="#" class="reset button red"><?=Text::translate("Reset")?></a>
		<?php
			}
		?>
	</footer>
</div>
<script>
	BigTreeManyToMany({
		id: "<?=$this->ID?>",
		count: <?=$x?>,
		key: "<?=$this->Key?>",
		sortable: <?=($sortable ? "true" : "false")?>,
		max: <?=$max?>
	});
</script>
<?php
	} else {
?>
<div class="multi_widget">
	<section>
		<p><?=Text::translate("There are no items available to tag.")?></p>
	</section>
</div>
<?php
	}
?>