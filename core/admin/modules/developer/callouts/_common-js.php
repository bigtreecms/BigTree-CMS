<?php
	namespace BigTree;
	
	/**
	 * @global array $field_types
	 * @global int $field_count
	 */
?>
<script>
	(function() {
		var CurrentFieldKey = false;
		var FieldCount = <?=$field_count?>;
		var FieldTable = $("#field_table");

		BigTreeFormValidator("form.module");		
		
		FieldTable.on("blur", ".developer_resource_id input", function() {
			$(this).parents("li").find(".developer_resource_display_title input").val($(this).val());
		});

		$(".clear_label").click(function(ev) {
			ev.preventDefault();
			$("input[name=display_field]:checked").get(0).customControl.clear();
		});

		$(".form_table").on("click",".icon_settings",function(ev) {
			ev.preventDefault();
	
			// Prevent double clicks
			if (BigTree.Busy) {
				return;
			}
	
			var key = $(this).attr("name");
			CurrentFieldKey = key;

			BigTreeDialog({
				title: "<?=Text::translate("Field Settings", true)?>",
				url: "<?=ADMIN_ROOT?>ajax/developer/load-field-settings/",
				post: { callout: "true", type: $("#type_" + key).val(), data: $("#settings" + key).val() },
				icon: "edit",
				callback: function(data) {
					$("#settings_" + CurrentFieldKey).val(JSON.stringify(data));
				}
			});
			
		}).on("click",".icon_delete",function() {
			BigTreeDialog({
				title: "<?=Text::translate("Delete Field")?>",
				content: '<p class="confirm"><?=Text::translate("Are you sure you want to delete this field?", true)?></p>',
				icon: "delete",
				alternateSaveText: "<?=Text::translate("OK")?>",
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
			
		$(".add_field").click(function() {
			FieldCount++;
			
			var li = $('<li id="row_' + FieldCount + '">');
			li.html('<section class="developer_resource_callout_id">' +
						'<span class="icon_sort"></span>' +
						'<input type="text" name="fields[' + FieldCount + '][id]" value="" />' +
					'</section>' + 
					'<section class="developer_resource_callout_title">' +
						'<input type="text" name="fields[' + FieldCount + '][title]" value="" />' +
					'</section>' + 
					'<section class="developer_resource_callout_subtitle">' +
						'<input type="text" name="fields[' + FieldCount + '][subtitle]" value="" />' +
					'</section>' +
					'<section class="developer_resource_type">' +
						'<select name="fields[' + FieldCount + '][type]" id="type_' + FieldCount + '">' +
							'<optgroup label="Default">' +
								<?php foreach ($field_types["default"] as $k => $v) { ?>
								'<option value="<?=$k?>"><?=$v["name"]?></option>' +
								<?php } ?>
							'</optgroup>' + 
							<?php if (count($field_types["custom"])) { ?>
							'<optgroup label="Custom">' +
								<?php foreach ($field_types["custom"] as $k => $v) { ?>
								'<option value="<?=$k?>"><?=$v["name"]?></option>' +
								<?php } ?>
							'</optgroup>' +
							<?php } ?>
						'</select>' +
						'<a href="#" tabindex="-1" class="icon_settings" name="' + FieldCount + '"></a>' +
						'<input type="hidden" name="fields[' + FieldCount + '][settings]" value="" id="settings_' + FieldCount + '" />' +
					'</section>' +
					'<section class="developer_resource_display_title">' +
						'<input type="radio" name="display_field" value="" id="display_title_' + FieldCount + '" />' +
					'</section>' +
					'<section class="developer_resource_action right">' +
						'<a href="#" tabindex="-1" class="icon_delete"></a>' +
					'</section>');
	
			FieldTable.append(li);
			li.find("select").get(0).customControl = new BigTreeSelect(li.find("select").get(0));
			li.find("input[type=radio]").get(0).customControl = new BigTreeRadioButton(li.find("input[type=radio]").get(0));
	
			FieldTable.sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
	
			return false;
		});
		
		FieldTable.sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
	})();
</script>