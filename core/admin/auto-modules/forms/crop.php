<?
	BigTree::globalizeArray($_SESSION["bigtree_admin"]["form_data"]);
	// Load the cropper Javascript.
	$bigtree["js"][] = "jcrop.min.js";
	// Override the default H1
	$bigtree["page_override"] = array("title" => "Crop Images","icon" => "crop");
?>
<div class="container">
	<header>
		<h2 class="cropper"><span>Cropping Image</span> <span class="count current">1</span> <span>of</span> <span class="count total"><?=count($crops)?></span></h2>
	</header>
	<form method="post" action="<?=$bigtree["form_root"]?>process-crops/<? if (is_array($page)) { echo $page["id"]."/"; } else if (is_numeric($page)) { echo $page; } ?>" id="crop_form" class="module">
		<input type="hidden" name="return_page" value="<?=htmlspecialchars($return_link)?>" />
		<input type="hidden" name="crop_info" value="<?=htmlspecialchars(json_encode($crops))?>" />
		<section class="cropper">
			<ul id="cropper">
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
												
						// If we're doing a retina crop, make the initial crop area fit the retina version.
						if ($crop["retina"] && $width >= $cwidth * 2 && $height >= $cheight * 2) {
							$initial_width = $min_width * 2;
							$initial_height = $min_height * 2;
						} else {
							$initial_width = $min_width;
							$initial_height = $min_height;
						}
						
						// Figure out where we're starting the cropping box (should be centered)
						$initial_x = ceil(($box_width - $initial_width) / 2);
						$initial_y = ceil(($box_height - $initial_height) / 2);
						
						// Figure out where the arrow should be
						$arrow_margin = 13 + ceil($box_height / 2);
				?>
				<li<? if ($x > 1) { ?> style="display: none;"<? } ?>>
					<div class="original">
						<p>Original</p>
						<img src="<?=$image?>" id="cropImage<?=$x?>" width="<?=$box_width?>" height="<?=$box_height?>" />
					</div>
					<div class="crop_arrow" style="margin-top: <?=$arrow_margin?>px;"></div>
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
					<script>
						$(document).ready(function() {
							$("#cropImage<?=$x?>").Jcrop({
								minSize: [<?=$min_width?>,<?=$min_height?>],
								aspectRatio: <?=($min_width / $min_height)?>,
								setSelect: [<?=$initial_x?>,<?=$initial_y?>,<?=($initial_x + $initial_width)?>,<?=($initial_y + $initial_height)?>],
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
<script>
	var current = 1;
	var max = <?=count($crops)?>;
	
	$("#crop_form").submit(function() {
		if (current != max) {
			$("#cropper li").eq(current-1).hide();
			$("#cropper li").eq(current).show();
			current++;
			$("h2.cropper .current").html(current);
			return false;
		}
	});
</script>