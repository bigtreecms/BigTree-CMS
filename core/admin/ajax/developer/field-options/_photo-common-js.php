<script type="text/javascript">
	var crop_count = <?=$cx?>;
	var thumb_count = <?=$tx?>;
	var crop_thumb_count = <?=$ctx?>;
	
	$(".image_attr").on("click",".del a",function() {
		count = $(this).attr("href").substr(1);
		$(".image_attr_thumbs_" + count).remove();
		$(this).parents("ul").remove();
		
		return false;
	});
	
	$(".image_attr").on("click",".thumbnail a",function() {
		count = $(this).attr("href").substr(1);
		crop_thumb_count++;
		
		ul = $('<ul class="image_attr_thumbs_' + count + '">');
		
		li_pre = $('<li>');
		li_pre.html('<input type="text" class="image_attr_thumbs" name="crops[' + count + '][thumbs][' + crop_thumb_count + '][prefix]" value="" />');
		ul.append(li_pre);
		
		li_width = $('<li>');
		li_width.html('<input type="text" name="crops[' + count + '][thumbs][' + crop_thumb_count + '][width]" value="" />');
		ul.append(li_width);
		
		li_height = $('<li>');
		li_height.html('<input type="text" name="crops[' + count + '][thumbs][' + crop_thumb_count + '][height]" value="" />');
		ul.append(li_height);
		
		li_up = $('<li class="up">');
		ul.append(li_up);
		
		li_del = $('<li class="del">');
		li_del.html('<a href="#"></a>');
		ul.append(li_del);
	
		$(this).parents("ul").after(ul);
		
		return false;
	});

	$("#pop_crop_list input").on("keydown",function(e) {
		if (e.keyCode == 13) {
			_local_addCrop();
			return false;
		}
	});

	$(".add_crop").click(_local_addCrop);
	
	function _local_addCrop() {
		crop_count++;
		ul = $('<ul>');
		
		li_pre = $('<li>');
		li_pre.html('<input type="text" name="crops[' + crop_count + '][prefix]" value="" />');
		ul.append(li_pre);
		
		li_width = $('<li>');
		li_width.html('<input type="text" name="crops[' + crop_count + '][width]" value="" />');
		ul.append(li_width);
		
		li_height = $('<li>');
		li_height.html('<input type="text" name="crops[' + crop_count + '][height]" value="" />');
		ul.append(li_height);
		
		li_thumb = $('<li class="thumbnail">');
		li_thumb.html('<a href="#' + crop_count + '"></a>');
		ul.append(li_thumb);
		
		li_del = $('<li class="del">');
		li_del.html('<a href="#' + crop_count + '"></a>');
		ul.append(li_del);
		
		$("#pop_crop_list").append(ul);
		
		return false;
	}
	
	$("#pop_thumb_list input").on("keydown",function(e) {
		if (e.keyCode == 13) {
			_local_addThumb();
			return false;
		}
	});
	
	$(".add_thumb").click(_local_addThumb);
	
	function _local_addThumb() {
		thumb_count++;
		ul = $('<ul>');
		
		li_pre = $('<li>');
		li_pre.html('<input type="text" name="thumbs[' + thumb_count + '][prefix]" value="" />');
		ul.append(li_pre);
		
		li_width = $('<li>');
		li_width.html('<input type="text" name="thumbs[' + thumb_count + '][width]" value="" />');
		ul.append(li_width);
		
		li_height = $('<li>');
		li_height.html('<input type="text" name="thumbs[' + thumb_count + '][height]" value="" />');
		ul.append(li_height);
		
		li_del = $('<li class="del">');
		li_del.html('<a href="#"></a>');
		ul.append(li_del);
		
		$("#pop_thumb_list").append(ul);
		
		return false;
	}
</script>