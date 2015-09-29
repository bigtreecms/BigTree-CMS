<?php
	$cached_types = $admin->getCachedFieldTypes(true);
	$types = $cached_types['callouts'];
?>
<script>
	BigTreeFormValidator("form.module");
	
	BigTree.localCurrentFieldKey = false;
	BigTree.localResourceCount = <?=$x?>;
	
	$("#resource_table").on("blur", ".developer_resource_id input", function() {
		$(this).parents("li").find(".developer_resource_display_title input").val($(this).val());
	});
	
	$(".template_image_list a").click(function() {
		$(".template_image_list a.active").removeClass("active");
		$(this).addClass("active");
		$("#existing_image").val($(this).attr("href").substr(1));
		
		return false;
	});
	
	$(".form_table").on("click",".icon_settings",function(ev) {
		ev.preventDefault();

		// Prevent double clicks
		if (BigTree.Busy) {
			return;
		}

		var key = $(this).attr("name");
		BigTree.localCurrentFieldKey = key;
		
		$.ajax("<?=ADMIN_ROOT?>ajax/developer/load-field-options/", { type: "POST", data: { callout: "true", type: $("#type_" + key).val(), data: $("#options_" + key).val() }, complete: function(response) {
			BigTreeDialog({
				title: "Field Options",
				content: response.responseText,
				icon: "edit",
				callback: function(data) {
					$("#options_" + BigTree.localCurrentFieldKey).val(JSON.stringify(data));
				}
			});
		}});
		
	}).on("click",".icon_delete",function() {
		BigTreeDialog({
			title: "Delete Resource",
			content: '<p class="confirm">Are you sure you want to delete this resource?</p>',
			icon: "delete",
			alternateSaveText: "OK",
			callback: $.proxy(function() {
				$(this).parents("li").remove();
			},this)
		});
		
		return false;
	}).on("click","input[name=display_field]",function() {
	// Handle making sure IDs get tacked on to the display field radio buttons.
		// Get the id field
		var id = $(this).parents("li").find("input").eq(0).val();
		$(this).val(id);
	}).on("change",".developer_resource_callout_id input",function() {
		$(this).parents("li").find("input[type=radio]").val($(this).val());
	});
		
	$(".add_resource").click(function() {
		BigTree.localResourceCount++;
		
		var li = $('<li id="row_' + BigTree.localResourceCount + '">');
		li.html('<section class="developer_resource_callout_id"><span class="icon_sort"></span><input type="text" name="resources[' + BigTree.localResourceCount + '][id]" value="" /></section><section class="developer_resource_callout_title"><input type="text" name="resources[' + BigTree.localResourceCount + '][title]" value="" /></section><section class="developer_resource_callout_subtitle"><input type="text" name="resources[' + BigTree.localResourceCount + '][subtitle]" value="" /></section><section class="developer_resource_type"><select name="resources[' + BigTree.localResourceCount + '][type]" id="type_' + BigTree.localResourceCount + '"><optgroup label="Default"><?php foreach ($types['default'] as $k => $v) {
    ?><option value="<?=$k?>"><?=$v['name']?></option><?php 
} ?></optgroup><?php if (count($types['custom'])) {
    ?><optgroup label="Custom"><?php foreach ($types['custom'] as $k => $v) {
    ?><option value="<?=$k?>"><?=$v['name']?></option><?php 
}
    ?></optgroup><?php 
} ?></select><a href="#" tabindex="-1" class="icon_settings" name="' + BigTree.localResourceCount + '"></a><input type="hidden" name="resources[' + BigTree.localResourceCount + '][options]" value="" id="options_' + BigTree.localResourceCount + '" /></section><section class="developer_resource_display_title"><input type="radio" name="display_field" value="" id="display_title_' + BigTree.localResourceCount + '" /></section><section class="developer_resource_action right"><a href="#" tabindex="-1" class="icon_delete"></a></section>');

		$("#resource_table").append(li);
		li.find("select").get(0).customControl = new BigTreeSelect(li.find("select").get(0));
		li.find("input[type=radio]").get(0).customControl = new BigTreeRadioButton(li.find("input[type=radio]").get(0));

		$("#resource_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });

		return false;
	});
	
	$("#resource_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
</script>