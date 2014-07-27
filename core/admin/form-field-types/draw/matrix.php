<?
	if (!is_array($field["value"])) {
		$field["value"] = array();
	}
	if (!function_exists("_localDrawCalloutLevel")) {
		// We're going to loop through the callout array so we don't have to do stupid is_array crap anymore.
		function _localDrawMatrixLevel($keys,$level) {
			global $field;
			foreach ($level as $key => $value) {
				if (is_array($value)) {
					_localDrawMatrixLevel(array_merge($keys,array($key)),$value);
				} else {
?>
<input type="hidden" name="<?=$field["key"]?>[<?=implode("][",$keys)?>][<?=$key?>]" value="<?=BigTree::safeEncode($value)?>" />
<?
				}
			}
		}
	}
?>
<fieldset class="callouts<? if ($bigtree["last_resource_type"] == "callouts") { ?> callouts_no_margin<? } ?>" id="<?=$field["id"]?>">
	<label<?=$label_validation_class?>><?=$field["title"]?><? if ($field["subtitle"]) { ?> <small><?=$field["subtitle"]?></small><? } ?></label>
	<div class="contain">
		<?
			$x = 0;
			foreach ($field["value"] as $item) {
		?>
		<article>
			<input type="hidden" class="btx_matrix_data" value="<?=base64_encode(json_encode($item))?>" />
			<? _localDrawMatrixLevel(array($x),$item) ?>
			<h4>
				<?=BigTree::safeEncode($item["__internal-title"])?>
				<input type="hidden" name="<?=$field["key"]?>[<?=$x?>][__internal-title]" value="<?=BigTree::safeEncode($item["__internal-title"])?>" />
			</h4>
			<p>
				<?=BigTree::safeEncode($item["__internal-subtitle"])?>
				<input type="hidden" name="<?=$field["key"]?>[<?=$x?>][__internal-subtitle]" value="<?=BigTree::safeEncode($item["__internal-subtitle"])?>" />
			</p>
			<div class="bottom">
				<span class="icon_drag"></span>
				<a href="#" class="icon_edit"></a>
				<a href="#" class="icon_delete"></a>
			</div>
		</article>
		<?
				$x++;
			}
		?>
	</div>
	<a href="#" class="add_item button"><span class="icon_small icon_small_add"></span>Add Item</a>
	<script>
		if (typeof BTXMatrix == "undefined") {
			$("body").append('<script type="text/javascript" src="<?=ADMIN_ROOT?>js/btx-matrix.js">');
		}
		$(function() {
		 	BTXMatrix.init("#<?=$field["id"]?>","<?=$field["key"]?>",<?=json_encode($field["options"]["columns"])?>);
			BTXMatrix.count += <?=count($field["value"])?>;
		});
	</script>
</fieldset>