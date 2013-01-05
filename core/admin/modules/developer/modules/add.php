<?
	$groups = $admin->getModuleGroups();
	
	// Stop notices
	$gbp = array();
?>
<div class="container">
	<form method="post" action="<?=$section_root?>create/" class="module">
		<section>
			<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>
			<div class="left">
				<fieldset>
					<label class="required">Name</label>
					<input name="name" class="required" type="text" />
				</fieldset>
			</div>
			<br class="clear" /><br />
			<fieldset class="developer_module_group">
				<label>Group <small>(if a new group name is chosen, the select box is ignored)</small></label> 
				<input name="group_new" type="text" placeholder="New Group" /><span>OR</span> 
				<select name="group_existing">
					<option value="0"></option>
					<? foreach ($groups as $group) { ?>
					<option value="<?=$group["id"]?>"><?=htmlspecialchars($group["name"])?></option>
					<? } ?>
				</select>
			</fieldset>
			<div class="left">
				<fieldset>
					<label>Related Table</label>
					<select name="table" id="rel_table">
						<option></option>
						<? BigTree::getTableSelectOptions($table) ?>
					</select>
				</fieldset>
				<fieldset>
					<label class="required">Class Name <small>(will create a class file in custom/inc/modules/)</small></label>
					<input name="class" type="text" />
				</fieldset>
			</div>
			
			<br class="clear" />
			<fieldset>
		        <label class="required">Icon</label>
		        <input type="hidden" name="icon" id="selected_icon" value="gear" />
		        <ul class="developer_icon_list">
		        	<? foreach ($admin->IconClasses as $class) { ?>
		        	<li>
		        		<a href="#<?=$class?>"<? if ($class == "gear") { ?> class="active"<? } ?>><span class="icon_small icon_small_<?=$class?>"></span></a>
		        	</li>
		        	<? } ?>
		        </ul>
		    </fieldset>

			<fieldset>
			    <input type="checkbox" name="gbp[enabled]" id="gbp_on" />
			    <label class="for_checkbox">Enable Advanced Permissions <small>(allows setting permissions on grouped views)</small></label>
			</fieldset>
		</section>
		<section class="sub" id="gbp" style="display: none;">
			<div class="left">
				<fieldset>
					<label>Grouping Name <small>(i.e. "Category")</small></label>
					<input type="text" name="gbp[name]" />
				</fieldset>
			</div>
			<br class="clear" />
			<article>
				<fieldset>
					<label>Main Table</label>
					<select name="gbp[table]" class="table_select">
						<option></option>
						<? BigTree::getTableSelectOptions() ?>
					</select>
				</fieldset>
				<fieldset name="gbp[group_field]">
					<label>Main Field</label>
					<div>&mdash;</div>
				</fieldset>
			</article>
			<article>
				<fieldset>
					<label>Other Table</label>
					<select name="gbp[other_table]" class="table_select">
						<option></option>
						<? BigTree::getTableSelectOptions() ?>
					</select>
				</fieldset>
				<fieldset name="gbp[title_field]">
					<label>Title Field</label>
					<div>&mdash;</div>
				</fieldset>
			</article>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>
<? include BigTree::path("admin/modules/developer/modules/_js.php") ?>