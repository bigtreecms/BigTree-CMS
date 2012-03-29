<?
	$item = $admin->getModuleAction($commands[0]);
	BigTree::globalizeArray($item);
	$module = $admin->getModule($module);
	$breadcrumb[] = array("title" => $module["name"], "link" => "developer/modules/edit/".$module["id"]."/");
	$breadcrumb[] = array("title" => "Edit Action", "link" => "#");
?>
<h1><span class="icon_developer_modules"></span>Edit Action</h1>
<? include BigTree::path("admin/modules/developer/modules/_nav.php"); ?>
<div class="form_container">
	<form method="post" action="<?=$developer_root?>modules/actions/update/<?=$item["id"]?>/" class="module">
		<section>
			<fieldset>
				<label class="required">Name</label>
				<input type="text" name="name" class="required" value="<?=$item["name"]?>" />
			</fieldset>
			<fieldset>
				<label>Route</label>
				<input type="text" name="route" value="<?=$item["route"]?>" />
			</fieldset>
			<fieldset>
				<label class="required">Image</label>
				<input type="hidden" name="class" id="selected_icon" value="<?=$item["class"]?>" />
				<ul class="developer_icon_list">
					<? foreach ($classes as $class) { ?>
					<li>
						<a href="#<?=$class?>"<? if ($class == $item["class"]) { ?> class="active"<? } ?>><span class="icon_small icon_small_<?=$class?>"></span></a>
					</li>
					<? } ?>
				</ul>
			</fieldset>
			<fieldset>
				<label>In Navigation</label>
				<input type="checkbox" name="in_nav" <? if ($item["in_nav"]) { ?>checked="checked" <? } ?>/>
			</fieldset>
		</section>
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