<?php
	$cached_types = $admin->getCachedFieldTypes(true);
	$types = $cached_types["callouts"];
?>
<script>
	(function() {
		var CurrentFieldKey = false;
		var FieldCount = <?=$x?>;

		BigTreeFormValidator("form.module");		
		
		$("#field_table").on("blur", ".developer_field_id input", function() {
			$(this).parents("li").find(".developer_field_display_title input").val($(this).val());
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
				title: "Field Options",
				url: "<?=ADMIN_ROOT?>ajax/developer/load-field-options/",
				post: { callout: "true", type: $("#type_" + key).val(), data: $("#options_" + key).val() },
				icon: "edit",
				callback: function(data) {
					$("#options_" + CurrentFieldKey).val(JSON.stringify(data));
				}
			});
			
		}).on("click",".icon_delete",function() {
			BigTreeDialog({
				title: "Delete field",
				content: '<p class="confirm">Are you sure you want to delete this field?</p>',
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
		}).on("change",".developer_field_callout_id input",function() {
			$(this).parents("li").find("input[type=radio]").val($(this).val());
		});
			
		$(".add_field").click(function() {
			FieldCount++;
			
			var li = $('<li id="row_' + FieldCount + '">');
			li.html('<section class="developer_field_callout_id"><span class="icon_sort"></span><input type="text" name="fields[' + FieldCount + '][id]" value="" /></section><section class="developer_field_callout_title"><input type="text" name="fields[' + FieldCount + '][title]" value="" /></section><section class="developer_field_callout_subtitle"><input type="text" name="fields[' + FieldCount + '][subtitle]" value="" /></section><section class="developer_field_type"><select name="fields[' + FieldCount + '][type]" id="type_' + FieldCount + '"><optgroup label="Default"><? foreach ($types["default"] as $k => $v) { ?><option value="<?=$k?>"><?=$v["name"]?></option><? } ?></optgroup><? if (count($types["custom"])) { ?><optgroup label="Custom"><? foreach ($types["custom"] as $k => $v) { ?><option value="<?=$k?>"><?=$v["name"]?></option><? } ?></optgroup><? } ?></select><a href="#" tabindex="-1" class="icon_settings" name="' + FieldCount + '"></a><input type="hidden" name="fields[' + FieldCount + '][options]" value="" id="options_' + FieldCount + '" /></section><section class="developer_field_display_title"><input type="radio" name="display_field" value="" id="display_title_' + FieldCount + '" /></section><section class="developer_field_action right"><a href="#" tabindex="-1" class="icon_delete"></a></section>');
	
			$("#field_table").append(li);
			li.find("select").get(0).customControl = new BigTreeSelect(li.find("select").get(0));
			li.find("input[type=radio]").get(0).customControl = new BigTreeRadioButton(li.find("input[type=radio]").get(0));
	
			$("#field_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
	
			return false;
		});
		
		$("#field_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
	})();
</script>