<?php
	$module = $admin->getModule($_GET["module"]);
	$table = $_GET["table"];
	
	if (!$title) {
		$title = $module["name"];
		if (substr($title,-3,3) == "ies") {
			$title = substr($title,0,-3)."y";
		} else {
			$title = rtrim($title,"s");
		}
	}
	
	$cached_types = $admin->getCachedFieldTypes(true);
	$types = $cached_types["modules"];
?>
<div class="container">
	<header>
		<p>Step 2: Creating Your Form</p>
	</header>
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/designer/form-create/" class="module">
		<?php $admin->drawCSRFToken() ?>
		<input type="hidden" name="module" value="<?=$module["id"]?>" />
		<input type="hidden" name="table" value="<?=htmlspecialchars($table)?>" />
		<section>
			<p class="error_message"<?php if (!count($e)) { ?> style="display: none;"<?php } ?>>Errors found! Please fix the highlighted fields before submitting.</p>
			<div class="left">
				<fieldset>
					<label class="required">Item Title <small>(for example, "Question" as in "Adding Question")</small></label>
					<input type="text" class="required" name="title" value="<?=$title?>" />
				</fieldset>
			</div>
		</section>
		<section id="field_area" class="sub">
			<fieldset<?php if (isset($e["fields"])) { ?> class="form_error"<?php } ?>>
				<label class="required">Fields<?php if (isset($e["fields"])) { ?><span class="form_error_reason">One Or More Fields Required</span><?php } ?></label>
				<div class="form_table">
					<header>
						<a class="add add_field" href="#"><span></span>Field</A>
					</header>
					<div class="labels">
						<span class="developer_resource_form_title">Title</span>
						<span class="developer_resource_form_subtitle">Subtitle</span>
						<span class="developer_resource_type">Type</span>
						<span class="developer_resource_action">Edit</span>
						<span class="developer_resource_action">Delete</span>
					</div>
					<ul id="resource_table"></ul>
				</div>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>

<script>
	BigTreeFormValidator("form.module");

	BigTree.localCurrentFieldKey = false;
	BigTree.localMTMCount = 0;
	BigTree.localKeyCount = 0;
	BigTree.localHooks = function() {
		$("#resource_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
		BigTreeCustomControls();
	};
	
	$(".form_table").on("click",".icon_settings",function(ev) {
		ev.preventDefault();

		// Prevent double clicks
		if (BigTree.Busy) {
			return;
		}

		BigTree.localCurrentFieldKey = $(this).attr("name");
		
		$.ajax("<?=ADMIN_ROOT?>ajax/developer/load-field-settings/", { type: "POST", data: { type: $("#type_" + BigTree.localCurrentFieldKey).val(), data: $("#settings_" + BigTree.localCurrentFieldKey).val() }, complete: function(response) {
			BigTreeDialog({
				title: "Field Settings",
				content: response.responseText,
				icon: "edit",
				callback: function(data) {
					$("#settings_" + BigTree.localCurrentFieldKey).val(JSON.stringify(data));
				}
			});
		}});
		
	}).on("click",".icon_delete",function() {
		$(this).parents("li").remove();		
		return false;
	});
	
	$(".add_field").click(function() {
		BigTree.localKeyCount++;
		var c = BigTree.localKeyCount;

		var li = $('<li id="row_' + c + '">');
		li.html('<section class="developer_resource_form_title"><span class="icon_sort"></span><input type="text" name="titles[' + c + ']" value="" class="required" /></section><section class="developer_resource_form_subtitle"><input type="text" name="subtitles[' + c + ']" value="" /></section><section class="developer_resource_type"><select name="type[' + c + ']" id="type_' + c + '"><optgroup label="Default"><?php foreach ($types["default"] as $k => $v) { ?><option value="<?=$k?>"><?=$v["name"]?></option><?php } ?></optgroup><?php if (count($types["custom"])) { ?><optgroup label="Custom"><?php foreach ($types["custom"] as $k => $v) { ?><option value="<?=$k?>"><?=$v["name"]?></option><?php } ?></optgroup><?php } ?></select><a href="#" class="icon_settings" name="' + c + '"></a><input type="hidden" name="settings[' + c + ']" value="" id="settings_' + c + '" /></section><section class="developer_resource_action"><a href="#" class="icon_delete" name="' + c + '"></a></section>');
		
		$("#resource_table").append(li);
		BigTree.localHooks();
		
		return false;
	});
	
	BigTree.localHooks();
</script>