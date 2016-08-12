<?php
	namespace BigTree;
	
	$resource = Resource::getByFile($_POST["file"]);
	$file = $resource->Array;
	$pinfo = pathinfo($file["file"]);
	
	// We throw on ?uniqid so that we don't cache the thumbnail in the event that we just replaced it
	if ($file["is_image"]) {
?>
<div class="file_browser_detail_thumb">
	<img src="<?=$file["thumbs"]["bigtree_internal_detail"].($_COOKIE["bigtree_admin"]["recently_replaced_file"] ? "?".uniqid() : "")?>" alt="" />
</div>
<?php
	}
?>
<div class="file_browser_detail_title">
	<label for="file_browser_detail_title_input">Title</label>
	<input type="text" name="<?=$file["id"]?>" id="file_browser_detail_title_input" value="<?=$file["name"]?>" />
</div>
<div class="file_browser_detail_list">
	<?php if (!$file["is_image"]) { ?>
	<p><span>File Name</span><strong><?=$pinfo["basename"]?></strong></p>
	<?php } ?>
	<p><span>File Type</span><strong><?=$pinfo["extension"]?></strong></p>
	<?php if ($file["width"]) { ?>
	<p><span>Width</span><strong><?=$file["width"]?></strong></p>
	<?php } ?>
	<?php if ($file["height"]) { ?>
	<p><span>Height</span><strong><?=$file["height"]?></strong></p>
	<?php } ?>
	<p><span>Uploaded</span><strong><?=date("n/j/y @ g:ia",strtotime($file["date"]))?></strong></p>
</div>
<?php
	if (Auth::user()->Level) {
?>
<div class="file_browser_detail_actions">
	<a href="#" data-replace="<?=$file["id"]?>" class="button replace">Replace</a>
	<a href="#" data-allocation="<?=$resource->AllocationCount?>" class="button delete red">Delete</a>
</div>
<?php
	}
?>