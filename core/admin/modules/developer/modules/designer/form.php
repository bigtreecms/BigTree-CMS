<?php
	namespace BigTree;
	
	$module = new Module($_GET["module"]);
	$field_types = FieldType::reference(true, "modules");
	
	if (!empty($_SESSION["developer"]["saved_form"])) {
		$saved = $_SESSION["developer"]["saved_form"];
		$error = true;
		$title = $saved["title"];
		$table = $saved["table"];
		
		unset($_SESSION["developer"]["saved_form"]);
	} else {
		$error = false;
		$table = $_GET["table"];
		$title = $module->Name;
		
		if (substr($title,-3,3) == "ies") {
			$title = substr($title, 0, -3)."y";
		} else {
			$title = rtrim($title, "s");
		}
	}
?>
<div class="container">
	<header>
		<p><?=Text::translate("Step 2: Creating Your Form")?></p>
	</header>
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/designer/form-create/" class="module">
		<?php CSRF::drawPOSTToken(); ?>
		<input type="hidden" name="module" value="<?=$module->ID?>" />
		<input type="hidden" name="table" value="<?=htmlspecialchars($table)?>" />
		<section>
			<p class="error_message"<?php if (!$error) { ?> style="display: none;"<?php } ?>><?=Text::translate("Errors found! Please fix the highlighted fields before submitting.")?></p>
			<div class="left">
				<fieldset>
					<label for="form_field_title" class="required"><?=Text::translate('Item Title <small>(for example, "Question" as in "Adding Question")</small>')?></label>
					<input id="form_field_title" type="text" class="required" name="title" value="<?=Text::htmlEncode($title)?>" />
				</fieldset>
			</div>
		</section>
		<section id="field_area" class="sub">
			<fieldset<?php if ($error) { ?> class="form_error"<?php } ?>>
				<label class="required"><?=Text::translate("Fields")?><?php if ($error) { ?><span class="form_error_reason"><?=Text::translate("One Or More Fields Required")?></span><?php } ?></label>
				<div class="form_table">
					<header>
						<a class="add add_field" href="#"><span></span><?=Text::translate("Field")?></A>
					</header>
					<div class="labels">
						<span class="developer_resource_form_title"><?=Text::translate("Title")?></span>
						<span class="developer_resource_form_subtitle"><?=Text::translate("Subtitle")?></span>
						<span class="developer_resource_type"><?=Text::translate("Type")?></span>
						<span class="developer_resource_action"><?=Text::translate("Edit")?></span>
						<span class="developer_resource_action"><?=Text::translate("Delete")?></span>
					</div>
					<ul id="resource_table"></ul>
				</div>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Create", true)?>" />
		</footer>
	</form>
</div>

<script>
	(function() {
		var CurrentFieldKey = false;
		var KeyCount = 0;
		
		function hooks() {
			$("#resource_table").sortable({
				axis: "y",
				containment: "parent",
				handle: ".icon_sort",
				items: "li",
				placeholder: "ui-sortable-placeholder",
				tolerance: "pointer"
			});
			
			BigTreeCustomControls();
		}
		
		$(".form_table").on("click",".icon_settings",function(ev) {
			ev.preventDefault();
			
			// Prevent double clicks
			if (BigTree.Busy) {
				return;
			}
			
			CurrentFieldKey = $(this).attr("name");
			
			BigTreeDialog({
				title: "<?=Text::translate("Field Options")?>",
				url: "<?=ADMIN_ROOT?>ajax/developer/load-field-options/",
				post: { type: $("#type_" + CurrentFieldKey).val(), data: $("#options_" + CurrentFieldKey).val() },
				icon: "edit",
				callback: function(data) {
					$("#options_" + CurrentFieldKey).val(JSON.stringify(data));
				}
			});
			
		}).on("click",".icon_delete",function() {
			$(this).parents("li").remove();
			return false;
		});
		
		$(".add_field").click(function() {
			KeyCount++;
			
			var li = $('<li id="row_' + KeyCount + '">');
			li.html('<section class="developer_resource_form_title">' +
						'<span class="icon_sort"></span>' +
						'<input type="text" name="titles[' + KeyCount + ']" value="" class="required" />' +
					'</section>' +
					'<section class="developer_resource_form_subtitle">' +
						'<input type="text" name="subtitles[' + KeyCount + ']" value="" />' +
					'</section>' +
					'<section class="developer_resource_type">' +
						'<select name="type[' + KeyCount + ']" id="type_' + KeyCount + '">' +
							'<optgroup label="Default">' +
								<?php foreach ($field_types["default"] as $id => $field_type) { ?>
								'<option value="<?=$id?>"><?=$field_type["name"]?></option><?php } ?>' +
							'</optgroup>' +
							<?php if (count($field_types["custom"])) { ?>
							'<optgroup label="Custom">' +
								<?php foreach ($field_types["custom"] as $id => $field_type) { ?>
								'<option value="<?=$id?>"><?=$field_type["name"]?></option>' +
								<?php } ?>
							'</optgroup>' +
							<?php } ?>
						'</select>' +
						'<a href="#" class="options icon_settings" name="' + KeyCount + '"></a>' +
						'<input type="hidden" name="options[' + KeyCount + ']" value="" id="options_' + KeyCount + '" />' +
					'</section>' +
					'<section class="developer_resource_action">' +
						'<a href="#" class="icon_delete" name="' + KeyCount + '"></a>' +
					'</section>');
			
			$("#resource_table").append(li);
			hooks();
			
			return false;
		});
		
		hooks();
		BigTreeFormValidator("form.module");
	})();
</script>