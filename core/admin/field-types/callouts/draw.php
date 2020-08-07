<?php
	if (!is_array($field["value"])) {
		$field["value"] = array();
	}

	$noun = $field["settings"]["noun"] ? htmlspecialchars($field["settings"]["noun"]) : "Callout";
	$max = !empty($field["settings"]["max"]) ? $field["settings"]["max"] : 0;

	// Work with older group info from 4.1 and lower
	if (!is_array($field["settings"]["groups"]) && $field["settings"]["group"]) {
		$field["settings"]["groups"] = array($field["settings"]["group"]);
	}

	if (!empty($field["settings"]["groups"])) {
		$callouts_available = $admin->getCalloutsInGroups($field["settings"]["groups"]);
	} else {
		$callouts_available = $admin->getCalloutsAllowed("name ASC");
	}
?>
<div class="multi_widget matrix_list" id="<?=$field["id"]?>">
	<section class="multi_widget_instructions"<?php if (count($field["value"])) { ?> style="display: none;"<?php } ?>>
		<p>Click "Add <?=$noun?>" to add an item to this list.</p>
	</section>

	<ul id="<?=$field["id"]?>_list">
		<?php
			$x = 0;

			foreach ($field["value"] as $callout) {
				$type = $admin->getCallout($callout["type"]);
				$disabled = ($type["level"] > $admin->Level);

				if (!empty($type)) {
		?>
		<li class="collapsed<?php if ($disabled) { ?> disabled<?php } ?>"<?php if ($disabled) { ?> data-tooltip="<p>This item requires a higher user level to edit.</p>"<?php } ?>>
			<div class="inner">
				<span class="icon_sort"></span>
				<p class="multi_widget_entry_title">
					<?=BigTree::trimLength($callout["display_title"], 100)?>
					<small><?=BigTree::trimLength($type["name"] ,100)?></small>						
				</p>
				<a href="#" class="icon_delete"></a>
				<a href="#" class="icon_edit"></a>
			</div>

			<div class="matrix_entry_fields">
				<input type="hidden" name="<?=$field["key"]?>[<?=$x?>][type]" value="<?=$callout["type"]?>">

				<?php
					foreach ($type["resources"] as $resource) {
						$subfield = [
							"type" => $resource["type"],
							"title" => $resource["title"],
							"subtitle" => $resource["subtitle"],
							"key" => $field["key"]."[$x][".$resource["id"]."]",
							"has_value" => isset($bigtree["resources"][$resource["id"]]),
							"value" => isset($callout[$resource["id"]]) ? $callout[$resource["id"]] : "",
							"tabindex" => $field["tabindex"],
							"settings" => $resource["settings"] ?: $resource["options"]
						];
		
						if (empty($subfield["settings"]["directory"])) {
							$subfield["settings"]["directory"] = "files/callouts/";
						}
			
						BigTreeAdmin::drawField($subfield);
					}
				?>
			</div>
		</li>
		<?php
					$x++;
				}
			}
		?>
	</ul>

	<footer>
		<select class="callout_type">
			<?php
				foreach ($callouts_available as $option) {
			?>
			<option value="<?=$option["id"]?>"><?=$option["name"]?></option>
			<?php
				}
			?>
		</select>
		<a href="#" class="add_item button"><span class="icon_small icon_small_add"></span>Add <?=$noun?></a>
		<?php if ($max) { ?>
		<small class="max">LIMIT <?=$max?></small>
		<?php } ?>
	</footer>
</div>

<script>
	BigTreeCallouts({
		selector: "#<?=$field["id"]?>",
		list: "#<?=$field["id"]?>_list",
		key: "<?=$field["key"]?>",
		noun: "<?=$noun?>",
		max: <?=$max?>,
		tab_index: <?=$field["tabindex"]?>,
		front_end_editor: <?=(defined("BIGTREE_FRONT_END_EDITOR") ? "true" : "false")?>
	});
</script>