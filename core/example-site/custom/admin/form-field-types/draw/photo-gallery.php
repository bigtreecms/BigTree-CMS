<?
	// Modified Photo Gallery to add Attribution and Link attributes

	$photos = is_array($field["value"]) ? $field["value"] : array();
	$max = count($photos);
	$current = 0;
	
	$button_options = htmlspecialchars(json_encode(array(
		"minWidth" => $field["options"]["min_width"],
		"minHeight" => $field["options"]["min_height"]
	)));
?>
<div class="photo_gallery_widget" id="<?=$field["id"]?>">
	<ul>
		<?
			foreach ($photos as $photo) {
				if ($field["options"]["preview_prefix"]) {
					$preview_image = BigTree::prefixFile($photo["image"],$field["options"]["preview_prefix"]);
				} else {
					$preview_image = $photo["image"];
				}
		?>
		<li>
			<figure>
				<img src="<?=$preview_image?>" alt="" />
			</figure>
			<input type="hidden" name="<?=$field["key"]?>[<?=$current?>][image]" value="<?=$photo["image"]?>" />
			<input type="hidden" name="<?=$field["key"]?>[<?=$current?>][caption]" value="<?=$photo["caption"]?>" class="caption" />
			<input type="hidden" name="<?=$field["key"]?>[<?=$current?>][attribution]" value="<?=$photo["attribution"]?>" class="attribution" />
			<input type="hidden" name="<?=$field["key"]?>[<?=$current?>][link]" value="<?=$photo["link"]?>" class="link" />
			<? if (!$field["options"]["disable_captions"]) { ?>
			<a href="#" class="icon_edit"></a>
			<? } ?>
			<a href="#" class="icon_delete"></a>
		</li>
		<?
					$current++;
			}
		?>
	</ul>
	<footer class="image_field">
		<input type="file" tabindex="<?=$field["tabindex"]?>" name="<?=$field["key"]?>[<?=$current?>][image]" />
		<? if (!defined("BIGTREE_FRONT_END_EDITOR")) { ?>
		<span class="or">OR</span>
		<a href="#<?=$field["id"]?>" data-options="<?=$button_options?>" class="button form_image_browser"><span class="icon_images"></span>Browse</a>
		<? } ?>
	</footer>
</div>
<script>
	new CustomPhotoGallery("<?=$field["id"]?>","<?=$field["key"]?>",<?=$current?><? if ($field["options"]["disable_captions"]) { ?>,true<? } ?>);
</script>