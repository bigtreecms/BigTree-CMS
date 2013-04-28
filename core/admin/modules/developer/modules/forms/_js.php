<script>
	BigTree.localCurrentField = false;
	BigTree.localHooks = function() {
		$("#resource_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
		BigTreeCustomControls();
	};

	$("#form_table").change(function(event,data) {
		$("#field_area").load("<?=ADMIN_ROOT?>ajax/developer/load-form/", { table: data.value }, BigTree.localHooks);
		$("#create").show();
	});
	
	$("#field_area").on("click",".icon_settings",function() {
		key = $(this).attr("name");
		BigTree.localCurrentField = key;
		
		$.ajax("<?=ADMIN_ROOT?>ajax/developer/load-field-options/", { type: "POST", data: { type: $("#type_" + key).val(), data: $("#options_" + key).val() }, complete: function(response) {
			new BigTreeDialog("Field Options",response.responseText,function(data) {
				$.ajax("<?=ADMIN_ROOT?>ajax/developer/save-field-options/?key=" + BigTree.localCurrentField, { type: "POST", data: data });
			});
		}});
		
		return false;
	}).on("click",".icon_delete",function() {
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
		BigTree.localHooks();
		li.find(".icon_settings").trigger("click");
		
		return false;
	});
	
	$(".add_many_to_many").click(function() {
		BigTree.localMTMCount++;
			
		li = $('<li id="mtm_row_' + BigTree.localMTMCount + '">');
		li.html('<section class="developer_resource_form_title"><span class="icon_sort"></span><input type="text" name="titles[mtm_' + BigTree.localMTMCount + ']" value="" /></section><section class="developer_resource_form_subtitle"><input type="text" name="subtitles[mtm_' + BigTree.localMTMCount + ']" value="" /></section><section class="developer_resource_type"><input name="type[mtm_' + BigTree.localMTMCount + ']" id="type_mtm_' + BigTree.localMTMCount + '" type="hidden" value="many-to-many" /><span class="resource_name">Many To Many</span><a href="#" class="options icon_settings" name="mtm_' + BigTree.localMTMCount + '"></a><input type="hidden" name="options[mtm_' + BigTree.localMTMCount + ']" value="" id="options_mtm_' + BigTree.localMTMCount + '" /></section><section class="developer_resource_action"><a href="#" class="icon_delete" name="mtm_' + BigTree.localMTMCount + '"></a></section>');
		
		$("#resource_table").append(li);
		BigTree.localHooks();
		li.find(".icon_settings").trigger("click");
		
		return false;
	});
	
	BigTree.localHooks();
	new BigTreeFormValidator("form.module");
</script>	