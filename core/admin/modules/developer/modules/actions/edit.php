<?
	$item = $admin->getModuleAction(end($bigtree["commands"]));
	BigTree::globalizeArray($item);
	$module = $admin->getModule($module);
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/actions/update/<?=$item["id"]?>/" class="module">
		<input type="hidden" name="position" value="<?=$item["position"]?>" />
		<? include BigTree::path("admin/modules/developer/modules/actions/_form.php") ?>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>

<script>
	$(".developer_icon_list a").click(function() {
		$(".developer_icon_list a").removeClass("active");
		$(this).addClass("active");
		$("#selected_icon").val($(this).attr("href").substr(1));
		
		return false;
	});
</script>