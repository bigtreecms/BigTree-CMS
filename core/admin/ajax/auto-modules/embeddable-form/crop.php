<?
	BigTree::globalizeArray($_SESSION["bigtree_admin"]["form_data"]);
?>
<div class="container">
	<form method="post" action="<?=$bigtree["form_root"]?>process-crops/?id=<?=$bigtree["form"]["id"]?>&hash=<?=$bigtree["form"]["hash"]?>" id="crop_form" class="module">
		<input type="hidden" name="return_page" value="<?=htmlspecialchars($return_link)?>" />
		<input type="hidden" name="crop_info" value="<?=htmlspecialchars(json_encode($crops))?>" />
		<section id="cropper">
			<?
				$x = 0;
				foreach ($crops as $crop) {
					$x++;
					list($width,$height,$type,$attr) = getimagesize($crop["image"]);
			?>
			<article<? if ($x > 1) { ?> style="display: none;"<? } ?>>
				<div class="original">
					<img src="<?=str_replace(SITE_ROOT,STATIC_ROOT,$crop["image"])?>" id="cropImage<?=$x?>" data-retina="<?=$crop["retina"]?>" data-width="<?=$width?>" data-height="<?=$height?>" data-crop-width="<?=$crop["width"]?>" data-crop-height="<?=$crop["height"]?>" alt="" />
				</div>
				<input type="hidden" name="x[]" id="x<?=$x?>" />
				<input type="hidden" name="y[]" id="y<?=$x?>" />
				<input type="hidden" name="width[]" id="width<?=$x?>" />
				<input type="hidden" name="height[]" id="height<?=$x?>" />
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
	BigTree.localInitJcrop = function() {
		if (BigTree.localJcropAPI) {
			BigTree.localJcropAPI.destroy();
		}
		var currentImage = $("#cropImage" + BigTree.localCurrentCrop);
		var retina = currentImage.attr("data-retina");
		var width = currentImage.attr("data-width");
		var height = currentImage.attr("data-height");
		var crop_width = currentImage.attr("data-crop-width");
		var crop_height = currentImage.attr("data-crop-height");
		var window_width = window.innerWidth;

		// Cap it at 600x600
		if (window_width > 600) {
			window_width = 600;
		}

		box_width = width;
		box_height = height;
		
		if (box_width > window_width) {
			box_height = Math.ceil(box_height * window_width / box_width);
			box_width = window_width;
		}
		
		preview_width = crop_width;
		preview_height = crop_height;
		
		if (preview_width > window_width) {
			preview_height = Math.ceil(preview_height * window_width / preview_width);
			preview_width = window_width;
		}
		
		image_ratio = box_width / width;
		
		min_width = Math.ceil(crop_width * image_ratio);
		min_height = Math.ceil(crop_height * image_ratio);
		
		if (preview_height < box_height) {
			preview_margin = Math.floor((box_height - preview_height) / 2);
			box_margin = 0;
		} else {
			box_margin = Math.floor((preview_height - box_height) / 2);
			preview_margin = 0;
		}
		
		// Fill the cropper to ~90% of the available area by default.
		if (min_width > min_height) {
			initial_width = Math.ceil(box_width * 0.90);
			initial_height = Math.ceil(initial_width / min_width * min_height);
		} else {
			initial_height = Math.ceil(box_height * 0.90);
			initial_width = Math.ceil(initial_height / min_height * min_width);
		}
		
		if ((initial_width < min_width || initial_height < min_height) || (retina && (initial_width < min_width * 2 || initial_height < min_height * 2))) {
			// If we're doing a retina crop, make the initial crop area fit the retina version.
			if (retina) {
				initial_width = min_width * 2;
				initial_height = min_height * 2;
			} else {
				initial_width = min_width;
				initial_height = min_height;
			}
		}
		
		// Figure out where we're starting the cropping box (should be centered)
		initial_x = Math.ceil((box_width - initial_width) / 2);
		initial_y = Math.ceil((box_height - initial_height) / 2);

		// Save our calculations
		BigTree.localCropInfo = { box_width: box_width, box_height: box_height, preview_width: preview_width, preview_height: preview_height, width: width, height: height };

		currentImage.width(box_width).height(box_height).Jcrop({
			minSize: [min_width,min_height],
			aspectRatio: (min_width / min_height),
			setSelect: [initial_x,initial_y,(initial_x + initial_width),(initial_y + initial_height)],
			onSelect: BigTree.localSetCoords,
			onChange: BigTree.localSetCoords
		},function() {
			BigTree.localJcropAPI = this;
		});
	};

	BigTree.localSetCoords = function(coords) {
		rx = BigTree.localCropInfo.box_width / coords.w;
		ry = BigTree.localCropInfo.box_height / coords.h;
		bx = BigTree.localCropInfo.preview_width / coords.w;
		by = BigTree.localCropInfo.preview_height / coords.h;
	
		$("#x" + BigTree.localCurrentCrop).val(Math.round(coords.x * (BigTree.localCropInfo.width / BigTree.localCropInfo.box_width)));
		$("#y" + BigTree.localCurrentCrop).val(Math.round(coords.y * (BigTree.localCropInfo.height / BigTree.localCropInfo.box_height)));
		$("#width" + BigTree.localCurrentCrop).val(Math.round(coords.w * (BigTree.localCropInfo.width / BigTree.localCropInfo.box_width)));
		$("#height" + BigTree.localCurrentCrop).val(Math.round(coords.h * (BigTree.localCropInfo.height / BigTree.localCropInfo.box_height)));
	};

	BigTree.localWindowResizeCheck = function() {
		w = window.innerWidth;
		if (w > 600) {
			w = 600;
		}
		if (w != BigTree.localWindowWidth) {
			BigTree.localWindowWidth = w;	
			BigTree.localInitJcrop();
		}
	};

	window.parent.BigTreeEmbeddableForm<?=$bigtree["form"]["id"]?>.scrollToTop();
	BigTree.localCurrentCrop = 1;
	BigTree.localMaxCrops = <?=count($crops)?>;
	BigTree.localWindowWidth = window.innerWidth;
	if (BigTree.localWindowWidth > 600) {
		BigTree.localWindowWidth = 600;
	}
	
	$("#crop_form").submit(function() {
		if (BigTree.localCurrentCrop != BigTree.localMaxCrops) {
			$("#cropper article").eq(BigTree.localCurrentCrop - 1).hide();
			$("#cropper article").eq(BigTree.localCurrentCrop).show();
			BigTree.localCurrentCrop++;
			BigTree.localInitJcrop();
			return false;
		}
	});

	setInterval(BigTree.localWindowResizeCheck,250);

	BigTree.localInitJcrop();
</script>