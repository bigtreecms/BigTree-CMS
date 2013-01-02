<?
	if (is_string($value)) {
		$entries = json_decode($value,true);
		if (!is_array($entries)) {
			$entries = array();
		}
	} elseif (is_array($value)) {
		$entries = $value;
	} else {
		$entries = array();
	}
	$x = 0;
	
	$clean_key = str_replace(array("[","]"),"_",$key);
	
	// We recreate the field array because Chrome doesn't like to do for loops properly when the numeric keys are out of order.
	$aoi_fields = array();
	foreach ($options["fields"] as $f) {
		$aoi_fields[] = $f;
	}
?>
<fieldset id="<?=$clean_key?>">
	<? if ($title) { ?><label><?=$title?><? if ($subtitle) { ?> <small><?=$subtitle?></small><? } ?></label><? } ?>
	<div class="multi_widget">
		<ul>
			<?
				foreach ($entries as $entry) {
			?>
			<li>
				<input type="hidden" name="<?=$key?>[<?=$x?>]" value="<?=htmlspecialchars(json_encode($entry))?>" />
				<span class="icon_sort"></span>
				<p><?=BigTree::trimLength(strip_tags(current($entry)),100)?></p>
				<a href="#" class="icon_delete"></a>
				<a href="#" class="icon_edit"></a>
			</li>
			<?
					$x++;
				}
			?>
		</ul>
		<footer>
			<a href="#" class="add button"><span class="icon_small icon_small_add"></span>Add Item</a>
		</footer>
	</div>
</fieldset>
<script>
	new BigTreeArrayOfItems("<?=$clean_key?>",<?=$x?>,"<?=$key?>",<?=json_encode($aoi_fields)?>);
</script>