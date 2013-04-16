<script>
	BigTree.cropCount = <?=$cx?>;
	BigTree.thumbCount = <?=$tx?>;
	BigTree.cropThumbCount = <?=$ctx?>;

	BigTree.localAddCrop = function() {
		BigTree.cropCount++;
		ul = $('<ul>');
		
		li_pre = $('<li>');
		li_pre.html('<input type="text" name="crops[' + BigTree.cropCount + '][prefix]" value="" />');
		ul.append(li_pre);
		
		li_width = $('<li>');
		li_width.html('<input type="text" name="crops[' + BigTree.cropCount + '][width]" value="" />');
		ul.append(li_width);
		
		li_height = $('<li>');
		li_height.html('<input type="text" name="crops[' + BigTree.cropCount + '][height]" value="" />');
		ul.append(li_height);
		
		li_thumb = $('<li class="thumbnail">');
		li_thumb.html('<a href="#' + BigTree.cropCount + '" title="Create Thumbnail of Crop"></a>');
		ul.append(li_thumb);
		
		li_color = $('<li class="colormode">');
		li_color.html('<input type="hidden" name="crops[' + BigTree.cropCount + '][grayscale]" value="" /><a href="#" title="Switch Color Mode"></a>');
		ul.append(li_color);
		
		li_del = $('<li class="del">');
		li_del.html('<a href="#' + BigTree.cropCount + '" title="Remove"></a>');
		ul.append(li_del);
		
		$("#pop_crop_list").append(ul);
		
		return false;
	};

	BigTree.localAddThumb = function() {
		BigTree.thumbCount++;
		ul = $('<ul>');
		
		li_pre = $('<li>');
		li_pre.html('<input type="text" name="thumbs[' + BigTree.thumbCount + '][prefix]" value="" />');
		ul.append(li_pre);
		
		li_width = $('<li>');
		li_width.html('<input type="text" name="thumbs[' + BigTree.thumbCount + '][width]" value="" />');
		ul.append(li_width);
		
		li_height = $('<li>');
		li_height.html('<input type="text" name="thumbs[' + BigTree.thumbCount + '][height]" value="" />');
		ul.append(li_height);
		
		li_color = $('<li class="colormode">');
		li_color.html('<input type="hidden" name="thumbs[' + BigTree.thumbCount + '][grayscale]" value="" /><a href="#" title="Switch Color Mode"></a>');
		ul.append(li_color);
		
		li_del = $('<li class="del">');
		li_del.html('<a href="#"></a>');
		ul.append(li_del);
		
		$("#pop_thumb_list").append(ul);
		
		return false;
	}
	
	$(".image_attr").on("click",".del a",function() {
		count = $(this).attr("href").substr(1);
		$(".image_attr_thumbs_" + count).remove();
		$(this).parents("ul").remove();
		
		return false;
	});
	
	$(".image_attr").on("click",".thumbnail a",function() {
		count = $(this).attr("href").substr(1);
		BigTree.cropThumbCount++;
		
		ul = $('<ul class="image_attr_thumbs_' + count + '">');
		
		li_pre = $('<li class="thumbed">');
		li_pre.html('<span class="icon_small icon_small_picture"></span><input type="text" class="image_attr_thumbs" name="crops[' + count + '][thumbs][' + BigTree.cropThumbCount + '][prefix]" value="" />');
		ul.append(li_pre);
		
		li_width = $('<li>');
		li_width.html('<input type="text" name="crops[' + count + '][thumbs][' + BigTree.cropThumbCount + '][width]" value="" />');
		ul.append(li_width);
		
		li_height = $('<li>');
		li_height.html('<input type="text" name="crops[' + count + '][thumbs][' + BigTree.cropThumbCount + '][height]" value="" />');
		ul.append(li_height);
		
		li_up = $('<li class="up">');
		li_up.html('<span class="icon_small icon_small_up"></span>');
		ul.append(li_up);
		
		li_color = $('<li class="colormode">');
		li_color.html('<input type="hidden" name="crops[' + count + '][thumbs][' + BigTree.cropThumbCount + '][grayscale]" value="" /><a href="#" title="Switch Color Mode"></a>');
		ul.append(li_color);
		
		li_del = $('<li class="del">');
		li_del.html('<a href="#" title="Remove"></a>');
		ul.append(li_del);
	
		$(this).parents("ul").after(ul);
		
		return false;
	});
	
	$(".image_attr").on("click",".colormode a",function() {
		$(this).toggleClass("gray");
		if ($(this).hasClass("gray")) {
			$(this).prev("input").val("on");
		} else {
			$(this).prev("input").val("");			
		}
		
		return false;
	});

	$("#pop_crop_list input").on("keydown",function(e) {
		if (e.keyCode == 13) {
			BigTree.localAddCrop();
			return false;
		}
	});

	$(".add_crop").click(BigTree.localAddCrop);
	
	$("#pop_thumb_list input").on("keydown",function(e) {
		if (e.keyCode == 13) {
			BigTree.localAddThumb();
			return false;
		}
	});
	
	$(".add_thumb").click(BigTree.localAddThumb);
</script>