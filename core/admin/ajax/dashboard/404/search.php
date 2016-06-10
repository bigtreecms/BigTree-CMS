<?php
	namespace BigTree;

	/**
	 * @global string $type
	 */
	
	$type = isset($_POST["type"]) ? $_POST["type"] : $type;
	$page = isset($_POST["page"]) ? intval($_POST["page"]) : 1;
	$search = isset($_POST["search"]) ? $_POST["search"] : "";
	$tabindex = 0;

	list($pages, $items) = Redirect::search($type, $search, $page, true);

	foreach ($items as $item) {
		$tabindex++;
?>
<li>
	<section class="requests_404"><?=$item["requests"]?></section>
	<section class="url_404"><?=$item["broken_url"]?></section>
	<section class="redirect_404">
		<input type="text" tabindex="<?=$tabindex?>" name="<?=$item["id"]?>" id="404_<?=$item["id"]?>" class="autosave" value="<?=str_replace(WWW_ROOT,"",$item["redirect_url"])?>" />
	</section>
	<?php if ($type == "ignored") { ?>
	<section class="ignore_404"><a href="#<?=$item["id"]?>" class="icon_restore"></a></section>	
	<?php } else { ?>
	<section class="ignore_404"><a href="#<?=$item["id"]?>" class="icon_archive"></a></section>	
	<?php } ?>
	<section class="ignore_404"><a href="#<?=$item["id"]?>" class="icon_delete"></a></section>
</li>
<?php
	}
?>
<script>
	BigTree.setPageCount("#view_paging",<?=$pages?>,<?=$page?>);
</script>