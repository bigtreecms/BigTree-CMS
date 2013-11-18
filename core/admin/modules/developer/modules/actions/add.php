<?
	$module = $admin->getModule(end($bigtree["commands"]));
	$item = array("name" => "", "route" => "", "level" => 0, "class" => "", "in_nav" => "");
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/actions/create/<?=$module["id"]?>/" class="module">
		<? include BigTree::path("admin/modules/developer/modules/actions/_form.php") ?>
		<footer>
			<input type="submit" class="button blue" value="Create" />
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