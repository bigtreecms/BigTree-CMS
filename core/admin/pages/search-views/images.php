<?php
	$mpage = ADMIN_ROOT.$module['route'].'/';
	BigTree::globalizeArray($view);
?>
<div class="table" id="" class="image_list">
	<summary><h2>Search Results</h2></summary>
	<?php if (isset($view['actions']['edit'])) {
    ?>
	<header>
		<span class="view_column">Click an image to edit it.</span>
	</header>
	<?php 
} ?>
	<section>
		<ul id="image_list_<?=$view['id']?>" class="image_list">
			<?php
				foreach ($items as $item) {
				    if ($options['preview_prefix']) {
				        $preview_image = BigTree::prefixFile($item[$options['image']], $options['preview_prefix']);
				    } else {
				        $preview_image = $item[$options['image']];
				    }
				    ?>
			<li id="row_<?=$item['id']?>">
				<a class="image<?php if (!isset($view['actions']['edit'])) {
    ?> image_disabled<?php 
}
				    ?>" href="<?=$view['edit_url'].$item['id']?>/"><img src="<?=$preview_image?>" alt="" /></a>
				<?php
					foreach ($actions as $action => $data) {
					    if ($action != 'edit') {
					        $class = $admin->getActionClass($action, $item);
					        $link = '#'.$item['id'];

					        if ($data != 'on') {
					            $data = json_decode($data, true);
					            $class = $data['class'];
					            $link = $mpage.$data['route'].'/'.$item['id'].'/';
					            if ($data['function']) {
					                $link = call_user_func($data['function'], $item);
					            }
					        }
					        ?>
				<a href="<?=$link?>" class="<?=$class?>"></a>
				<?php

					    }
					}
				    ?>
			</li>
			<?php

				}
			?>
		</ul>
	</section>
</div>
<script>	
	$("#image_list_<?=$view['id']?> .icon_delete").click(function() {
		BigTreeDialog({
			title: "Delete Item",
			content: '<p class="confirm">Are you sure you want to delete this item?</p>',
			icon: "delete",
			alternateSaveText: "OK",
			callback: $.proxy(function() {
				$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/delete/?view=<?=$view['id']?>&id=" + $(this).attr("href").substr(1));
				$(this).parents("li").remove();
			},this)
		});
		
		return false;
	});
	
	$("#image_list_<?=$view['id']?> .icon_approve").click(function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/approve/?view=<?=$view['id']?>&id=" + $(this).attr("href").substr(1));
		$(this).toggleClass("icon_approve_on");
		return false;
	});
	
	$("#image_list_<?=$view['id']?> .icon_feature").click(function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/feature/?view=<?=$view['id']?>&id=" + $(this).attr("href").substr(1));
		$(this).toggleClass("icon_feature_on");
		return false;
	});
	
	$("#image_list_<?=$view['id']?> .icon_archive").click(function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/archive/?view=<?=$view['id']?>&id=" + $(this).attr("href").substr(1));
		$(this).toggleClass("icon_archive_on");
		return false;
	});
	
	$("#image_list_<?=$view['id']?> img").load(function() {
		var w = $(this).width();
		var h = $(this).height();
		if (w > h) {
			var perc = 108 / w;
			h = perc * h;
			var style = { margin: Math.floor((108 - h) / 2) + "px 0 0 0" };
		} else {
			var perc = 108 / h;
			w = perc * w;
			var style = { margin: "0 0 0 " + Math.floor((108 - w) / 2) + "px" };
		}
		
		$(this).css(style);
	});

	// Stop disabled edit action from working.
	$(".image_list a.image_disabled").click(function() {
		return false;
	});
</script>