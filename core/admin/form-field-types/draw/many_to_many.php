<?
	$cols = sqlcolumns($options["mtm-connecting-table"]);
	$sortable = false;
	if ($cols["position"])
		$sortable = true;
	
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
	} else {
		if ($sortable) {
			$q = sqlquery("SELECT * FROM `".$options["mtm-connecting-table"]."` WHERE `".$options["mtm-my-id"]."` = '$item_id' ORDER BY `position`");
		} else {
			$q = sqlquery("SELECT * FROM `".$options["mtm-connecting-table"]."` WHERE `".$options["mtm-my-id"]."` = '$item_id'");
		}
		
		$entries = array();
		while ($f = sqlfetch($q)) {
			$g = sqlfetch(sqlquery("SELECT * FROM `".$options["mtm-other-table"]."` WHERE id = '".$f[$options["mtm-other-id"]]."'"));
			if ($g) {
				$entries[$g["id"]] = $g[$options["mtm-other-descriptor"]];
			}
		}
	}
	
	$list = array();
	$q = sqlquery("SELECT * FROM `".$options["mtm-other-table"]."` ORDER BY ".$options["mtm-sort"]);
	while ($f = sqlfetch($q)) {
		$list[$f["id"]] = $f[$options["mtm-other-descriptor"]];
	}
	
	if (isset($options["mtm-list-parser"])) {
		eval('$list = '.$options["mtm-list-parser"].'($list);');
	}
	
	$clean_key = str_replace(array("[","]"),"_",$key);
	
	$x = 0;
?>
<fieldset id="<?=$clean_key?>">
	<? if ($title) { ?><label><?=$title?><? if ($subtitle) { ?> <small><?=$subtitle?></small><? } ?></label><? } ?>
	<div class="multi_widget many_to_many">
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
				<option value="<?=htmlspecialchars($k)?>"><?=htmlspecialchars(BigTree::trimLength(strip_tags($v),50))?></option>
				<? } ?>
			</select>
			<a href="#" class="add button"><span class="icon_small icon_small_add"></span>Add Item</a>
		</footer>
	</div>
</fieldset>
<script type="text/javascript">
	new BigTreeManyToMany("<?=$clean_key?>",<?=$x?>,"<?=$key?>",<?=($sortable ? "true" : "false")?>);
</script>