<?php
	if (!is_array($field["value"])) {
		$field["value"] = array();
	}

	$noun = !empty($field["settings"]["noun"]) ? htmlspecialchars($field["settings"]["noun"]) : "Callout";
	$max = !empty($field["settings"]["max"]) ? $field["settings"]["max"] : 0;

	// Work with older group info from 4.1 and lower
	if (empty($field["settings"]["groups"]) && !empty($field["settings"]["group"])) {
		$field["settings"]["groups"] = array($field["settings"]["group"]);
	}

	if (!empty($field["settings"]["groups"])) {
		$callouts_available = $admin->getCalloutsInGroups($field["settings"]["groups"]);
	} else {
		$callouts_available = $admin->getCalloutsAllowed("name ASC");
	}
	
	
	$cached_types = $admin->getCachedFieldTypes();
	$bigtree["previous_field_types"] = $bigtree["field_types"] ?? [];
	$bigtree["field_types"] = $cached_types["callouts"];
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
				
				// Callout type was deleted
				if (!$type) {
					continue;
				}
				
				$disabled = ($type["level"] > $admin->Level);
				
				// Convert timestamps for existing data to the user's frame of reference so when it saves w/o changes the time is correct
				$existing_data = $callout;
				
				foreach ($type["resources"] as $resource) {
					$current_value = $existing_data[$resource["id"]] ?? null;
					
					if (!empty($current_value) && empty($resource["settings"]["ignore_timezones"])) {
						if ($resource["type"] == "time") {
							$existing_data[$resource["id"]] = $admin->convertTimestampToUser($current_value, "H:i:s");
						} else if ($resource["type"] == "datetime") {
							$existing_data[$resource["id"]] = $admin->convertTimestampToUser($current_value, "Y-m-d H:i:s");
						}
					}
				}

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

			<div class="matrix_entry_fields callout_fields">
				<input type="hidden" name="<?=$field["key"]?>[<?=$x?>][type]" value="<?=$callout["type"]?>">

				<?php
					// Run hooks for modifying the field array
					$type["resources"] = $admin->runHooks("fields", "callout", $type["resources"], [
						"callout" => $type,
						"step" => "draw"
					]);
					
					$bigtree["callout"] = $type;
					
					foreach ($type["resources"] as $resource) {
						if (!empty($resource["settings"])) {
							$settings = $resource["settings"];
						} elseif (!empty($resource["options"])) {
							$settings = $resource["options"];
						} else {
							$settings = [];
						}
						
						$subfield = [
							"type" => $resource["type"],
							"title" => $resource["title"],
							"subtitle" => $resource["subtitle"],
							"key" => $field["key"]."[$x][".$resource["id"]."]",
							"has_value" => isset($existing_data[$resource["id"]]),
							"value" => $existing_data[$resource["id"]] ?? "",
							"tabindex" => $field["tabindex"],
							"settings" => $settings,
						];
		
						if (empty($subfield["settings"]["directory"])) {
							$subfield["settings"]["directory"] = "files/callouts/";
						}
			
						BigTreeAdmin::drawField($subfield);
					}
				?>
			
				<button class="matrix_collapse button green">Done Editing</button>
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
<?php
	$bigtree["field_types"] = $bigtree["previous_field_types"];
?>