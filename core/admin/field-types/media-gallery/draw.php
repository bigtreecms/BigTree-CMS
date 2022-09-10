<?php
	/**
	 * @global BigTreeAdmin $admin
	 * @global array $field
	 */
	
	$max = !empty($field["settings"]["max"]) ? intval($field["settings"]["max"]) : 0;
	$current = 0;
	
	if (empty($field["value"]) || !is_array($field["value"])) {
		$items = [];
	} else {
		$items = $field["value"];
	}
	
	if (empty($field["settings"]["columns"]) || !is_array($field["settings"]["columns"])) {
		$columns = [];
	} else {
		$columns = $field["settings"]["columns"];
	}
	
	$media_settings = $field["settings"];
	unset($media_settings["columns"]);
	
	if (!empty($media_settings["preset"])) {
		if (!isset($bigtree["media_settings"])) {
			$bigtree["media_settings"] = BigTreeJSONDB::get("config", "media-settings");
		}
		
		$preset = $bigtree["media_settings"]["presets"][$media_settings["preset"]];
		
		if (!empty($preset["min_width"])) {
			$media_settings["min_width"] = $preset["min_width"];
		}
		
		if (!empty($preset["min_height"])) {
			$media_settings["min_height"] = $preset["min_height"];
		}
	}
?>
<div class="multi_widget media_gallery" id="<?=$field["id"]?>">
	<section class="multi_widget_instructions"<?php if (count($items)) { ?> style="display: none;"<?php } ?>>
		<p>Click one of the options below to add a media gallery entry.</p>
	</section>
	
	<ul id="<?=$field["id"]?>_list">
		<?php
			$x = 0;
			
			foreach ($items as $item) {
				if (empty($item["type"])) {
					$item["type"] = "image";
				}
				
				if ($item["type"] == "video") {
					$type = $item["video"]["service"];
				} else {
					$type = "image";
				}
				
				if (!empty($field["settings"]["preview_prefix"])) {
					$preview_image = BigTree::prefixFile($item["image"], $field["settings"]["preview_prefix"]);
				} else {
					$preview_image = $item["image"];
				}
				
				// Convert timestamps for existing data to the user's frame of reference so when it saves w/o changes the time is correct
				$existing_additional_data = $item["info"];
				
				foreach ($field["settings"]["columns"] as $resource) {
					$current_value = $existing_additional_data[$resource["id"]];
					
					if (!empty($current_value) && empty($resource["settings"]["ignore_timezones"])) {
						if ($resource["type"] == "time") {
							$existing_additional_data[$resource["id"]] = $admin->convertTimestampToUser($current_value, "H:i:s");
						} else if ($resource["type"] == "datetime") {
							$existing_additional_data[$resource["id"]] = $admin->convertTimestampToUser($current_value, "Y-m-d H:i:s");
						}
					}
				}
		?>
		<li class="collapsed">
			<div class="inner">
				<span class="icon_sort"></span>
				
				<figure<?php if ($item["type"] == "video") { ?> class="media_gallery_type_<?=strtolower($item["video"]["service"])?>"<?php } ?>>
					<img src="<?=$preview_image?>" alt="" />
				</figure>
				
				<p class="multi_widget_entry_title">
					<?=BigTree::trimLength($item["__internal-title"] ?? "", 100)?>
					<small><?=BigTree::trimLength($item["__internal-subtitle"] ?? "", 100)?></small>
				</p>
				
				<a href="#" class="icon_delete"></a>
				<a href="#" class="icon_edit"></a>
			</div>
			
			<div class="matrix_entry_fields">
				<input type="hidden" name="<?=$field["key"]."[$x][type]"?>" value="<?=$item["type"]?>" />
				<?php
					if ($item["type"] == "photo") {
						$media_fields = [[
							"id" => "*photo",
							"type" => "image",
							"title" => "Photo",
							"subtitle" => (!empty($media_settings["min_width"]) && !empty($media_settings["min_height"])) ?
								"(min ".$media_settings["min_width"]."x".$media_settings["min_height"].")" : "",
							"settings" => array_merge(["validation" => "required"], $media_settings),
							"value" => $item["image"],
						]];
					} elseif ($item["type"] == "video" && $item["video"]["service"] == "local") {
						$media_fields = [
							[
								"id" => "*localvideo",
								"type" => "upload",
								"title" => "Video",
								"subtitle" => "(h264 file)",
								"settings" => ["required" => true],
								"value" => $item["video"]["url"],
							],
							[
								"id" => "*photo",
								"type" => "image",
								"title" => "Cover Photo",
								"subtitle" => (!empty($media_settings["min_width"]) && !empty($media_settings["min_height"])) ?
									"(min ".$media_settings["min_width"]."x".$media_settings["min_height"].")" : "",
								"settings" => array_merge(["required" => true], $media_settings),
								"value" => $item["image"],
							],
						];
					} else {
						$media_fields = [[
							"id" => "*video",
							"type" => "video",
							"title" => "Video URL",
							"subtitle" => "(include http://)",
							"settings" => ["required" => true],
							"value" => array_merge(["image" => $item["image"]], $item["video"]),
						]];
					}
					
					foreach (array_merge($media_fields, $columns) as $resource) {
						if (!empty($resource["settings"])) {
							$settings = is_array($resource["settings"]) ? $resource["settings"] : @json_decode($resource["settings"], true);
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
							"has_value" => isset($resource["value"]) || isset($existing_additional_data[$resource["id"]]),
							"value" => $resource["value"] ?? ($existing_additional_data[$resource["id"]] ?? ""),
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
	
	<footer class="media_gallery_footer">
		<?php
			if (empty($field["settings"]["disable_photos"])) {
		?>
		<a href="#" class="add_item button" data-type="photo"><span class="icon_small icon_small_picture"></span>Add Photo</a>
		<?php
			}
			
			if (empty($field["settings"]["disable_youtube"])) {
		?>
		<a href="#" class="add_item button" data-type="youtube"><span class="icon_small icon_small_youtube"></span>Add YouTube Video</a>
		<?php
			}
			
			if (empty($field["settings"]["disable_vimeo"])) {
		?>
		<a href="#" class="add_item button" data-type="vimeo"><span class="icon_small icon_small_vimeo"></span>Add Vimeo Video</a>
		<?php
			}
			
			if (!empty($field["settings"]["enable_manual"])) {
		?>
		<a href="#" class="add_item button" data-type="local"><span class="icon_small icon_small_video"></span>Add Local Video</a>
		<?php
			}
			
			if ($max) {
		?>
		<small class="max">LIMIT <?=$max?></small>
		<?php
			}
		?>
	</footer>
</div>

<script>
	BigTree.hookReady(function() {
		BigTreeMediaGallery({
			selector: "#<?=$field["id"]?>",
			key: "<?=$field["key"]?>",
			list: "#<?=$field["id"]?>_list",
			columns: <?=json_encode($columns)?>,
			settings: <?=json_encode($media_settings)?>,
			max: <?=$max?>
		});
	});
</script>