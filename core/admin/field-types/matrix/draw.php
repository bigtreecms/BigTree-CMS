<?php
	/**
	 * @global BigTreeAdmin $admin
	 * @global array $field
	 */
	
	if (!is_array($field["value"])) {
		$field["value"] = [];
	}
	
	$max = !empty($field["settings"]["max"]) ? $field["settings"]["max"] : 0;
	
	if (empty($field["settings"]["columns"])) {
		trigger_error("You must add at least one column to a matrix field.", E_USER_WARNING);
		
		return;
	}
?>
<div class="multi_widget matrix_list" id="<?=$field["id"]?>">
	<section class="multi_widget_instructions"<?php if (count($field["value"])) { ?> style="display: none;"<?php } ?>>
		<p>Click "Add Item" to add an item to this list.</p>
	</section>

	<ul id="<?=$field["id"]?>_list">
		<?php
			$x = 0;

			foreach ($field["value"] as $item) {
				// Convert timestamps for existing data to the user's frame of reference so when it saves w/o changes the time is correct
				$existing_data = $item;

				foreach ($field["settings"]["columns"] as $resource) {					
					$current_value = $existing_data[$resource["id"]];

					if (!empty($current_value) && empty($resource["settings"]["ignore_timezones"])) {
						if ($resource["type"] == "time") {
							$existing_data[$resource["id"]] = $admin->convertTimestampToUser($current_value, "H:i:s");
						} else if ($resource["type"] == "datetime") {
							$existing_data[$resource["id"]] = $admin->convertTimestampToUser($current_value, "Y-m-d H:i:s");
						}
					}
				}
		?>
		<li class="collapsed">
			<div class="inner">
				<span class="icon_sort"></span>
				<p class="multi_widget_entry_title">
					<?=BigTree::trimLength($existing_data["__internal-title"], 100)?>
					<small><?=BigTree::trimLength($existing_data["__internal-subtitle"] ,100)?></small>
				</p>
				<a href="#" class="icon_delete"></a>
				<a href="#" class="icon_edit"></a>
			</div>

			<div class="matrix_entry_fields">
				<?php
					foreach ($field["settings"]["columns"] as $resource) {
						if (!empty($resource["settings"])) {
							$settings = @json_decode($resource["settings"], true);
						} else if (!empty($resource["options"])) {
							$settings = @json_decode($resource["options"], true);
						} else {
							$settings = [];
						}

						$settings = is_array($settings) ? $settings : [];

						if (empty($settings["directory"])) {
							$settings["directory"] = "files/pages/";
						}
						
						$subfield = [
							"type" => $resource["type"],
							"title" => $resource["title"] ?? "",
							"subtitle" => $resource["subtitle"] ?? "",
							"key" => $field["key"]."[$x][".$resource["id"]."]",
							"has_value" => isset($existing_data[$resource["id"]]),
							"value" => $existing_data[$resource["id"]] ?? "",
							"tabindex" => $field["tabindex"],
							"settings" => $settings,
							"matrix_title_field" => !empty($resource["display_title"]),
						];
			
						BigTreeAdmin::drawField($subfield);
					}
				?>
			
				<button class="matrix_collapse button green">Done Editing</button>
			</div>
		</li>
		<?php
				$x++;
			}
		?>
	</ul>

	<footer>
		<a href="#" class="add_item add_item_button button"><span class="icon_small icon_small_add"></span>Add Item</a>
		<?php if ($max) { ?>
		<small class="max">LIMIT <?=$max?></small>
		<?php } ?>
	</footer>
</div>

<script>
	BigTreeMatrix({
		selector: "#<?=$field["id"]?>",
		list: "#<?=$field["id"]?>_list",
		key: "<?=$field["key"]?>",
		columns: <?=json_encode($field["settings"]["columns"])?>,
		max: <?=$max?>,
		style: "list",
		front_end_editor: <?=(defined("BIGTREE_FRONT_END_EDITOR") ? "true" : "false")?>
	});
</script>
