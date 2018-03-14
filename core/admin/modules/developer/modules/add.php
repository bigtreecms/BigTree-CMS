<?php
	$groups = $admin->getModuleGroups("name ASC");
	
	// Stop notices
	$gbp = array();
	$name = $route = $group_new = $group_existing = $table = $class = "";
	$icon = "gear";
	if (isset($_SESSION["bigtree_admin"]["saved"])) {
		BigTree::globalizeArray($_SESSION["bigtree_admin"]["saved"],"htmlspecialchars");
		unset($_SESSION["bigtree_admin"]["saved"]);
	}
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/create/" class="module">
		<?php $admin->drawCSRFToken() ?>
		<section>
			<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>
			<div class="contain">
				<div class="left">
					<fieldset>
						<label class="required">Name</label>
						<input name="name" class="required" type="text" value="<?=$name?>" />
					</fieldset>
				</div>
				<div class="right">
					<fieldset<?php if (isset($_GET["error"])) { ?> class="form_error"<?php } ?>>
						<label>Route <small>(must be unique, auto generated if left blank, valid chars: alphanumeric and "-")</small></label>
						<input name="route" type="text" value="<?=$route?>" />
					</fieldset>
				</div>
			</div>
			<fieldset class="developer_module_group">
				<label>Group <small>(if a new group name is chosen, the select box is ignored)</small></label> 
				<input name="group_new" type="text" placeholder="New Group" value="<?=$group_new?>" /><span>OR</span> 
				<select name="group_existing">
					<option value="0"></option>
					<?php foreach ($groups as $group) { ?>
					<option value="<?=$group["id"]?>"<?php if ($group_existing == $group["id"]) { ?> selected="selected"<?php } ?>><?=$group["name"]?></option>
					<?php } ?>
				</select>
			</fieldset>
			<div class="left">
				<fieldset>
					<label>Related Table</label>
					<select name="table" id="rel_table">
						<option></option>
						<?php BigTree::getTableSelectOptions($table) ?>
					</select>
				</fieldset>
				<fieldset>
					<label class="required">Class Name <small>(will create a class file in custom/inc/modules/)</small></label>
					<input name="class" type="text" value="<?=$class?>" />
				</fieldset>
			</div>
			
			<br class="clear" />
			<fieldset>
				<label class="required">Icon</label>
				<input type="hidden" name="icon" id="selected_icon" value="<?=$icon?>" />
				<ul class="developer_icon_list">
					<?php foreach (BigTreeAdmin::$IconClasses as $class) { ?>
					<li>
						<a href="#<?=$class?>"<?php if ($class == "gear") { ?> class="active"<?php } ?>><span class="icon_small icon_small_<?=$class?>"></span></a>
					</li>
					<?php } ?>
				</ul>
			</fieldset>

			<fieldset>
				<input type="checkbox" name="gbp[enabled]" id="gbp_on" <?php if (isset($gbp["enabled"]) && $gbp["enabled"]) { ?>checked="checked" <?php } ?>/>
				<label class="for_checkbox">Enable Advanced Permissions</label>
			</fieldset>
		</section>
		<?php include BigTree::path("admin/modules/developer/modules/_gbp.php"); ?>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>
<?php include BigTree::path("admin/modules/developer/modules/_js.php"); ?>