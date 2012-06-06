<?
	$table = isset($bigtree["commands"][1]) ? $bigtree["commands"][1] : "";

	$module = $admin->getModule($bigtree["commands"][0]);
	$edit_action_exists = $admin->doesModuleEditActionExist($module["id"]);
	
	if (isset($bigtree["commands"][2])) {
		$title = $bigtree["commands"][2];
		if (substr($title,-3,3) == "ies") {
			$title = substr($title,0,-3)."y";
		} else {
			$title = rtrim($title,"s");
		}
	} else {
		$title = "";
	}
	
	$title = htmlspecialchars(urldecode($title));

	$breadcrumb[] = array("title" => $module["name"], "link" => "developer/modules/edit/$id/");
	$breadcrumb[] = array("title" => "Add Form", "link" => "#");
?>

<h1><span class="icon_developer_modules"></span>Add Form</h1>
<? include BigTree::path("admin/modules/developer/modules/_nav.php"); ?>

<div class="form_container">
	<form method="post" action="<?=$developer_root?>modules/forms/create/<?=$module["id"]?>/" class="module">
		<section>
			<div class="left">
				<fieldset>
					<label class="required">Item Title <small>(for example, "Question" as in "Adding Question")</small></label>
					<input type="text" class="required" name="title" value="<?=$title?>" />
				</fieldset>
				
				<? if ($edit_action_exists) { ?>
				<fieldset>
					<label>Action Suffix <small>(for when there is more than one set of forms in a module)</small></label>
					<input type="text" name="suffix" <? if (isset($bigtree["commands"][3])) { echo 'value="'.$bigtree["commands"][3].'" '; } ?>/>
				</fieldset>
				<? } ?>
				
				<fieldset>
					<label class="required">Data Table</label>
					<select name="table" id="form_table" class="required">
						<option></option>
						<? BigTree::getTableSelectOptions($table); ?>
					</select>
				</fieldset>
			</div>
			
			<div class="right">
				<fieldset>
					<label>Preprocessing Function <small>(passes in post data, returns keyed array of adds/edits)</small></label>
					<input type="text" name="preprocess" value="<?=$form["preprocess"]?>" />
				</fieldset>
				
				<fieldset>
					<label>Function Callback <small>(passes in ID and parsed post data, and publish state)</small></label>
					<input type="text" name="callback" value="<?=htmlspecialchars($form["callback"])?>" />
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

<script type="text/javascript">
	$("#form_table").change(function(event,data) {
		$("#field_area").load("<?=ADMIN_ROOT?>ajax/developer/load-form/", { table: data.value });
		$("#create").show();
	});
</script>	