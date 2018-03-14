<?php
	$groups = $admin->getModuleGroups();
?>
<div class="container">
	<header>
		<p>The module designer will guide you through making a module without needing access to the database or knowledge of database table creation.</p>
	</header>
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/designer/create/" enctype="multipart/form-data" class="module">
		<?php $admin->drawCSRFToken() ?>
		<section>
			<p class="error_message"<?php if (!count($e)) { ?> style="display: none;"<?php } ?>>Errors found! Please fix the highlighted fields before submitting.</p>
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
					<?php foreach ($groups as $group) { ?>
					<option value="<?=$group["id"]?>"<?php if ($group_existing == $group["id"]) { ?> selected="selected"<?php } ?>><?=$group["name"]?></option>
					<?php } ?>
				</select>
			</fieldset>
			<div class="left">
				<fieldset<?php if (isset($e["table"])) { ?> class="form_error"<?php } ?>>
					<label class="required">Table Name <small>(for example, my_site_news)</small><?php if (isset($e["table"])) { ?><span class="form_error_reason">Table Already Exists</span><?php } ?></label>
					<input name="table" class="required" type="text" value="<?=$table?>" />
				</fieldset>
				<fieldset<?php if (isset($e["class"])) { ?> class="form_error"<?php } ?>>
					<label class="required">Class Name <small>(for example, MySiteNews)</small><?php if (isset($e["class"])) { ?><span class="form_error_reason">Class Already Exists</span><?php } ?></label>
					<input name="class" class="required" type="text" value="<?=$class?>" />
				</fieldset>
			</div>
			<br class="clear" />
			<fieldset>
				<label class="required">Icon</label>
				<input type="hidden" name="icon" id="selected_icon" value="gear" />
				<ul class="developer_icon_list">
					<?php foreach (BigTreeAdmin::$IconClasses as $class) { ?>
					<li>
						<a href="#<?=$class?>"<?php if ($class == "gear") { ?> class="active"<?php } ?>><span class="icon_small icon_small_<?=$class?>"></span></a>
					</li>
					<?php } ?>
				</ul>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Continue" />
		</footer>
	</form>
</div>
<?php include BigTree::path("admin/modules/developer/modules/_js.php"); ?>