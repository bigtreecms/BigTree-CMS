<?
	$module_title = "Edit Module Action";
	$item = $admin->getModuleAction(end($bigtree["commands"]));
	BigTree::globalizeArray($item);
	$module = $admin->getModule($module);
	$breadcrumb[] = array("title" => $module["name"], "link" => "developer/modules/edit/".$module["id"]."/");
	$breadcrumb[] = array("title" => "Edit Action", "link" => "#");
?>
<h1><span class="modules"></span>Edit Action</h1>
<? include BigTree::path("admin/modules/developer/modules/_nav.php"); ?>
<div class="form_container">
	<form method="post" action="<?=$developer_root?>modules/actions/update/<?=$item["id"]?>/" class="module">
		<input type="hidden" name="position" value="<?=$item["position"]?>" />
		<? include BigTree::path("admin/modules/developer/modules/actions/_form.php") ?>
		<footer>
			<input type="submit" class="button blue" value="Update" />
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