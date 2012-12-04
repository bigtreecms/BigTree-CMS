<?
	$id = $_GET["module"];
	$table = isset($_GET["table"]) ? $_GET["table"] : "";

	$module = $admin->getModule($id);
	$edit_action_exists = $admin->doesModuleEditActionExist($module["id"]);

	if (isset($_GET["title"])) {
		$title = $_GET["title"];
		if (substr($title,-3,3) == "ies") {
			$title = substr($title,0,-3)."y";
		} else {
			$title = rtrim($title,"s");
		}
	} else {
		$title = "";
	}

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


	$title = htmlspecialchars(urldecode($title));
?>
<div class="form_container">
	<form method="post" action="<?=$developer_root?>modules/forms/create/<?=$module["id"]?>/" class="module">
		<section>
			<div class="left last">
				<fieldset>
					<label class="required">Item Title <small>(for example, "Question" as in "Adding Question")</small></label>
					<input type="text" class="required" name="title" value="<?=$title?>" />
				</fieldset>

				<? if ($edit_action_exists) { ?>
				<fieldset>
					<label>Action Suffix <small>(for when there is more than one set of forms in a module)</small></label>
					<input type="text" name="suffix" class="required" <? if (isset($_GET["suffix"])) { ?>value="<?=$_GET["suffix"]?>" <? } ?>/>
				</fieldset>
				<? } ?>

				<fieldset>
					<label class="required">Data Table</label>
					<select name="table" id="form_table" class="required">
						<option></option>
						<? BigTree::getTableSelectOptions($table); ?>
					</select>
				</fieldset>

				<fieldset>
					<input type="checkbox" name="tagging" />
					<label class="for_checkbox">Enable Tagging</label>
				</fieldset>
			</div>

			<div class="right last">
				<? if ($available_views) { ?>
				<fieldset>
					<label>Return View <small>(after the form is submitted, it will return to this view)</small></label>
					<select name="return_view">
						<? foreach ($available_views as $view) { ?>
						<option value="<?=$view["id"]?>"<? if (isset($_GET["view"]) && $_GET["view"] == $view["id"]) { ?> selected="selected"<? } ?>><?=$view["name"]?></option>
						<? } ?>
					</select>
				</fieldset>
				<? } ?>

				<fieldset>
					<label>Return URL <small>(an optional return URL to override the default return view)</small></label>
					<input type="text" name="return_url" />
				</fieldset>

				<fieldset>
					<label>Preprocessing Function <small>(passes in post data, returns keyed array of adds/edits)</small></label>
					<input type="text" name="preprocess" />
				</fieldset>

				<fieldset>
					<label>Function Callback <small>(passes in ID and parsed post data, and publish state)</small></label>
					<input type="text" name="callback" />
				</fieldset>
			</div>
		</section>
		<section class="sub" id="field_area">
			<?
				if ($table) {
					include BigTree::path("admin/ajax/developer/load-form.php");
				} else {
					echo "<p>Please choose a table to populate fields.</p>";
				}
			?>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>

<? include BigTree::path("admin/modules/developer/modules/forms/_js.php") ?>