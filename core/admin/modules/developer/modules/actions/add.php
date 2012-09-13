<?
	$module = $admin->getModule(end($bigtree["commands"]));
	$breadcrumb[] = array("title" => $module["name"], "link" => "developer/modules/edit/".$module["id"]."/");
	$breadcrumb[] = array("title" => "Add Action", "link" => "#");
	
	$item = array("name" => "", "route" => "", "level" => 0, "class" => "", "in_nav" => "");
?>
<h1><span class="modules"></span>Add Action</h1>
<? include BigTree::path("admin/modules/developer/modules/_nav.php"); ?>
<div class="form_container">
	<form method="post" action="<?=$developer_root?>modules/actions/create/<?=$module["id"]?>/" class="module">
		<? include BigTree::path("admin/modules/developer/modules/actions/_form.php") ?>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>

<script type="text/javascript">
	$(".developer_icon_list a").click(function() {
		$(".developer_icon_list a").removeClass("active");
		$(this).addClass("active");
		$("#selected_icon").val($(this).attr("href").substr(1));
		
		return false;
	});
</script>