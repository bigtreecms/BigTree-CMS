<?php
	namespace BigTree;
	
	$action = new ModuleAction;
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/actions/create/<?=htmlspecialchars($_GET["module"])?>/" class="module">
		<?php include Router::getIncludePath("admin/modules/developer/modules/actions/_form.php") ?>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Create", true)?>" />
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