<?
	$file = $admin->getResourceByFile($_POST["file"]);
	$pinfo = BigTree::pathInfo($file["file"]);
?>
<? if ($file["is_image"]) { ?>
<div class="file_browser_detail_thumb">
	<img src="<?=$file["thumbs"]["bigtree_internal_detail"]?>" alt="" />
</div>
<? } ?>
<div class="file_browser_detail_title">
	<label>Title</label>
	<input type="text" name="<?=$file["id"]?>" id="file_browser_detail_title_input" value="<?=$file["name"]?>" />
</div>
<div class="file_browser_detail_list">
	<? if (!$file["is_image"]) { ?>
	<p><span>File Name</span><strong><?=$pinfo["basename"]?></strong></p>
	<? } ?>
	<p><span>File Type</span><strong><?=$pinfo["extension"]?></strong></p>
	<? if ($file["width"]) { ?>
	<p><span>Width</span><strong><?=$file["width"]?></strong></p>
	<? } ?>
	<? if ($file["height"]) { ?>
	<p><span>Height</span><strong><?=$file["height"]?></strong></p>
	<? } ?>
	<p><span>Uploaded</span><strong><?=date("n/j/y @ g:ia",strtotime($file["date"]))?></strong></p>
</div>