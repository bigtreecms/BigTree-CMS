<?
	if (!is_array($field["value"])) {
		$field["value"] = array();
	}
	if (!function_exists("_localDrawCalloutLevel")) {
		// We're going to loop through the callout array so we don't have to do stupid is_array crap anymore.
		function _localDrawCalloutLevel($keys,$level) {
			global $field;
			foreach ($level as $key => $value) {
				if (is_array($value)) {
					_localDrawCalloutLevel(array_merge($keys,array($key)),$value);
				} else {
?>
<input type="hidden" name="<?=$field["key"]?>[<?=implode("][",$keys)?>][<?=$key?>]" value="<?=BigTree::safeEncode($value)?>" />
<?
				}
			}
		}
	}

	$noun = $field["options"]["noun"] ? htmlspecialchars($field["options"]["noun"]) : "Callout";
?>
<fieldset class="callouts<? if ($bigtree["last_resource_type"] == "callouts") { ?> callouts_no_margin<? } ?>" id="<?=$field["id"]?>">
	<label<?=$label_validation_class?>><?=$field["title"]?><? if ($field["subtitle"]) { ?> <small><?=$field["subtitle"]?></small><? } ?></label>
	<div class="contain">
		<?
			$x = 0;
			foreach ($field["value"] as $callout) {
				$type = $admin->getCallout($callout["type"]);
		?>
		<article>
			<input type="hidden" class="callout_data" value="<?=base64_encode(json_encode($callout))?>" />
			<? _localDrawCalloutLevel(array($x),$callout) ?>
			<h4>
				<?=BigTree::safeEncode($callout["display_title"])?>
				<input type="hidden" name="<?=$field["key"]?>[<?=$x?>][display_title]" value="<?=BigTree::safeEncode($callout["display_title"])?>" />
			</h4>
			<p><?=$type["name"]?></p>
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
	<a href="#" class="add_callout button"><span class="icon_small icon_small_add"></span>Add <?=$noun?></a>
	<script>
		$(function() {
			BigTreeCallouts.init("#<?=$field["id"]?>","<?=$field["key"]?>","<?=$noun?>","<?=$field["options"]["group"]?>");
			BigTreeCallouts.count += <?=count($field["value"])?>;
		});
	</script>
</fieldset>