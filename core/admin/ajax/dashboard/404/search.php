<?
	$type = isset($_POST["type"]) ? $_POST["type"] : $type;
	$page = isset($_POST["page"]) ? $_POST["page"] : 1;
	$search = isset($_POST["search"]) ? $_POST["search"] : "";
	
	list($pages,$items) = $admin->search404s($type,$search,$page);

	$tabindex = 0;
	foreach ($items as $item) {
		$tabindex++;
?>
<li>
	<section class="requests_404"><?=$item["requests"]?></section>
	<section class="url_404"><?=$item["broken_url"]?></section>
	<section class="redirect_404">
		<input type="text" tabindex="<?=$tabindex?>" name="<?=$item["id"]?>" id="404_<?=$item["id"]?>" class="autosave" value="<?=$item["redirect_url"]?>" />
	</section>
	<? if ($type == "ignored") { ?>
	<section class="ignore_404"><a href="#<?=$item["id"]?>" class="icon_restore"></a></section>	
	<? } else { ?>
	<section class="ignore_404"><a href="#<?=$item["id"]?>" class="icon_archive"></a></section>	
	<? } ?>
	<section class="ignore_404"><a href="#<?=$item["id"]?>" class="icon_delete"></a></section>
</li>
<?
	}
?>
<script>
	BigTree.SetPageCount("#view_paging",<?=$pages?>,<?=$page?>);
</script>