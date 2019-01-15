<?php
	// Generate the file manager restrictions
	$button_options = htmlspecialchars(json_encode(["currentlyKey" => $field["key"]."[managed]", "type" => "video"]));
?>
<div class="image_field video_field">
	<div class="contain">
		<input<?php if ($field["required"]) { ?> class="required"<?php } ?> type="url" tabindex="<?=$field["tabindex"]?>" name="<?=$field["key"]?>[new]" placeholder="YouTube or Vimeo URL" />
		<?php
			if (!defined("BIGTREE_FRONT_END_EDITOR") && empty($bigtree["form"]["embedded"])) {
		?>
		<span class="or">OR</span>		
		<a href="#<?=$field["id"]?>" data-options="<?=$button_options?>" class="button resource_browser_button"><span class="icon_video"></span>Browse</a>
		<?php
			}
		?>
	</div>
	<div class="currently" id="<?=$field["id"]?>"<?php if (empty($field["value"])) { ?> style="display: none;"<?php } ?>>
		<a href="#" class="remove_resource"></a>
		<div class="currently_wrapper">
			<?php
				if ($field["value"]) {
					if ($field["value"]["service"] == "YouTube") {
						echo '<iframe src="https://youtube.com/embed/'.$field["value"]["id"].'?autoplay=false&showinfo=false&showrel=false&showcontrols=false"></iframe>';
					} elseif ($field["value"]["service"] == "Vimeo") {
						echo '<iframe src="https://player.vimeo.com/video/'.$field["value"]["id"].'?autoplay=false&showinfo=false&showrel=false&showcontrols=false"></iframe>';
					}
				}
			?>
		</div>
		<label>CURRENT</label>
		<input type="hidden" name="<?=$field["key"]?>[existing]" value="<?=BigTree::safeEncode(json_encode($field["value"]))?>" />
	</div>
</div>