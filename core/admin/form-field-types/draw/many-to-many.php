<?
	// Find out whether this is a draggable Many to Many.
	$table_description = BigTree::describeTable($field["options"]["mtm-connecting-table"]);
	$cols = $table_description["columns"];
	$sortable = false;
	if (isset($cols["position"])) {
		$sortable = true;
	}
	
	$entries = array();
	// If we have existing data then this item is either pending or has pending changes so we use that data.
	if (is_array($field["value"])) {
		foreach ($field["value"] as $oid) {
			$g = sqlfetch(sqlquery("SELECT * FROM `".$field["options"]["mtm-other-table"]."` WHERE id = '$oid'"));
			if ($g) {
				$entries[$g["id"]] = $g[$field["options"]["mtm-other-descriptor"]];
			}			
		}
	// No pending data, let's query the connecting table directly for the entries.
	} else {
		if ($sortable) {
			$q = sqlquery("SELECT * FROM `".$field["options"]["mtm-connecting-table"]."` WHERE `".$field["options"]["mtm-my-id"]."` = '".$bigtree["edit_id"]."' ORDER BY `position` DESC");
		} else {
			$q = sqlquery("SELECT * FROM `".$field["options"]["mtm-connecting-table"]."` WHERE `".$field["options"]["mtm-my-id"]."` = '".$bigtree["edit_id"]."'");
		}
		
		while ($f = sqlfetch($q)) {
			// Get the title from the other table.
			$g = sqlfetch(sqlquery("SELECT * FROM `".$field["options"]["mtm-other-table"]."` WHERE id = '".$f[$field["options"]["mtm-other-id"]]."'"));
			if ($g) {
				$entries[$g["id"]] = $g[$field["options"]["mtm-other-descriptor"]];
			}
		}
	}

	// Gather a list of the items that could possibly be tagged.
	$list = array();
	$q = sqlquery("SELECT * FROM `".$field["options"]["mtm-other-table"]."` ORDER BY ".$field["options"]["mtm-sort"]);
	while ($f = sqlfetch($q)) {
		$list[$f["id"]] = $f[$field["options"]["mtm-other-descriptor"]];
	}
	
	// If we have a parser, send a list of the entries and available items through it.
	if (isset($field["options"]["mtm-list-parser"]) && $field["options"]["mtm-list-parser"]) {
		$list = call_user_func($field["options"]["mtm-list-parser"],$list,true);
		$entries = call_user_func($field["options"]["mtm-list-parser"],$entries,false);
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
	<section<? if (count($entries)) { ?> style="display: none;"<? } ?>>
		<p>No items have been tagged. Click "Add Item" to add an item to this list.</p>
	</section>
	<ul>
		<?
			foreach ($entries as $id => $description) {
		?>
		<li>
			<input type="hidden" name="<?=$field["key"]?>[<?=$x?>]" value="<?=BigTree::safeEncode($id)?>" />
			<? if ($sortable) { ?>
			<span class="icon_sort"></span>
			<? } ?>
			<p><?=BigTree::safeEncode(BigTree::trimLength(strip_tags($description),100))?></p>
			<a href="#" class="icon_delete"></a>
		</li>
		<?
				$x++;
			}
		?>
	</ul>
	<footer>
		<select>
			<? foreach ($list as $k => $v) { ?>
			<option value="<?=BigTree::safeEncode($k)?>"><?=BigTree::safeEncode(BigTree::trimLength(strip_tags($v),100))?></option>
			<? } ?>
		</select>
		<a href="#" class="add button"><span class="icon_small icon_small_add"></span>Add Item</a>
	</footer>
</div>
<script>
	new BigTreeManyToMany("<?=$field["id"]?>",<?=$x?>,"<?=$field["key"]?>",<?=($sortable ? "true" : "false")?>);
</script>
<?
	} else {
?>
<div class="multi_widget">
	<section>
		<p>There are no items available to tag.</p>
	</section>
</div>
<?
	}
?>