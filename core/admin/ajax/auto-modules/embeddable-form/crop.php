<?
	BigTree::globalizeArray($_SESSION["bigtree_admin"]["form_data"]);
?>
<div class="container">
	<form method="post" action="<?=$bigtree["form_root"]?>process-crops/?hash=<?=$bigtree["form"]["hash"]?>" id="crop_form" class="module">
		<input type="hidden" name="return_page" value="<?=htmlspecialchars($return_link)?>" />
		<input type="hidden" name="crop_info" value="<?=htmlspecialchars(json_encode($crops))?>" />
		<section id="cropper">
			<?
				$x = 0;
				foreach ($crops as $crop) {
					$x++;
					list($width,$height,$type,$attr) = getimagesize($crop["image"]);
					$image = str_replace(SITE_ROOT,STATIC_ROOT,$crop["image"]);
					$cwidth = $crop["width"];
					$cheight = $crop["height"];
					
					$box_width = $width;
					$box_height = $height;
					
					if ($box_width > 420) {
						$box_height = ceil($box_height * 420 / $box_width);
						$box_width = 420;
					}
					
					$preview_width = $cwidth;
					$preview_height = $cheight;
					
					if ($preview_width > 420) {
						$preview_height = ceil($preview_height * 420 / $preview_width);
						$preview_width = 420;
					}
					
					$image_ratio = $box_width / $width;
					
					$min_width = ceil($cwidth * $image_ratio);
					$min_height = ceil($cheight * $image_ratio);
					
					if ($preview_height < $box_height) {
						$preview_margin = floor(($box_height - $preview_height) / 2);
						$box_margin = 0;
					} else {
						$box_margin = floor(($preview_height - $box_height) / 2);
						$preview_margin = 0;
					}
					
					// Fill the cropper to ~90% of the available area by default.
					if ($min_width > $min_height) {
						$initial_width = ceil($box_width * 0.90);
						$initial_height = ceil($initial_width / $min_width * $min_height);
					} else {
						$initial_height = ceil($box_height * 0.90);
						$initial_width = ceil($initial_height / $min_height * $min_width);
					}
					
					if (($initial_width < $min_width || $initial_height < $min_height) || ($crop["retina"] && ($initial_width < $min_width * 2 || $initial_height < $min_height * 2))) {
						// If we're doing a retina crop, make the initial crop area fit the retina version.
						if ($crop["retina"]) {
							$initial_width = $min_width * 2;
							$initial_height = $min_height * 2;
						} else {
							$initial_width = $min_width;
							$initial_height = $min_height;
						}
					}
					
					// Figure out where we're starting the cropping box (should be centered)
					$initial_x = ceil(($box_width - $initial_width) / 2);
					$initial_y = ceil(($box_height - $initial_height) / 2);
	
					// Figure out where the arrow should be
					$arrow_margin = 13 + ceil($box_height / 2);
			?>
			<article<? if ($x > 1) { ?> style="display: none;"<? } ?>>
				<div class="original">
					<img src="<?=$image?>" id="cropImage<?=$x?>" width="<?=$box_width?>" height="<?=$box_height?>" />
				</div>
				<input type="hidden" name="x[]" id="x<?=$x?>" />
				<input type="hidden" name="y[]" id="y<?=$x?>" />
				<input type="hidden" name="width[]" id="width<?=$x?>" />
				<input type="hidden" name="height[]" id="height<?=$x?>" />
				<script>
					$(document).ready(function() {
						$("#cropImage<?=$x?>").Jcrop({
							minSize: [<?=$min_width?>,<?=$min_height?>],
							aspectRatio: <?=($min_width / $min_height)?>,
							setSelect: [<?=$initial_x?>,<?=$initial_y?>,<?=($initial_x + $initial_width)?>,<?=($initial_y + $initial_height)?>],
							onSelect: BigTree.localSetCoords<?=$x?>,
							onChange: BigTree.localSetCoords<?=$x?>
						});
					});
					
					BigTree.localShowPreview<?=$x?> = function(coords) {
						rx = <?=$box_width?> / coords.w;
						ry = <?=$box_height?> / coords.h;
						bx = <?=$preview_width?> / coords.w;
						by = <?=$preview_height?> / coords.h;
					
						$("#x<?=$x?>").val(Math.round(coords.x * <?=($width/$box_width)?>));
						$("#y<?=$x?>").val(Math.round(coords.y * <?=($height/$box_height)?>));
						$("#width<?=$x?>").val(Math.round(coords.w * <?=($width/$box_width)?>));
						$("#height<?=$x?>").val(Math.round(coords.h * <?=($height/$box_height)?>));
					};
				</script>
			</article>
			<?
				}
			?>
		</section>
		<footer>
			<input type="submit" value="Crop Image" />			
		</footer>
	</form>
</div>
<script>
	window.parent.BigTreeEmbeddableForm.scrollToTop();
	BigTree.currentCrop = 1;
	BigTree.maxCrops = <?=count($crops)?>;
	
	$("#crop_form").submit(function() {
		if (BigTree.currentCrop != BigTree.maxCrops) {
			$("#cropper article").eq(BigTree.currentCrop - 1).hide();
			$("#cropper article").eq(BigTree.currentCrop).show();
			BigTree.currentCrop++;
			return false;
		}
	});
</script>