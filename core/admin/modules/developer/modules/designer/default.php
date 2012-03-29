<?
	$groups = $admin->getModuleGroups();
?>
<h1><span class="icon_developer_modules"></span>Module Designer</h1>
<? include BigTree::path("admin/modules/developer/modules/_nav.php"); ?>
<div class="form_container">
	<header>
		<p>The module designer will guide you through making a module without needing access to the database or knowledge of database table creation.</p>
	</header>
	<form method="post" action="<?=$developer_root?>modules/designer/create/" enctype="multipart/form-data" class="module">
		<section>
			<p class="error_message"<? if (!count($e)) { ?> style="display: none;"<? } ?>>Errors found! Please fix the highlighted fields before submitting.</p>
			<div class="left">
				<fieldset>
					<label class="required">Module Name <small>(for example, News)</small></label>
					<input name="name" class="required" type="text" value="<?=$name?>" />
				</fieldset>
			</div>
			<br class="clear" /><br />
			<fieldset class="clear developer_module_group">
				<label>Module Group <small>(if a new group name is chosen, the select box is ignored)</small></label> 
				<input name="group_new" type="text" placeholder="New Group" value="<?=$group_new?>" />
				<span>OR</span>
				<select name="group_existing">
					<option value="0"></option>
					<? foreach ($groups as $group) { ?>
					<option value="<?=$group["id"]?>"<? if ($group_existing == $group["id"]) { ?> selected="selected"<? } ?>><?=htmlspecialchars($group["name"])?></option>
					<? } ?>
				</select>
			</fieldset>
			<div class="left">
				<fieldset<? if ($e["table"]) { ?> class="form_error"<? } ?>>
					<label class="required">Table Name <small>(for example, my_site_news)</small><? if ($e["table"]) { ?><span class="form_error_reason">Table Already Exists</span><? } ?></label>
					<input name="table" class="required" type="text" value="<?=$table?>" />
				</fieldset>
				<fieldset<? if ($e["class"]) { ?> class="form_error"<? } ?>>
					<label class="required">Class Name <small>(for example, MySiteNews)</small><? if ($e["class"]) { ?><span class="form_error_reason">Class Already Exists</span><? } ?></label>
					<input name="class" class="required" type="text" value="<?=$class?>" />
				</fieldset>
			</div>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Continue" />
		</footer>
	</form>
</div>
<script type="text/javascript">
	new BigTreeFormValidator("form.module");
</script>