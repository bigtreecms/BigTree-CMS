<?php
	namespace BigTree;
		
	/**
	 * @global array $bigtree
	 */
	
	// Find out whether this is a draggable Many to Many.
	$table_description = SQL::describeTable($this->Settings["mtm-connecting-table"]);
	$cols = $table_description["columns"];
	$sortable = false;
	
	if (isset($cols["position"])) {
		$sortable = true;
	}
	
	$entries = array();
	// If we have existing data then this item is either pending or has pending changes so we use that data.
	if (is_array($this->Value)) {
		foreach ($this->Value as $other_id) {
			$other_descriptor = SQL::fetchSingle("SELECT `".$this->Settings["mtm-other-descriptor"]."` 
												  FROM `".$this->Settings["mtm-other-table"]."` WHERE id = ?", $other_id);
			if ($other_descriptor !== false) {
				$entries[$other_id] = $other_descriptor;
			}			
		}
	// No pending data, let's query the connecting table directly for the entries, but only if this isn't a new entry
	} elseif ($bigtree["edit_id"]) {
		$query_string = "SELECT * FROM `".$this->Settings["mtm-connecting-table"]."` WHERE `".$this->Settings["mtm-my-id"]."` = ?";
		if ($sortable) {
			$query_string .= "ORDER BY `position` DESC";
		}
		
		$query = SQL::query($query_string, $bigtree["edit_id"]);
		
		while ($entry = $query->fetch()) {
			$other_descriptor = SQL::fetchSingle("SELECT `".$this->Settings["mtm-other-descriptor"]."` 
												  FROM `".$this->Settings["mtm-other-table"]."`
												  WHERE id = ?", $entry[$this->Settings["mtm-other-id"]]);
			if ($other_descriptor !== false) {
				$entries[$entry["id"]] = $other_descriptor;
			}
		}
	}

	// Gather a list of the items that could possibly be tagged.
	$list = array();
	$query = SQL::query("SELECT `id`, `".$this->Settings["mtm-other-descriptor"]."` AS `title`
						 FROM `".$this->Settings["mtm-other-table"]."` ORDER BY ".$this->Settings["mtm-sort"]);
	while ($item = $query->fetch()) {
		$list[$item["id"]] = $item["title"];
	}
	
	// If we have a parser, send a list of the entries and available items through it.
	if (isset($this->Settings["mtm-list-parser"]) && $this->Settings["mtm-list-parser"]) {
		$list = call_user_func($this->Settings["mtm-list-parser"],$list,true);
		$entries = call_user_func($this->Settings["mtm-list-parser"],$entries,false);
	}

	// Remove items from the list that have already been tagged.
	foreach ($entries as $key => $value) {
		unset($list[$key]);
	}
	
	// A count of the number of entries
	$entry_counter = 0;
	
	// Only show the field if there are items that could be tagged.
	if (count($list) || count($entries)) {
?>
<div class="multi_widget many_to_many" id="<?=$this->ID?>">
	<section<?php if (count($entries)) { ?> style="display: none;"<?php } ?>>
		<p>No items have been tagged. Click "Add Item" to add an item to this list.</p>
	</section>
	<ul>
		<?php
			foreach ($entries as $id => $description) {
		?>
		<li>
			<input type="hidden" name="<?=$this->Key?>[<?=$entry_counter?>]" value="<?=Text::htmlEncode($id)?>" />
			<?php if ($sortable) { ?>
			<span class="icon_sort"></span>
			<?php } ?>
			<p><?=Text::htmlEncode(Text::trimLength(strip_tags($description),100))?></p>
			<a href="#" class="icon_delete"></a>
		</li>
		<?php
				$entry_counter++;
			}
		?>
	</ul>
	<footer>
		<select>
			<?php foreach ($list as $key => $value) { ?>
			<option value="<?=Text::htmlEncode($key)?>"><?=Text::htmlEncode(Text::trimLength(strip_tags($value),100))?></option>
			<?php } ?>
		</select>
		<a href="#" class="add button"><span class="icon_small icon_small_add"></span>Add Item</a>
		<?php
			if ($this->Settings["show_add_all"]) {
		?>
		<a href="#" class="add_all button">Add All</a>
		<?php
			}
			if ($this->Settings["show_reset"]) {
		?>
		<a href="#" class="reset button red">Reset</a>
		<?php
			}
		?>
	</footer>
</div>
<script>
	BigTreeManyToMany({
		id: "<?=$this->ID?>",
		count: <?=$entry_counter?>,
		key: "<?=$this->Key?>",
		sortable: <?=($sortable ? "true" : "false")?>
	});
</script>
<?php
	} else {
?>
<div class="multi_widget">
	<section>
		<p>There are no items available to tag.</p>
	</section>
</div>
<?php
	}
?>