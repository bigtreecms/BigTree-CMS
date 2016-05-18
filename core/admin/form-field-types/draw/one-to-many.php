<?php
	namespace BigTree;
	
	if (!$field["value"]) {
		$field["value"] = array();
	} elseif (!is_array($field["value"])) {
		$field["value"] = json_decode($field["value"],true);
	}

	// Throw a warning if they didn't setup the field type properly
	if (!$field["options"]["table"] || !$field["options"]["title_column"]) {
		trigger_error("One-to-Many field type requires a table and a title field to be setup to function.",E_USER_ERROR);
	}

	$entries = array();
	$sort = $field["options"]["sort_by_column"] ? $field["options"]["sort_by_column"] : $field["options"]["title_column"]." ASC";

	// Get existing entries' titles
	foreach ($field["value"] as $entry) {
		$title = SQL::fetchSingle("SELECT `".$field["options"]["title_column"]."` FROM `".$field["options"]["table"]."` WHERE id = ?", $entry);
		if ($title !== false) {
			$entries[$entry] = $title;
		}			
	}

	// Gather a list of the items that could possibly be used
	$list = array();
	$query = SQL::query("SELECT `id`, `".$field["options"]["title_column"]."` AS `title` 
						 FROM `".$field["options"]["table"]."` ORDER BY $sort");
	while ($entry = $query->fetch()) {
		$list[$entry["id"]] = $entry["title"];
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
	<section<?php if (count($entries)) { ?> style="display: none;"<?php } ?>>
		<p>Click "Add Item" to add an item to this list.</p>
	</section>
	<ul>
		<?php
			foreach ($entries as $id => $title) {
		?>
		<li>
			<input type="hidden" name="<?=$field["key"]?>[<?=$x?>]" value="<?=Text::htmlEncode($id)?>" />
			<span class="icon_sort"></span>
			<p><?=Text::htmlEncode(Text::trimLength(strip_tags($title),100))?></p>
			<a href="#" class="icon_delete"></a>
		</li>
		<?php
				$x++;
			}
		?>
	</ul>
	<footer>
		<select>
			<?php foreach ($list as $id => $title) { ?>
			<option value="<?=Text::htmlEncode($id)?>"><?=Text::htmlEncode(Text::trimLength(strip_tags($title),100))?></option>
			<?php } ?>
		</select>
		<a href="#" class="add button"><span class="icon_small icon_small_add"></span>Add Item</a>
		<?php
			if ($field["options"]["show_add_all"]) {
		?>
		<a href="#" class="add_all button">Add All</a>
		<?php
			}
			if ($field["options"]["show_reset"]) {
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
		sortable: "true"
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