<?php
	namespace BigTree;
?>
<script>
	var Form = (function() {
		var FieldArea = $("#field_area");
		var FieldKey = false;
		var FieldSelect;
		var MTMCount = 0;
		
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
		
		function setFieldSelect(fs) {
			FieldSelect = fs;
		}
		
		function setMTMCount(count) {
			MTMCount = count;
		}
		
		$("#form_table").change(function(event,data) {
			$("#field_area").load("<?=ADMIN_ROOT?>ajax/developer/load-form/", { table: data.value }, hooks);
			$("#create").show();
		});
		
		$("#manage_hooks").click(function() {
			var data = $.parseJSON($("#form_hooks").val());
			var html = '<fieldset><label><?=Text::translate("Editing Hook")?></label><input type="text" name="edit" value="' + htmlspecialchars(data.edit ? data.edit : "") + '" /></fieldset>';
			
			html += '<fieldset><label><?=Text::translate("Pre-processing Hook")?></label><input type="text" name="pre" value="' + htmlspecialchars(data.pre ? data.pre : "") + '" /></fieldset>';
			html += '<fieldset><label><?=Text::translate("Post-processing Hook")?></label><input type="text" name="post" value="' + htmlspecialchars(data.post ? data.post : "") + '" /></fieldset>';
			html += '<fieldset><label><?=Text::translate("Publishing Hook")?></label><input type="text" name="publish" value="' + htmlspecialchars(data.publish ? data.publish : "") + '" /></fieldset>';
			
			BigTreeDialog({
				title: "<?=Text::translate("Manage Hooks", true)?>",
				content: html,
				helpLink: "http://www.bigtreecms.org/docs/dev-guide/modules/advanced-techniques/form-hooks/",
				icon: "edit",
				callback: function(data) {
					$("#form_hooks").val(JSON.stringify(data));
				}
			});
			
			return false;
		});
		
		FieldArea.on("click",".icon_settings",function(ev) {
			ev.preventDefault();
			
			// Prevent double clicks
			if (BigTree.Busy) {
				return;
			}
			
			FieldKey = $(this).attr("name");
			
			BigTreeDialog({
				title: "<?=Text::translate("Field Options", true)?>",
				url: "<?=ADMIN_ROOT?>ajax/developer/load-field-options/",
				post: { table: $("#form_table").val(), type: $("#type_" + FieldKey).val(), data: $("#options_" + FieldKey).val() },
				icon: "edit",
				callback: function(data) {
					$("#options_" + FieldKey).val(JSON.stringify(data));
				}
			});
			
		}).on("click",".icon_delete",function() {
			var li = $(this).parents("li");
			var title = li.find("input").val();
			var key = $(this).attr("name");

			if (title && key !== "__geocoding__" && key.indexOf("__mtm-") !== 0) {
				FieldSelect.addField(key, title);
			}
			
			li.remove();
			
			return false;
		});
		
		FieldArea.on("click",".add_geocoding",function() {
			var li = $('<li id="row_geocoding">');
			
			li.html('<section class="developer_resource_form_title"><span class="icon_sort"></span><input type="text" name="fields[__geocoding__][title]" value="<?=Text::translate("Geocoding", true)?>" disabled="disabled" /></section>' +
				'<section class="developer_resource_form_subtitle"><input type="text" name="fields[__geocoding__][subtitle]" value="" disabled="disabled" /></section>' +
				'<section class="developer_resource_type"><input name="fields[__geocoding__][type]" id="type_geocoding" type="hidden" value="geocoding" /><span class="resource_name"><?=Text::translate("Geocoding", true)?></span><a href="#" class="options icon_settings" name="geocoding"></a><input type="hidden" name="fields[__geocoding__][options]" value="" id="options_geocoding" /></section>' +
				'<section class="developer_resource_action"><a href="#" class="icon_delete" name="geocoding"></a></section>');
			
			$("#resource_table").append(li);
			hooks();
			li.find(".icon_settings").trigger("click");
			
			return false;
		});
		
		FieldArea.on("click",".add_many_to_many",function() {
			var li = $('<li id="mtm_row_' + MTMCount + '">');
			
			MTMCount++;
			li.html('<section class="developer_resource_form_title"><span class="icon_sort"></span><input type="text" name="fields[__mtm-' + MTMCount + '__][title]" value="" /></section>' +
				'<section class="developer_resource_form_subtitle"><input type="text" name="fields[__mtm-' + MTMCount + '__][subtitle]" value="" /></section>' +
				'<section class="developer_resource_type"><input name="fields[__mtm-' + MTMCount + '__][type]" id="type___mtm-' + MTMCount + '__" type="hidden" value="many-to-many" /><span class="resource_name"><?=Text::translate("Many To Many", true)?></span><a href="#" class="options icon_settings" name="__mtm-' + MTMCount + '__"></a><input type="hidden" name="fields[__mtm-' + MTMCount + '__][options]" value="" id="options___mtm-' + MTMCount + '__" /></section>' +
				'<section class="developer_resource_action"><a href="#" class="icon_delete" name="__mtm-' + MTMCount + '__"></a></section>');
			
			$("#resource_table").append(li);
			hooks();
			li.find(".icon_settings").trigger("click");
			
			return false;
		});
		
		hooks();
		BigTreeFormValidator("form.module");
		
		return { FieldKey: FieldKey, MTMCount: MTMCount, hooks: hooks, setFieldSelect: setFieldSelect, setMTMCount: setMTMCount };
	})();
</script>	