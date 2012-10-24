<?
	$form = BigTreeAutoModule::getForm(end($bigtree["commands"]));;
	$module = $admin->getModule(BigTreeAutoModule::getModuleForForm($form));

	$action = $admin->getModuleActionForForm($form["id"]);
	$route = str_replace(array("add-","edit-","add","edit"),"",$action["route"]);

	$table = $form["table"];
	$fields = $form["fields"];

	// Find out if we have more than one view. If so, give them an option of which one to return to.
	if (sqlrows(sqlquery("SELECT * FROM bigtree_module_actions WHERE module = '".$module["id"]."' AND view != 0")) > 1) {
		$available_views = array();
		$q = sqlquery("SELECT bigtree_module_views.id,bigtree_module_actions.name FROM bigtree_module_views JOIN bigtree_module_actions ON bigtree_module_views.id = bigtree_module_actions.view WHERE bigtree_module_actions.module = '".$module["id"]."'");
		while ($f = sqlfetch($q)) {
			$available_views[] = $f;
		}
	} else {
		$available_views = false;
	}

	$breadcrumb[] = array("title" => $module["name"], "link" => "developer/modules/edit/".$module["id"]."/");
	$breadcrumb[] = array("title" => "Edit Form", "link" => "#");
?>
<h1><span class="modules"></span>Edit Form</h1>
<? include BigTree::path("admin/modules/developer/modules/_nav.php"); ?>

<div class="form_container">
	<form method="post" action="<?=$developer_root?>modules/forms/update/<?=$form["id"]?>/" class="module">
		<section>
			<div class="left last">
				<fieldset>
					<label class="required">Item Title <small>(for example, "Question" as in "Adding Question")</small></label>
					<input type="text" name="title" value="<?=$form["title"]?>" class="required" />
				</fieldset>

				<? if ($route) { ?>
				<fieldset>
					<label>Action Suffix <small>(for when there is more than one set of forms in a module)</small></label>
					<input type="text" name="suffix" value="<?=htmlspecialchars($route)?>" />
				</fieldset>
				<? } ?>

				<fieldset>
					<label class="required">Data Table</label>
					<select name="table" id="form_table" class="required">
						<option></option>
						<? BigTree::getTableSelectOptions($form["table"]); ?>
					</select>
				</fieldset>

				<fieldset>
					<input type="checkbox" name="tagging" <? if ($form["tagging"]) { ?>checked="checked" <? } ?>/>
					<label class="for_checkbox">Enable Tagging</label>
				</fieldset>
			</div>
			<div class="right last">
				<? if ($available_views) { ?>
				<fieldset>
					<label>Return View <small>(after the form is submitted, it will return to this view)</small></label>
					<select name="return_view">
						<? foreach ($available_views as $view) { ?>
						<option value="<?=$view["id"]?>"<? if ($form["return_view"] == $view["id"]) { ?> selected="selected"<? } ?>><?=$view["name"]?></option>
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

<? include BigTree::path("admin/modules/developer/modules/forms/_js.php") ?>