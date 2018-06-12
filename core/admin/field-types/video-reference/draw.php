<?php
	if ($field["value"]) {
		$resource = $admin->getResource($field["value"]);
	}
	
	// Generate the file manager restrictions
	$button_options = htmlspecialchars(json_encode(["currentlyKey" => $field["key"], "type" => "video"]));
?>
<div class="image_field<?php if ($field["options"]["validation"] == "required") { ?> reference_required<?php } ?>">
	<a href="#<?=$field["id"]?>" data-options="<?=$button_options?>" class="button resource_browser_button"><span class="icon_video"></span>Browse</a>
	<br class="clear" />
	<div class="currently" id="<?=$field["id"]?>"<?php if (!$field["value"] || !$resource) { ?> style="display: none;"<?php } ?>>
		<a href="#" class="remove_resource"></a>
		<div class="currently_wrapper">
			<?php
				if ($field["value"]) {
					if ($resource["location"] == "YouTube") {
						echo '<iframe src="https://youtube.com/embed/'.$resource["video_data"]["id"].'?autoplay=false&showinfo=false&showrel=false&showcontrols=false"></iframe>';
					} elseif ($resource["location"] == "Vimeo") {
						echo '<iframe src="https://player.vimeo.com/video/'.$resource["video_data"]["id"].'?autoplay=false&showinfo=false&showrel=false&showcontrols=false"></iframe>';
					}
				}
			?>
		</div>
		<label>CURRENT</label>
		<input type="hidden" name="<?=$field["key"]?>" value="<?=$field["value"]?>" />
	</div>
</div>