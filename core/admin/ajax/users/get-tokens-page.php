<?
	$admin->requireLevel(1);

	$query = isset($_GET["query"]) ? $_GET["query"] : "";
	$page = isset($_GET["page"]) ? $_GET["page"] : 0;

	$pages = $admin->getAPITokensPageCount($query);	
	$results = $admin->getPageOfAPITokens($page,$query);
	foreach ($results as $item) {
?>
<li id="row_<?=$item["id"]?>">
	<section class="tokens_name"><?=$item["user"]["name"]?></section>
	<section class="tokens_token"><?=$item["token"]?></section>
	<section class="tokens_type">
		<span class="icon_approve<? if ($item["read_only"]) { ?> icon_approve_on<? } ?>"></span>
	</section>
	<section class="view_action">
		<a href="<?=$admin_root?>users/tokens/edit/<?=$item["id"]?>/" class="icon_edit"></a>
	</section>
	<section class="view_action">
		<a href="#<?=$item["id"]?>" class="icon_delete"></a>
	</section>
</li>
<?
	}
?>
<script type="text/javascript">
	BigTree.SetPageCount("#view_paging",<?=$pages?>,<?=$page?>);
</script>