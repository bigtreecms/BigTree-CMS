<?
	$admin->requireLevel(1);
	
	$query = isset($_GET["query"]) ? $_GET["query"] : "";
	$page = isset($_GET["page"]) ? $_GET["page"] : 1;

	$pages = $admin->getSettingsPageCount($query);
	$results = $admin->getPageOfSettings($page,$query);
	
	foreach ($results as $item) {
		if (is_array($item["value"])) {
			$value = "&mdash; Click Edit To View &mdash;";
		} else {
			$value = BigTree::trimLength(strip_tags($item["value"]),100);
		}
?>
<li>
	<section class="settings_name"><?=$item["name"]?></section>
	<section class="settings_value"><?=$value?></section>
	<section class="view_action"><a href="<?=ADMIN_ROOT?>settings/edit/<?=$item["id"]?>/" class="icon_edit"></a></section>
</li>
<?
	}
?>
<script>
	BigTree.SetPageCount("#view_paging",<?=$pages?>,<?=$page?>);
</script>