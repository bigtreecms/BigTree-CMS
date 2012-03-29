<?
	$module = $admin->getModule($commands[0]);
	$breadcrumb[] = array("title" => $module["name"], "link" => "developer/modules/edit/".$module["id"]."/");
	$breadcrumb[] = array("title" => "Add Action", "link" => "#");
?>
<h1><span class="icon_developer_modules"></span>Add Action</h1>
<? include BigTree::path("admin/modules/developer/modules/_nav.php"); ?>
<div class="form_container">
	<form method="post" action="<?=$developer_root?>modules/actions/create/<?=$module["id"]?>/" class="module">
		<section>
			<div class="left">
				<fieldset>
					<label class="required">Name</label>
					<input type="text" name="name" class="required" />
				</fieldset>
				<fieldset>
					<label>Route</label>
					<input type="text" name="route" />
				</fieldset>
			</div>
			<br class="clear" /><br />
			<fieldset>
				<label class="required">Image</label>
				<input type="hidden" name="class" id="selected_icon" />
				<ul class="developer_icon_list">
					<? foreach ($classes as $class) { ?>
					<li>
						<a href="#<?=$class?>"><span class="icon_small icon_small_<?=$class?>"></span></a>
					</li>
					<? } ?>
				</ul>
			</fieldset>
			<fieldset>
				<input type="checkbox" name="in_nav" checked="checked" />
				<label class="for_checkbox">In Navigation</label>
			</fieldset>
		</section>
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