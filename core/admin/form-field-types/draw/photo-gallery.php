<?
	if (is_string($value)) {
		$pgw_data = json_decode($value,true);	
	} elseif (is_array($value)) {
		$pgw_data = $value;
	} else {
		$pgw_data = array();
	}
	$pgw_max = count($pgw_data);
	$pgw_current = 0;
	
	$button_options = array(
		"minWidth" => $options["min_width"],
		"minHeight" => $options["min_height"],
		"previewPrefix" => $options["preview_prefix"]
	);
	if ($value) {
		$buttion_options["preview"] = true;
	}
	$button_options = htmlspecialchars(json_encode($button_options));
?>
<fieldset>
	<? if ($title) { ?><label<?=$label_validation_class?>><?=$title?><? if ($subtitle) { ?> <small><?=$subtitle?></small><? } ?></label><? } ?>
	<div class="photo_gallery_widget" id="pgw_<?=$cms->urlify($key)?>">
		<ul id="list_pgw_<?=$cms->urlify($key)?>">
			<?
				if (is_array($pgw_data)) {
					foreach ($pgw_data as $pd) {
						if ($options["preview_prefix"]) {
							$pinfo = BigTree::pathInfo($pd["image"]);
							$preview_image = $pinfo["dirname"]."/".$options["preview_prefix"].$pinfo["basename"];
						} else {
							$preview_image = $pd["image"];
						}
			?>
			<li>
				<figure>
					<img src="<?=$preview_image?>" alt="" />
				</figure>
				<input type="hidden" name="<?=$key?>[<?=$pgw_current?>][image]" value="<?=$pd["image"]?>" />
				<input type="hidden" name="<?=$key?>[<?=$pgw_current?>][caption]" value="<?=$pd["caption"]?>" class="caption" />
				<a href="#" class="icon_edit"></a><a href="#" class="icon_delete"></a>
			</li>
			<?
						$pgw_current++;
					}
				}
			?>
		</ul>
		<footer class="image_field">
			<input<?=$input_validation_class?> type="file" tabindex="<?=$tabindex?>" name="<?=$key?>[<?=$pgw_current?>][image]" id="field_<?=$key?>" />
			<span class="or">OR</span>
			<a href="#field_currently_<?=$key?>" name="<?=$button_options?>" class="button form_image_browser"><span class="icon_images"></span>Browse</a>
			<a href="#" class="button blue add_photo">Add Photo</a>
		</footer>
	</div>
</fieldset>
<script>
	test = new BigTreePhotoGallery("pgw_<?=$cms->urlify($key)?>","<?=$key?>",<?=$pgw_current?>);
	$("#pgw_<?=$cms->urlify($key)?> img").load(function() {
		w = $(this).width();
		h = $(this).height();
		if (w > h) {
			perc = 75 / w;
			h = perc * h;
			style = { margin: Math.floor((75 - h) / 2) + "px 0 0 0" };
		} else {
			perc = 75 / h;
			w = perc * w;
			style = { margin: "0 0 0 " + Math.floor((75 - w) / 2) + "px" };
		}
		
		$(this).css(style);
	});
</script>