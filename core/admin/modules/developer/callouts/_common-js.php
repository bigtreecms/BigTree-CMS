<?
	$cached_types = $admin->getCachedFieldTypes();
	$types = $cached_types["callout"];
?>
<script>
	new BigTreeFormValidator("form.module");
	
	BigTree.currentFieldKey = false;
	BigTree.resourceCount = 0;
	
	$("#resource_table").on("blur", ".developer_resource_id input", function() {
		$(this).parents("li").find(".developer_resource_display_title input").val($(this).val());
	});
	
	$(".template_image_list a").click(function() {
		$(".template_image_list a.active").removeClass("active");
		$(this).addClass("active");
		$("#existing_image").val($(this).attr("href").substr(1));
		
		return false;
	});
	
	$(".form_table").on("click",".icon_settings",function() {
		key = $(this).attr("name");
		BigTree.currentFieldKey = key;
		
		$.ajax("<?=ADMIN_ROOT?>ajax/developer/load-field-options/", { type: "POST", data: { template: "true", type: $("#type_" + key).val(), data: $("#options_" + key).val() }, complete: function(response) {
			new BigTreeDialog("Field Options",response.responseText,function(data) {
				$.ajax("<?=ADMIN_ROOT?>ajax/developer/save-field-options/?key=" + BigTree.currentFieldKey, { type: "POST", data: data });
			});
		}});
		
		return false;
	}).on("click",".icon_delete",function() {
		new BigTreeDialog("Delete Resource",'<p class="confirm">Are you sure you want to delete this resource?',$.proxy(function() {
			$(this).parents("li").remove();
		},this),"delete",false,"OK");
		
		return false;
	}).on("click","input[name=display_field]",function() {
	// Handle making sure IDs get tacked on to the display field radio buttons.
		// Get the id field
		id = $(this).parents("li").find("input").eq(0).val();
		$(this).val(id);
	}).on("change",".developer_resource_callout_id input",function() {
		$(this).parents("li").find("input[type=radio]").val($(this).val());
	});
		
	$(".add_resource").click(function() {
		BigTree.resourceCount++;
		
		li = $('<li id="row_' + BigTree.resourceCount + '">');
		li.html('<section class="developer_resource_callout_id"><span class="icon_sort"></span><input type="text" name="resources[' + BigTree.resourceCount + '][id]" value="" /></section><section class="developer_resource_callout_title"><input type="text" name="resources[' + BigTree.resourceCount + '][title]" value="" /></section><section class="developer_resource_callout_subtitle"><input type="text" name="resources[' + BigTree.resourceCount + '][subtitle]" value="" /></section><section class="developer_resource_type"><select name="resources[' + BigTree.resourceCount + '][type]" id="type_' + BigTree.resourceCount + '" class="custom_control"><? foreach ($types as $k => $v) { ?><option value="<?=$k?>"><?=$v?></option><? } ?></select><a href="#" tabindex="-1" class="icon_settings" name="' + BigTree.resourceCount + '"></a><input type="hidden" name="resources[' + BigTree.resourceCount + '][options]" value="" id="options_' + BigTree.resourceCount + '" /></section><section class="developer_resource_display_title"><input type="radio" name="display_field" value="" id="display_title_' + BigTree.resourceCount + '" class="custom_control" /></section><section class="developer_resource_action right"><a href="#" tabindex="-1" class="icon_delete"></a></section>');

		$("#resource_table").append(li);
		li.find("select").get(0).customControl = new BigTreeSelect(li.find("select").get(0));
		li.find("input[type=radio]").get(0).customControl = new BigTreeRadioButton(li.find("input[type=radio]").get(0));

		$("#resource_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });

		return false;
	});
	
	$("#resource_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
</script>