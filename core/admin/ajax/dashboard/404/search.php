<?
	$type = $_POST["type"] ? $_POST["type"] : $type;
	$items = $admin->search404s($type,$_POST["search"]);

	$tabindex = 0;
	foreach ($items as $item) {
		$tabindex++;
?>
<li>
	<section class="requests_404"><?=$item["requests"]?></section>
	<section class="url_404"><?=$item["broken_url"]?></section>
	<section class="redirect_404">
		<input type="text" tabindex="<?=$tabindex?>" name="<?=$item["id"]?>" id="404_<?=$item["id"]?>" class="autosave" value="<?=htmlspecialchars($item["redirect_url"])?>" />
	</section>
	<section class="ignore_404"><a href="#<?=$item["id"]?>" class="icon_delete"></a></section>
</li>
<?
	}
?>