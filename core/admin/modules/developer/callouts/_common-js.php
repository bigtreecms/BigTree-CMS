<?
	$cached_types = $admin->getCachedFieldTypes();
	$types = $cached_types["callout"];
?>
<script>
	new BigTreeFormValidator("form.module");
	
	var current_editing_key;
	var resource_count = 0;
	
	$("#resource_table").on("blur", ".developer_resource_id input", function() {
		var id = $(this).val();
		var parent = $(this).parents("li");
		parent.find(".developer_resource_display_title input").val(id);
	});
	
	$(".template_image_list a").click(function() {
		$(".template_image_list a.active").removeClass("active");
		$(this).addClass("active");
		$("#existing_image").val($(this).attr("href").substr(1));
		
		return false;
	});
	
	$(".icon_settings").live("click",function() {
		key = $(this).attr("name");
		current_editing_key = key;
		
		$.ajax("<?=ADMIN_ROOT?>ajax/developer/load-field-options/", { type: "POST", data: { template: "true", type: $("#type_" + key).val(), data: $("#options_" + key).val() }, complete: function(response) {
			new BigTreeDialog("Field Options",response.responseText,function(data) {
				$.ajax("<?=ADMIN_ROOT?>ajax/developer/save-field-options/?key=" + current_editing_key, { type: "POST", data: data });
			});
		}});
		
		return false;
	});
	
	$(".icon_delete").live("click",function() {
		new BigTreeDialog("Delete Resource",'<p class="confirm">Are you sure you want to delete this resource?',$.proxy(function() {
			$(this).parents("li").remove();
		},this),"delete",false,"OK");
		
		return false;
	});
		
	$(".add_resource").click(function() {
		resource_count++;
		
		li = $('<li id="row_' + resource_count + '">');
		li.html('<section class="developer_resource_callout_id"><span class="icon_sort"></span><input type="text" name="resources[' + resource_count + '][id]" value="" /></section><section class="developer_resource_callout_title"><input type="text" name="resources[' + resource_count + '][title]" value="" /></section><section class="developer_resource_callout_subtitle"><input type="text" name="resources[' + resource_count + '][subtitle]" value="" /></section><section class="developer_resource_type"><select name="resources[' + resource_count + '][type]" id="type_' + resource_count + '" class="custom_control"><? foreach ($types as $k => $v) { ?><option value="<?=$k?>"><?=$v?></option><? } ?></select><a href="#" tabindex="-1" class="icon_settings" name="' + resource_count + '"></a><input type="hidden" name="resources[' + resource_count + '][options]" value="" id="options_' + resource_count + '" /></section><section class="developer_resource_display_title"><input type="radio" name="display_field" value="" id="display_title_' + resource_count + '" class="custom_control" /></section><section class="developer_resource_action right"><a href="#" tabindex="-1" class="icon_delete"></a></section>');

		$("#resource_table").append(li);
		li.find("select").get(0).customControl = new BigTreeSelect(li.find("select").get(0));
		li.find("input[type=radio]").get(0).customControl = new BigTreeRadioButton(li.find("input[type=radio]").get(0));

		$("#resource_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });

		return false;
	});

	// Handle making sure IDs get tacked on to the display field radio buttons.
	$("input[name=display_field]").live("click",function() {
		// Get the id field
		id = $(this).parents("li").find("input").eq(0).val();
		$(this).val(id);
	});
	$(".developer_resource_callout_id input").live("change",function() {
		$(this).parents("li").find("input[type=radio]").val($(this).val());
	});
	
	$("#resource_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
</script>