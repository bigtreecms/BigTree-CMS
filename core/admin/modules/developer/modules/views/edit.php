<?	
	$view = BigTreeAutoModule::getView(end($bigtree["path"]));
	$action = $admin->getModuleActionForView(end($bigtree["path"]));
	$module = $admin->getModule($action["module"]);

	BigTree::globalizeArray($view);

	if (!BigTree::tableExists($table)) {
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<h3>Error</h3>
		</div>
		<p>The table for this view (<?=$table?>) no longer exists.</p>
	</section>
	<footer>
		<a href="javascript:history.go(-1);" class="button">Back</a>
		<a href="<?=$section_root?>delete/<?=$view["id"]?>/" class="button red">Delete View</a>
	</footer>
</div>
<?
	} else {
?>
<div class="container">
	<form method="post" action="<?=$developer_root?>modules/views/update/<?=end($bigtree["path"])?>/" class="module">
		<section>
			<? if ($action["route"]) { ?>
			<div class="alert">
				<span></span>
				<p><strong>This is not the default view:</strong>  You may specify an action suffix below.</p>
			</div>
			<fieldset>
				<label>Add/Edit Suffix</label>
				<input type="text" name="suffix" value="<?=htmlspecialchars($suffix)?>" />
			</fieldset>
			<? } ?>
			
			<div class="left">
				<fieldset>
					<label class="required">Item Title <small>(for example, "Questions" to make the title "Viewing Questions")</small></label>
					<input type="text" name="title" value="<?=$title?>" class="required" />
				</fieldset>
				<fieldset>
					<label class="required">Data Table</label>
					<select name="table" id="view_table" class="required" >
						<? BigTree::getTableSelectOptions($table); ?>
					</select>
				</fieldset>
				<fieldset>
					<label>View Type</label>
					<select name="type" id="view_type" class="left" >
						<? foreach ($admin->ViewTypes as $key => $t) { ?>
						<option value="<?=$key?>"<? if ($key == $type) { ?> selected="selected"<? } ?>><?=$t?></option>
						<? } ?>
					</select>
					&nbsp; <a href="#" class="options icon_settings"></a>
					<input type="hidden" name="options" id="view_options" value="<?=htmlspecialchars(json_encode($view["options"]))?>" />
				</fieldset>
			</div>
			
			<div class="right">
				<fieldset>
					<label>Description <small>(instructions for the user)</small></label>
					<textarea name="description" ><?=$description?></textarea>
				</fieldset>
			</div>
			
			<fieldset>
				<label>Preview URL <small>(optional, i.e. http://www.website.com/news/preview/ &mdash; the item's id will be entered as the final route)</small></label>
				<input type="text" name="preview_url" value="<?=$preview_url?>" />
			</fieldset>
		</section>
		<section class="sub" id="field_area">
			<? include BigTree::path("admin/ajax/developer/load-view-fields.php") ?>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>
<?
		include BigTree::path("admin/modules/developer/modules/views/_js.php");
	}
?>