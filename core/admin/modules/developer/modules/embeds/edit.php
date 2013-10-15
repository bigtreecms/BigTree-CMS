<?
	$form = BigTreeAutoModule::getEmbedForm(end($bigtree["commands"]));;
	$module = $admin->getModule($form["module"]);

	$table = $form["table"];
	$fields = $form["fields"];
	
	if (!BigTree::tableExists($table)) {
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<h3>Error</h3>
		</div>
		<p>The table for this form (<?=$table?>) no longer exists.</p>
	</section>
	<footer>
		<a href="javascript:history.go(-1);" class="button">Back</a>
		<a href="<?=$section_root?>delete/<?=$form["id"]?>/?module=<?=$module["id"]?>" class="button red">Delete Form</a>
	</footer>
</div>
<?
	} else {
?>
<div class="container">
	<form method="post" action="<?=$section_root?>update/<?=$form["id"]?>/" class="module">
		<section>
			<div class="left last">
				<fieldset>
					<label class="required">Title <small>(for reference only, not shown in the embed)</small></label>
					<input type="text" class="required" name="title" value="<?=$form["title"]?>" />
				</fieldset>

				<fieldset>
					<label class="required">Data Table</label>
					<select name="table" id="form_table" class="required">
						<option></option>
						<? BigTree::getTableSelectOptions($table); ?>
					</select>
				</fieldset>

				<fieldset>
					<input type="checkbox" name="default_pending"<? if ($form["default_pending"]) { ?> checked="checked"<? } ?> />
					<label class="for_checkbox">Default Submissions to Pending</label>
				</fieldset>
			</div>

			<div class="right last">
				<fieldset>
					<label>Preprocessing Function <small>(passes in post data, returns keyed array of adds/edits)</small></label>
					<input type="text" name="preprocess" value="<?=htmlspecialchars($form["preprocess"])?>" />
				</fieldset>

				<fieldset>
					<label>Function Callback <small>(passes in ID and parsed post data, and publish state)</small></label>
					<input type="text" name="callback" value="<?=htmlspecialchars($form["callback"])?>" />
				</fieldset>

				<fieldset>
					<label>Custom CSS File <small>(full URL)</small></label>
					<input type="text" name="css" value="<?=$form["css"]?>" />
				</fieldset>
			</div>
		</section>
		<section class="sub">
			<label>Embed Code <small>(not editable)</small></label>
			<input type="text" value="<?=htmlspecialchars('<script type="text/javascript" src="'.ADMIN_ROOT.'js/embeddable-form.js?hash='.$form["hash"].'"></script>')?>" />
		</section>
		<section class="sub" id="field_area">
			<? include BigTree::path("admin/ajax/developer/load-form.php") ?>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>
<?
		include BigTree::path("admin/modules/developer/modules/forms/_js.php");
	}
?>