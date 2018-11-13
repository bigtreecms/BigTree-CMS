<?php
	if (!$field["value"]) {
		$field["value"] = array();
	} elseif (!is_array($field["value"])) {
		$field["value"] = json_decode($field["value"],true);
	}

	$max = !empty($field["settings"]["max"]) ? $field["settings"]["max"] : 0;

	// Throw an exception if they didn't setup the field type properly
	if (!$field["settings"]["table"] || !$field["settings"]["title_column"]) {
		throw new Exception("One-to-Many field type requires a table and a title field to be setup to function.");
	}

	$entries = array();
	$sort = $field["settings"]["sort_by_column"] ? $field["settings"]["sort_by_column"] : $field["settings"]["title_column"]." ASC";

	// Get existing entries' titles
	foreach ($field["value"] as $entry) {
		$g = sqlfetch(sqlquery("SELECT `id`,`".$field["settings"]["title_column"]."` FROM `".$field["settings"]["table"]."` WHERE id = '".sqlescape($entry)."'"));
		if ($g) {
			$entries[$g["id"]] = $g[$field["settings"]["title_column"]];
		}			
	}

	// Gather a list of the items that could possibly be used
	$list = array();
	$q = sqlquery("SELECT `id`,`".$field["settings"]["title_column"]."` FROM `".$field["settings"]["table"]."` ORDER BY $sort");
	while ($f = sqlfetch($q)) {
		$list[$f["id"]] = $f[$field["settings"]["title_column"]];
	}

	// If we have a parser, send a list of the entries and available items through it.
	if (!empty($field["settings"]["parser"])) {
		$list = call_user_func($field["settings"]["parser"],$list,true);
		$entries = call_user_func($field["settings"]["parser"],$entries,false);
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
	<section<?php if (count($entries)) { ?> style="display: none;"<?php } ?>>
		<p>Click "Add Item" to add an item to this list.</p>
	</section>
	<ul>
		<?php
			foreach ($entries as $id => $title) {
		?>
		<li>
			<input type="hidden" name="<?=$field["key"]?>[<?=$x?>]" value="<?=BigTree::safeEncode($id)?>" />
			<span class="icon_sort"></span>
			<p><?=BigTree::safeEncode(BigTree::trimLength(strip_tags($title),100))?></p>
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
				<?php foreach ($list as $id => $title) { ?>
				<option value="<?=BigTree::safeEncode($id)?>"><?=BigTree::safeEncode(BigTree::trimLength(strip_tags($title),100))?></option>
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
		sortable: "true",
		max: <?=$max?>
	});
</script>
<?php
	} else {
?>
<div class="multi_widget">
	<section>
		<p>There are no items available.</p>
	</section>
</div>
<?php
	}
?>