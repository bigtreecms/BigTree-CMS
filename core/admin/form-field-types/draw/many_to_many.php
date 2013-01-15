<?
	// Find out whether this is a draggable Many to Many.
	$table_description = BigTree::describeTable($options["mtm-connecting-table"]);
	$cols = $table_description["columns"];
	$sortable = false;
	if (isset($cols["position"])) {
		$sortable = true;
	}
	
	// If we have $many_to_many[$key] set then this item is either pending or has pending changes so we use that data.
	if ($many_to_many[$key]) {
		$entries = array();
		if (!empty($many_to_many[$key]["data"])) {
			foreach ($many_to_many[$key]["data"] as $oid) {
				$g = sqlfetch(sqlquery("SELECT * FROM `".$options["mtm-other-table"]."` WHERE id = '$oid'"));
				if ($g) {
					$entries[$g["id"]] = $g[$options["mtm-other-descriptor"]];
				}			
			}
		}
	// No pending data, let's query the connecting table directly for the entries.
	} else {
		if ($sortable) {
			$q = sqlquery("SELECT * FROM `".$options["mtm-connecting-table"]."` WHERE `".$options["mtm-my-id"]."` = '$edit_id' ORDER BY `position`");
		} else {
			$q = sqlquery("SELECT * FROM `".$options["mtm-connecting-table"]."` WHERE `".$options["mtm-my-id"]."` = '$edit_id'");
		}
		
		$entries = array();
		while ($f = sqlfetch($q)) {
			// Get the title from the other table.
			$g = sqlfetch(sqlquery("SELECT * FROM `".$options["mtm-other-table"]."` WHERE id = '".$f[$options["mtm-other-id"]]."'"));
			if ($g) {
				$entries[$g["id"]] = $g[$options["mtm-other-descriptor"]];
			}
		}
	}

	// Gather a list of the items that could possibly be tagged.
	$list = array();
	$q = sqlquery("SELECT * FROM `".$options["mtm-other-table"]."` ORDER BY ".$options["mtm-sort"]);
	while ($f = sqlfetch($q)) {
		$list[$f["id"]] = $f[$options["mtm-other-descriptor"]];
	}
	
	// If we have a parser, send a list of the entries and available items through it.
	if (isset($options["mtm-list-parser"]) && $options["mtm-list-parser"]) {
		eval('$list = '.$options["mtm-list-parser"].'($list,true);');
		eval('$entries = '.$options["mtm-list-parser"].'($entries,false);');
	}

	// Remove items from the list that have already been tagged.
	foreach ($entries as $k => $v) {
		unset($list[$k]);
	}
	
	// Get a key we can use in JavaScript
	$clean_key = str_replace(array("[","]"),"_",$key);
	
	// A count of the number of entries
	$x = 0;
	
	// Only show the field if there are items that could be tagged.
	if (count($list)) {
?>
<fieldset id="<?=$clean_key?>">
	<? if ($title) { ?><label><?=$title?><? if ($subtitle) { ?> <small><?=$subtitle?></small><? } ?></label><? } ?>
	<div class="multi_widget many_to_many">
		<section<? if (count($entries)) { ?> style="display: none;"<? } ?>>
			<p>No items have been tagged. Click "Add Item" to add an item to this list.</p>
		</section>
		<ul>
			<?
				foreach ($entries as $id => $description) {
			?>
			<li>
				<input type="hidden" name="<?=$key?>[<?=$x?>]" value="<?=htmlspecialchars($id)?>" />
				<? if ($sortable) { ?>
				<span class="icon_sort"></span>
				<? } ?>
				<p><?=BigTree::trimLength(strip_tags($description),100)?></p>
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
				<option value="<?=htmlspecialchars(htmlspecialchars_decode($k))?>"><?=htmlspecialchars(htmlspecialchars_decode(BigTree::trimLength(strip_tags($v),100)))?></option>
				<? } ?>
			</select>
			<a href="#" class="add button"><span class="icon_small icon_small_add"></span>Add Item</a>
		</footer>
	</div>
</fieldset>
<script>
	new BigTreeManyToMany("<?=$clean_key?>",<?=$x?>,"<?=$key?>",<?=($sortable ? "true" : "false")?>);
</script>
<?
	}
?>