<?php
	// Find out whether this is a draggable Many to Many.
	$table_description = BigTree::describeTable($field["settings"]["mtm-connecting-table"]);
	$cols = $table_description["columns"];
	$sortable = false;
	$max = !empty($field["settings"]["max"]) ? $field["settings"]["max"] : 0;

	if (isset($cols["position"])) {
		$sortable = true;
	}
	
	$entries = array();
	// If we have existing data then this item is either pending or has pending changes so we use that data.
	if (is_array($field["value"])) {
		foreach ($field["value"] as $oid) {
			$g = sqlfetch(sqlquery("SELECT * FROM `".$field["settings"]["mtm-other-table"]."` WHERE id = '$oid'"));
			if ($g) {
				$entries[$g["id"]] = $g[$field["settings"]["mtm-other-descriptor"]];
			}			
		}
	// No pending data, let's query the connecting table directly for the entries, but only if this isn't a new entry
	} elseif ($bigtree["edit_id"]) {
		if ($sortable) {
			$q = sqlquery("SELECT * FROM `".$field["settings"]["mtm-connecting-table"]."` WHERE `".$field["settings"]["mtm-my-id"]."` = '".$bigtree["edit_id"]."' ORDER BY `position` DESC");
		} else {
			$q = sqlquery("SELECT * FROM `".$field["settings"]["mtm-connecting-table"]."` WHERE `".$field["settings"]["mtm-my-id"]."` = '".$bigtree["edit_id"]."'");
		}
		
		while ($f = sqlfetch($q)) {
			// Get the title from the other table.
			$g = sqlfetch(sqlquery("SELECT * FROM `".$field["settings"]["mtm-other-table"]."` WHERE id = '".$f[$field["settings"]["mtm-other-id"]]."'"));
			if ($g) {
				$entries[$g["id"]] = $g[$field["settings"]["mtm-other-descriptor"]];
			}
		}
	}

	// Gather a list of the items that could possibly be tagged.
	$list = array();
	$q = sqlquery("SELECT * FROM `".$field["settings"]["mtm-other-table"]."` ORDER BY ".$field["settings"]["mtm-sort"]);
	while ($f = sqlfetch($q)) {
		$list[$f["id"]] = $f[$field["settings"]["mtm-other-descriptor"]];
	}
	
	// If we have a parser, send a list of the entries and available items through it.
	if (isset($field["settings"]["mtm-list-parser"]) && $field["settings"]["mtm-list-parser"]) {
		$list = call_user_func($field["settings"]["mtm-list-parser"],$list,true);
		$entries = call_user_func($field["settings"]["mtm-list-parser"],$entries,false);
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
<div class="multi_widget many_to_many" id="<?=$field["id"]?>">
	<section<?php if (count($entries)) { ?> style="display: none;"<?php } ?>>
		<p>No items have been tagged. Click "Add Item" to add an item to this list.</p>
	</section>
	<ul>
		<?php
			foreach ($entries as $id => $description) {
		?>
		<li>
			<input type="hidden" name="<?=$field["key"]?>[<?=$x?>]" value="<?=BigTree::safeEncode($id)?>" />
			<?php if ($sortable) { ?>
			<span class="icon_sort"></span>
			<?php } ?>
			<p><?=BigTree::safeEncode(BigTree::trimLength(strip_tags($description),100))?></p>
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
				<option value="<?=BigTree::safeEncode($k)?>"><?=BigTree::safeEncode(BigTree::trimLength(strip_tags($v),100))?></option>
				<?php } ?>
			</select>
			<a href="#" class="add button"><span class="icon_small icon_small_add"></span>Add Item</a>
		</div>
		<?php
			if ($max) {
		?>
		<small class="max">LIMIT <?=$max?></small>
		<?php
			} elseif ($field["settings"]["show_add_all"]) {
		?>
		<a href="#" class="add_all button">Add All</a>
		<?php
			}
			if ($field["settings"]["show_reset"]) {
		?>
		<a href="#" class="reset button red">Reset</a>
		<?php
			}
		?>
	</footer>
</div>
<script>
	BigTreeManyToMany({
		id: "<?=$field["id"]?>",
		count: <?=$x?>,
		key: "<?=$field["key"]?>",
		sortable: <?=($sortable ? "true" : "false")?>,
		max: <?=$max?>
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