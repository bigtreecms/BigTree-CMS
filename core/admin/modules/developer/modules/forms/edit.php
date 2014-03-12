<?
	$form = BigTreeAutoModule::getForm(end($bigtree["commands"]));;
	$module = $admin->getModule(BigTreeAutoModule::getModuleForForm($form));

	$table = $form["table"];
	$fields = $form["fields"];

	// Find out if we have more than one view. If so, give them an option of which one to return to.
	$available_views = $admin->getModuleViews("action_name",$module["id"]);
	
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
		<a href="<?=DEVELOPER_ROOT?>modules/forms/delete/<?=$form["id"]?>/?module=<?=$module["id"]?>" class="button red">Delete Form</a>
	</footer>
</div>
<?
	} else {
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/forms/update/<?=$form["id"]?>/" class="module">
		<? if ($_GET["return"] == "front") { ?>
		<input type="hidden" name="return_page" value="<?=htmlspecialchars($_SERVER["HTTP_REFERER"])?>" />
		<? } ?>
		<section>
			<div class="left last">
				<fieldset>
					<label class="required">Item Title <small>(for example, "Question" as in "Adding Question")</small></label>
					<input type="text" name="title" value="<?=$form["title"]?>" class="required" />
				</fieldset>

				<fieldset>
					<label class="required">Data Table</label>
					<select name="table" id="form_table" class="required">
						<? BigTree::getTableSelectOptions($form["table"]); ?>
					</select>
				</fieldset>

				<fieldset>
					<input type="checkbox" name="tagging" <? if ($form["tagging"]) { ?>checked="checked" <? } ?>/>
					<label class="for_checkbox">Enable Tagging</label>
				</fieldset>
			</div>
			<div class="right last">
				<? if (count($available_views) > 1) { ?>
				<fieldset>
					<label>Return View <small>(after the form is submitted, it will return to this view)</small></label>
					<select name="return_view">
						<? foreach ($available_views as $view) { ?>
						<option value="<?=$view["id"]?>"<? if ($form["return_view"] == $view["id"]) { ?> selected="selected"<? } ?>><?=$view["action_name"]?></option>
						<? } ?>
					</select>
				</fieldset>
				<? } ?>

				<fieldset>
					<label>Return URL <small>(an optional return URL to override the default return view)</small></label>
					<input type="text" name="return_url" value="<?=htmlspecialchars($form["return_url"])?>" />
				</fieldset>

				<fieldset>
					<label>Preprocessing Function <small>(passes in post data, returns keyed array of adds/edits)</small></label>
					<input type="text" name="preprocess" value="<?=htmlspecialchars($form["preprocess"])?>" />
				</fieldset>

				<fieldset>
					<label>Function Callback <small>(passes in ID and parsed post data, and publish state)</small></label>
					<input type="text" name="callback" value="<?=htmlspecialchars($form["callback"])?>" />
				</fieldset>
			</div>
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