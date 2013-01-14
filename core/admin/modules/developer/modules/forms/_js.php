<script>
	$("#form_table").change(function(event,data) {
		$("#field_area").load("<?=ADMIN_ROOT?>ajax/developer/load-form/", { table: data.value }, _local_hooks);
		$("#create").show();
	});
	
	$(".icon_settings").live("click",function() {
		key = $(this).attr("name");
		current_editing_key = key;
		
		$.ajax("<?=ADMIN_ROOT?>ajax/developer/load-field-options/", { type: "POST", data: { type: $("#type_" + key).val(), data: $("#options_" + key).val() }, complete: function(response) {
			new BigTreeDialog("Field Options",response.responseText,function(data) {
				$.ajax("<?=ADMIN_ROOT?>ajax/developer/save-field-options/?key=" + current_editing_key, { type: "POST", data: data });
			});
		}});
		
		return false;
	});
		
	$(".icon_delete").live("click",function() {
		li = $(this).parents("li");
		title = li.find("input").val();
		if (title) {
			key = $(this).attr("name");
			if (key != "geocoding") {
				fieldSelect.addField(key,title);
			}
		}
		li.remove();
		return false;
	});
	
	$(".add_geocoding").click(function() {
		li = $('<li id="row_geocoding">');
		li.html('<section class="developer_resource_form_title"><span class="icon_sort"></span><input type="text" name="titles[geocoding]" value="Geocoding" disabled="disabled" /></section><section class="developer_resource_form_subtitle"><input type="text" name="subtitles[geocoding]" value="" disabled="disabled" /></section><section class="developer_resource_type"><input name="type[geocoding]" id="type_geocoding" type="hidden" value="geocoding" /><span class="resource_name">Geocoding</span><a href="#" class="options icon_settings" name="geocoding"></a><input type="hidden" name="options[geocoding]" value="" id="options_geocoding" /></section><section class="developer_resource_action"><a href="#" class="icon_delete" name="geocoding"></a></section>');
		
		$("#resource_table").append(li);
		_local_hooks();
		li.find(".icon_settings").trigger("click");
		
		return false;
	});
	
	$(".add_many_to_many").click(function() {
		mtm_count++;
			
		li = $('<li id="mtm_row_' + mtm_count + '">');
		li.html('<section class="developer_resource_form_title"><span class="icon_sort"></span><input type="text" name="titles[mtm_' + mtm_count + ']" value="" /></section><section class="developer_resource_form_subtitle"><input type="text" name="subtitles[mtm_' + mtm_count + ']" value="" /></section><section class="developer_resource_type"><input name="type[mtm_' + mtm_count + ']" id="type_mtm_' + mtm_count + '" type="hidden" value="many_to_many" /><span class="resource_name">Many To Many</span><a href="#" class="options icon_settings" name="mtm_' + mtm_count + '"></a><input type="hidden" name="options[mtm_' + mtm_count + ']" value="" id="options_mtm_' + mtm_count + '" /></section><section class="developer_resource_action"><a href="#" class="icon_delete" name="mtm_' + mtm_count + '"></a></section>');
		
		$("#resource_table").append(li);
		_local_hooks();
		li.find(".icon_settings").trigger("click");
		
		return false;
	});
	
	function _local_hooks() {
		$("#resource_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
		BigTreeCustomControls();
	}
	
	_local_hooks();
	
	new BigTreeFormValidator("form.module");
</script>	