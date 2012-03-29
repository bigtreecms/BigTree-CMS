<?
	$form = BigTreeAutoModule::getForm($commands[0]);;
	$module = $admin->getModule(BigTreeAutoModule::getModuleForForm($form));
	
	$action = $admin->getModuleActionForForm($form["id"]);
	$route = str_replace(array("add-","edit-","add","edit"),"",$action["route"]);
	
	$table = $form["table"];
	$fields = $form["fields"];
	
	$breadcrumb[] = array("title" => $module["name"], "link" => "developer/modules/edit/".$module["id"]."/");	
	$breadcrumb[] = array("title" => "Edit Form", "link" => "#");
?>
<h1><span class="icon_developer_modules"></span>Edit Form</h1>
<? include BigTree::path("admin/modules/developer/modules/_nav.php"); ?>

<div class="form_container">
	<form method="post" action="<?=$developer_root?>modules/forms/update/<?=$form["id"]?>/" class="module">
		<section>
			<div class="left">
				<fieldset>
					<label class="required">Item Title <small>(for example, "Question" as in "Adding Question")</small></label>
					<input type="text" name="title" value="<?=$form["title"]?>" class="required" />
				</fieldset>
			
				<? if ($route) { ?>
				<fieldset>
					<label>Action Suffix <small>(for when there is more than one set of forms in a module)</small></label>
					<input type="text" name="suffix" value="<?=$route?>" />
				</fieldset>
				<? } ?>
			
				<fieldset>
					<label class="required">Data Table</label>
					<select name="table" id="form_table" class="required">
						<option></option>
						<? BigTree::getTableSelectOptions($form["table"]); ?>
					</select>
				</fieldset>
			</div>
			<div class="right">
				<fieldset class="split">
					<label>Custom Javascript</label>
					<input type="text" name="javascript" value="<?=$form["javascript"]?>" />
				</fieldset>
			
				<fieldset class="split second">
					<label>Custom CSS</label>
					<input type="text" name="css" value="<?=$form["css"]?>" />
				</fieldset>
			
				<fieldset>
					<label>Function Callback <small>(passes in ID and parsed post data on success)</small></label>
					<input type="text" name="callback" value="<?=$form["callback"]?>" />
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

<script type="text/javascript">
	$("#form_table").bind("select:changed",function(event,data) {
		$("#field_area").load("<?=$admin_root?>ajax/developer/load-form/", { table: data.value });
	});
</script>