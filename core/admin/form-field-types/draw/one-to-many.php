<?
	if (!$field["value"]) {
		$field["value"] = array();
	} elseif (!is_array($field["value"])) {
		$field["value"] = json_decode($field["value"],true);
	}

	// Throw an exception if they didn't setup the field type properly
	if (!$field["options"]["table"] || !$field["options"]["title_column"]) {
		throw Exception("One-to-Many field type requires a table and a title field to be setup to function.");
	}

	$entries = array();
	$sort = $field["options"]["sort_by_column"] ? $field["options"]["sort_by_column"] : $field["options"]["title_column"]." ASC";

	// Get existing entries' titles
	foreach ($field["value"] as $entry) {
		$g = sqlfetch(sqlquery("SELECT `id`,`".$field["options"]["title_column"]."` FROM `".$field["options"]["table"]."` WHERE id = '".sqlescape($entry)."'"));
		if ($g) {
			$entries[$g["id"]] = $g[$field["options"]["title_column"]];
		}			
	}

	// Gather a list of the items that could possibly be used
	$list = array();
	$q = sqlquery("SELECT `id`,`".$field["options"]["title_column"]."` FROM `".$field["options"]["table"]."` ORDER BY $sort");
	while ($f = sqlfetch($q)) {
		$list[$f["id"]] = $f[$field["options"]["title_column"]];
	}

	// If we have a parser, send a list of the entries and available items through it.
	if (!empty($field["options"]["parser"])) {
		$list = call_user_func($field["options"]["parser"],$list,true);
		$entries = call_user_func($field["options"]["parser"],$entries,false);
	}

	// Remove items from the list that have already been used
	foreach ($entries as $k => $v) {
		unset($list[$k]);
	}
	
	// A count of the number of entries
	$x = 0;
	
	// Only show the field if there are items that could be used or removed
	if (count($list) || count($entries)) {
?>
<div class="multi_widget many_to_many" id="<?=$field["id"]?>">
	<section<? if (count($entries)) { ?> style="display: none;"<? } ?>>
		<p>Click "Add Item" to add an item to this list.</p>
	</section>
	<ul>
		<?
			foreach ($entries as $id => $title) {
		?>
		<li>
			<input type="hidden" name="<?=$field["key"]?>[<?=$x?>]" value="<?=BigTree::safeEncode($id)?>" />
			<span class="icon_sort"></span>
			<p><?=BigTree::safeEncode(BigTree::trimLength(strip_tags($title),100))?></p>
			<a href="#" class="icon_delete"></a>
		</li>
		<?
				$x++;
			}
		?>
	</ul>
	<footer>
		<select>
			<? foreach ($list as $id => $title) { ?>
			<option value="<?=BigTree::safeEncode($id)?>"><?=BigTree::safeEncode(BigTree::trimLength(strip_tags($title),100))?></option>
			<? } ?>
		</select>
		<a href="#" class="add button"><span class="icon_small icon_small_add"></span>Add Item</a>
		<?
			if ($field["options"]["show_add_all"]) {
		?>
		<a href="#" class="add_all button">Add All</a>
		<?
			}
			if ($field["options"]["show_reset"]) {
		?>
		<a href="#" class="reset button red">Reset</a>
		<?
			}
		?>
	</footer>
</div>
<script>
	BigTreeManyToMany({
		id: "<?=$field["id"]?>",
		count: <?=$x?>,
		key: "<?=$field["key"]?>",
		sortable: "true"
	});
</script>
<?
	} else {
?>
<div class="multi_widget">
	<section>
		<p>There are no items available.</p>
	</section>
</div>
<?
	}
?>