<?
	$groups = $admin->getModuleGroups();
?>
<div class="container">
	<header>
		<p>The module designer will guide you through making a module without needing access to the database or knowledge of database table creation.</p>
	</header>
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/designer/create/" enctype="multipart/form-data" class="module">
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
				<fieldset<? if (isset($e["table"])) { ?> class="form_error"<? } ?>>
					<label class="required">Table Name <small>(for example, my_site_news)</small><? if (isset($e["table"])) { ?><span class="form_error_reason">Table Already Exists</span><? } ?></label>
					<input name="table" class="required" type="text" value="<?=$table?>" />
				</fieldset>
				<fieldset<? if (isset($e["class"])) { ?> class="form_error"<? } ?>>
					<label class="required">Class Name <small>(for example, MySiteNews)</small><? if (isset($e["class"])) { ?><span class="form_error_reason">Class Already Exists</span><? } ?></label>
					<input name="class" class="required" type="text" value="<?=$class?>" />
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
		</section>
		<footer>
			<input type="submit" class="button blue" value="Continue" />
		</footer>
	</form>
</div>
<? include BigTree::path("admin/modules/developer/modules/_js.php") ?>