<?
	$bigtree["js"][] = "jcrop.min.js";
?>
<h2>Crop Images</h2>
<form class="bigtree_dialog_form" method="post" action="<?=ADMIN_ROOT?>pages/process-crops/">
	<div class="overflow">
		<p>You have <?=count($bigtree["crops"])?> image<? if (count($bigtree["crops"]) > 1) { ?>s<? } ?> that need<? if (count($bigtree["crops"]) == 1) { ?>s<? } ?> to be cropped.</p>
		<input type="hidden" name="return_page" value="<?=ADMIN_ROOT?>pages/front-end-return/<?=base64_encode($refresh_link)?>/" />
		<input type="hidden" name="crop_info" value="<?=htmlspecialchars(json_encode($bigtree["crops"]))?>" />
		<section id="cropper">
			<?
				$x = 0;
				foreach ($bigtree["crops"] as $crop) {
					$x++;
					list($width,$height,$type,$attr) = getimagesize($crop["image"]);
					$image = str_replace(SITE_ROOT,WWW_ROOT,$crop["image"]);
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
					
					$initial_x = ceil(($box_width - $min_width) / 2);
					$initial_y = ceil(($box_height - $min_height) / 2);
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
							setSelect: [<?=$initial_x?>,<?=$initial_y?>,<?=($initial_x+$min_width)?>,<?=($initial_y+$min_height)?>],
							onSelect: BigTree.localShowPreview<?=$x?>,
							onChange: BigTree.localShowPreview<?=$x?>
						});
					});
					
					BigTree.localShowPreview<?=$x?> = function(coords) {
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
	</div>
	<footer>
		<input type="submit" class="blue" value="Crop Image" />			
	</footer>
</form>
<script>
	BigTree.localCurrentCrop = 1;
	BigTree.localMaxCrops = <?=count($bigtree["crops"])?>;
	
	$("#crop_form").submit(function() {
		if (BigTree.localCurrentCrop != BigTree.localMaxCrops) {
			$("#cropper article").eq(BigTree.localCurrentCrop - 1).hide();
			$("#cropper article").eq(BigTree.localCurrentCrop).show();
			BigTree.localCurrentCrop++;
			return false;
		}
	});
</script>