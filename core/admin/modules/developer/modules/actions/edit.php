<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$action = new ModuleAction(end($bigtree["commands"]));
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/actions/update/<?=$action->ID?>/" class="module">
		<input type="hidden" name="position" value="<?=$action->Position?>" />
		<?php include Router::getIncludePath("admin/modules/developer/modules/actions/_form.php") ?>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />
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