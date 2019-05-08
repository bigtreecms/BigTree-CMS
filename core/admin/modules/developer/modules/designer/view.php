<?php
	namespace BigTree;
	
	$module = new Module($_GET["module"]);
	
	if (!empty($_SESSION["developer"]["saved_view"])) {
		$saved = $_SESSION["developer"]["saved_view"];
		$error = true;
		$title = $saved["title"];
		$table = $saved["table"];
		$type = $saved["type"];
		$description = Text::htmlEncode($saved["description"]);
		
		unset($_SESSION["developer"]["saved_view"]);
	} else {
		$error = false;
		$table = $_GET["table"];
		$title = $module->Name;
		$type = "searchable";
		$description = "";
		
		if (substr($title,-3,3) == "ies") {
			$title = substr($title, 0, -3)."y";
		} else {
			$title = rtrim($title, "s");
		}
	}
?>
<div class="container">
	<header>
		<p><?=Text::translate("Step 3: Creating Your View")?></p>
	</header>
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/designer/view-create/" class="module">
		<?php CSRF::drawPOSTToken(); ?>
		<input type="hidden" name="module" value="<?=$module->ID?>" />
		<input type="hidden" name="table" value="<?=Text::htmlEncode($table)?>" />
		<section>
			<p class="error_message"<?php if (!$error) { ?> style="display: none;"<?php } ?>><?=Text::translate("Errors found! Please ensure you have entered an Item Title and one or more Fields.")?></p>
			
			<div class="left">
				<fieldset>
					<label for="view_field_title" class="required"><?=Text::translate('Item Title <small>(for example, "Questions" to make the title "Viewing Questions")</small>')?></label>
					<input id="view_field_title" type="text" class="required" name="title" value="<?=Text::htmlEncode($title)?>" tabindex="1" />
				</fieldset>
				
				<fieldset class="left">
					<label for="view_type"><?=Text::translate("View Type")?></label>
					<select name="type" id="view_type" class="left" tabindex="2">
						<option value="searchable"><?=Text::translate("Searchable List")?></option>
						<option value="draggable"<?php if ($type == "draggable") { ?> selected="selected"<?php } ?>><?=Text::translate("Draggable List")?></option>
					</select>
					&nbsp; <a href="#" class="icon_settings centered"></a>
					<input type="hidden" name="settings" id="view_settings" />
				</fieldset>
			</div>
			
			<div class="right">
				<fieldset>
					<label for="view_field_description"><?=Text::translate("Page Description <small>(instructions for the user)</small>")?></label>
					<textarea id="view_field_description" name="description" tabindex="3"><?=$description?></textarea>
				</fieldset>
			</div>
		</section>
		<section id="field_area" class="sub">
			<?php
				define("BIGTREE_MODULE_DESIGNER_VIEW", true);
				include Router::getIncludePath("admin/ajax/developer/load-view-fields.php");
			?>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Create", true)?>" />
		</footer>
	</form>
</div>

<script>	
	BigTreeFormValidator("form.module");
	
	$(".icon_settings").click(function(ev) {
		ev.preventDefault();

		// Prevent double clicks
		if (BigTree.Busy) {
			return;
		}

		BigTreeDialog({
			title: "<?=Text::translate("View Settings", true)?>",
			url: "<?=ADMIN_ROOT?>ajax/developer/load-view-settings/",
			post: { table: "<?=$table?>", type: $("#view_type").val(), data: $("#view_settings").val() },
			icon: "edit",
			callback: function(data) {
				$("#view_settings").val(JSON.stringify(data));
			}
		});
	});
</script>