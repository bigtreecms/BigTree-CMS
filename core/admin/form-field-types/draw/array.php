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
				<a href="#" class="icon_edit"></a>
				<a href="#" class="icon_delete"></a>
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
<script type="text/javascript">
	new BigTreeArrayOfItems("<?=$clean_key?>",<?=$x?>,"<?=$key?>",<?=json_encode($options["fields"])?>);
</script>