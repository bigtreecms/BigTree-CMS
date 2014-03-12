<?
	$id = $_GET["module"];
	$table = isset($_GET["table"]) ? $_GET["table"] : "";

	$module = $admin->getModule($id);

	// Try to make sense of a plural title into singular
	if (isset($_GET["title"])) {
		$title = $_GET["title"];
		if (substr($title,-3,3) == "ies") {
			$title = substr($title,0,-3)."y";
		} else {
			$title = rtrim($title,"s");
		}
		if (strtolower($_GET["title"]) == "news") {
			$title = $_GET["title"];
		}
	} else {
		$title = "";
	}

	// Find out if we have more than one view. If so, give them an option of which one to return to.
	$available_views = $admin->getModuleViews("action_name",$module["id"]);

	$title = htmlspecialchars(urldecode($title));
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/forms/create/<?=$module["id"]?>/" class="module">
		<section>
			<div class="left last">
				<fieldset>
					<label class="required">Item Title <small>(for example, "Question" as in "Adding Question")</small></label>
					<input type="text" class="required" name="title" value="<?=$title?>" />
				</fieldset>

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
				<? if (count($available_views) > 1) { ?>
				<fieldset>
					<label>Return View <small>(after the form is submitted, it will return to this view)</small></label>
					<select name="return_view">
						<? foreach ($available_views as $view) { ?>
						<option value="<?=$view["id"]?>"<? if (isset($_GET["view"]) && $_GET["view"] == $view["id"]) { ?> selected="selected"<? } ?>><?=$view["action_name"]?></option>
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
					echo "<p>Please choose a table to populate this area.</p>";
				}
			?>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>

<? include BigTree::path("admin/modules/developer/modules/forms/_js.php") ?>