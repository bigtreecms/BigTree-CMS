<script src="<?=$admin_root?>js/jcrop.min.js" type="text/javascript"></script>
<h1><span class="crop"></span>Crop Images</h1>
<div class="form_container">
	<header>
		<p>You have <?=count($crops)?> image<? if (count($crops) > 1) { ?>s<? } ?> that need<? if (count($crops) == 1) { ?>s<? } ?> to be cropped.</p>
	</header>
	<form method="post" action="<?=$admin_root?>pages/process-crops/" id="crop_form" class="module">
		<input type="hidden" name="retpage" value="<?=htmlspecialchars($retpage)?>" />
		<input type="hidden" name="crop_info" value="<?=htmlspecialchars(json_encode($crops))?>" />
		<section class="cropper">
			<ul id="cropper">
				<?
					$x = 0;
					foreach ($crops as $crop) {
						$x++;
						list($width,$height,$type,$attr) = getimagesize($crop["image"]);
						$image = str_replace($site_root,$www_root,$crop["image"]);
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
				<li<? if ($x > 1) { ?> style="display: none;"<? } ?>>
					<div class="original">
						<p>Original</p>
						<img src="<?=$image?>" id="cropImage<?=$x?>" width="<?=$box_width?>" height="<?=$box_height?>" />
					</div>			
					<div class="cropped">
						<p>Cropped (<?=$cwidth?>x<?=$cheight?>)</p>
						<div style="padding-top: <?=$preview_margin?>px;">
							<div id="preview_<?=$x?>" style="width: <?=$preview_width?>px; height: <?=$preview_height?>px; overflow: hidden;">
								<img src="<?=$image?>" alt="" />
							</div>
						</div>
					</div>
					<input type="hidden" name="x[]" id="x<?=$x?>" />
					<input type="hidden" name="y[]" id="y<?=$x?>" />
					<input type="hidden" name="width[]" id="width<?=$x?>" />
					<input type="hidden" name="height[]" id="height<?=$x?>" />
					<script type="text/javascript">
						$(document).ready(function() {
							$("#cropImage<?=$x?>").Jcrop({
								minSize: [<?=$min_width?>,<?=$min_height?>],
								aspectRatio: <?=($min_width / $min_height)?>,
								setSelect: [<?=$initial_x?>,<?=$initial_y?>,<?=($initial_x+$min_width)?>,<?=($initial_y+$min_height)?>],
								onSelect: _local_showPreview<?=$x?>,
								onChange: _local_showPreview<?=$x?>
							});
						});
						
						function _local_showPreview<?=$x?>(coords) {
							var rx = <?=$box_width?> / coords.w;
							var ry = <?=$box_height?> / coords.h;
							var bx = <?=$preview_width?> / coords.w;
							var by = <?=$preview_height?> / coords.h;
						
							$("#preview_<?=$x?> img").css({
								width: Math.round(rx * <?=$preview_width?>) + 'px',
								height: Math.round(ry * <?=$preview_height?>) + 'px',
								marginLeft: '-' + Math.round(bx * coords.x) + 'px',
								marginTop: '-' + Math.round(by * coords.y) + 'px'
							});
							
							$("#x<?=$x?>").val(Math.round(coords.x * <?=($width/$box_width)?>));
							$("#y<?=$x?>").val(Math.round(coords.y * <?=($height/$box_height)?>));
							$("#width<?=$x?>").val(Math.round(coords.w * <?=($width/$box_width)?>));
							$("#height<?=$x?>").val(Math.round(coords.h * <?=($height/$box_height)?>));
						}
					</script>
				</li>
			<?
				}
			?>
			</ul>
		</section>
		<footer>
			<input type="submit" class="blue" value="Crop Image" />			
		</footer>
	</form>
</div>
<script type="text/javascript">
	var current = 1;
	var max = <?=count($crops)?>;
	
	$("#crop_form").submit(function() {
		if (current != max) {
			$("#cropper li").eq(current-1).hide();
			$("#cropper li").eq(current).show();
			current++;
			return false;
		}
	});
</script>