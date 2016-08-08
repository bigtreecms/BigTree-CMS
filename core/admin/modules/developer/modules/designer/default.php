<?php
	namespace BigTree;
	
	$groups = ModuleGroup::all("name ASC");
	
	if (!empty($_SESSION["developer"]["saved_module"])) {
		$saved = $_SESSION["developer"]["saved_module"];
		unset($_SESSION["developer"]["saved_module"]);
		
		$module = new Module($saved);
		$errors = $_SESSION["developer"]["designer_errors"];
		$group_new = Text::htmlEncode($saved["group_new"]);
		$group_existing = $saved["group_existing"];
		$table = Text::htmlEncode($saved["table"]);
	} else {
		$module = new Module;
		$module->Icon = "gear";
		$errors = array();
		$group_new = "";
		$group_existing = false;
		$table = "";
	}
?>
<div class="container">
	<header>
		<p><?=Text::translate("The module designer will guide you through making a module without needing access to the database or knowledge of database table creation.")?></p>
	</header>
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/designer/create/" enctype="multipart/form-data" class="module">
		<section>
			<p class="error_message"<?php if (!count($errors)) { ?> style="display: none;"<?php } ?>><?=Text::translate("Errors found! Please fix the highlighted fields before submitting.")?></p>
			<div class="left">
				<fieldset>
					<label for="module_field_name" class="required"><?=Text::translate("Module Name <small>(for example, News)</small>")?></label>
					<input id="module_field_name" name="name" class="required" type="text" value="<?=Text::htmlEncode($module->Name)?>" />
				</fieldset>
			</div>
			<br class="clear" /><br />
			<fieldset class="clear developer_module_group">
				<label for="module_field_group"><?=Text::translate("Module Group <small>(if a new group name is chosen, the select box is ignored)</small>")?></label>
				<input id="module_field_group" name="group_new" type="text" placeholder="<?=Text::translate("New Group", true)?>" value="<?=$group_new?>" />
				<span><?=Text::translate("OR")?></span>
				<label for="module_field_group_existing" class="visually_hidden">Existing Group</label>
				<select id="module_field_group_existing" name="group_existing">
					<option value="0"></option>
					<?php foreach ($groups as $group) { ?>
					<option value="<?=$group->ID?>"<?php if ($group_existing == $group["id"]) { ?> selected="selected"<?php } ?>><?=$group["name"]?></option>
					<?php } ?>
				</select>
			</fieldset>
			<div class="left">
				<fieldset<?php if (isset($e["table"])) { ?> class="form_error"<?php } ?>>
					<label for="module_field_table" class="required">Table Name <small>(for example, my_site_news)</small><?php if (isset($e["table"])) { ?><span class="form_error_reason">Table Already Exists</span><?php } ?></label>
					<input id="module_field_table" name="table" class="required" type="text" value="<?=$table?>" />
				</fieldset>
				<fieldset<?php if (isset($e["class"])) { ?> class="form_error"<?php } ?>>
					<label for="module_field_class" class="required">Class Name <small>(for example, MySiteNews)</small><?php if (isset($e["class"])) { ?><span class="form_error_reason">Class Already Exists</span><?php } ?></label>
					<input id="module_field_class" name="class" class="required" type="text" value="<?=$module->Class?>" />
				</fieldset>
			</div>
			<br class="clear" />
			<fieldset>
		        <label class="required"><?=Text::translate("Icon")?></label>
		        <input type="hidden" name="icon" id="selected_icon" value="gear" />
		        <ul class="developer_icon_list">
		        	<?php foreach (Module::$IconClasses as $class) { ?>
		        	<li>
		        		<a href="#<?=$class?>"<?php if ($class == $module->Icon) { ?> class="active"<?php } ?>><span class="icon_small icon_small_<?=$class?>"></span></a>
		        	</li>
		        	<?php } ?>
		        </ul>
		    </fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Continue", true)?>" />
		</footer>
	</form>
</div>
<?php include Router::getIncludePath("admin/modules/developer/modules/_js.php") ?>