<?
	$cached_types = $admin->getCachedFieldTypes(true);
	$types = $cached_types["templates"];
?>
<script>
	(function() {
		var CurrentField = false;
		var ResourceCount = <?=$x?>;

		BigTreeFormValidator("form.module");
		$(".form_table").on("click",".icon_settings",function(ev) {
			ev.preventDefault();

			// Prevent double clicks
			if (BigTree.Busy) {
				return;
			}

			CurrentField = $(this).attr("name");
			
			$.ajax("<?=ADMIN_ROOT?>ajax/developer/load-field-options/", { type: "POST", data: { template: "true", type: $("#type_" + CurrentField).val(), data: $("#options_" + CurrentField).val() }, complete: function(response) {
				BigTreeDialog({
					title: "Field Options",
					content: response.responseText,
					icon: "edit",
					callback: function(data) {
						$("#options_" + CurrentField).val(JSON.stringify(data));
					}
				});
			}});
			
		}).on("click",".icon_delete",function(ev) {
			ev.preventDefault();
			BigTreeDialog({
				title: "Delete Resource",
				content: '<p class="confirm">Are you sure you want to delete this resource?</p>',
				icon: "delete",
				alternateSaveText: "OK",
				callback: $.proxy(function() { $(this).parents("li").remove(); },this)
			});
		});
		
		$(".add_resource").click(function(ev) {
			ev.preventDefault();
			ResourceCount++;
			
			var li = $('<li>').html('<section class="developer_resource_id"><span class="icon_sort"></span><input type="text" name="resources[' + ResourceCount + '][id]" value="" /></section><section class="developer_resource_title"><input type="text" name="resources[' + ResourceCount + '][title]" value="" /></section><section class="developer_resource_subtitle"><input type="text" name="resources[' + ResourceCount + '][subtitle]" value="" /></section><section class="developer_resource_type"><select name="resources[' + ResourceCount + '][type]" id="type_' + ResourceCount + '"><optgroup label="Default"><? foreach ($types["default"] as $k => $v) { ?><option value="<?=$k?>"><?=$v["name"]?></option><? } ?></optgroup><? if (count($types["custom"])) { ?><optgroup label="Custom"><? foreach ($types["custom"] as $k => $v) { ?><option value="<?=$k?>"><?=$v["name"]?></option><? } ?></optgroup><? } ?></select><a href="#" tabindex="-1" class="icon_settings" name="' + ResourceCount + '"></a><input type="hidden" name="resources[' + ResourceCount + '][options]" value="" id="options_' + ResourceCount + '" /></section><section class="developer_resource_action right"><a href="#" tabindex="-1" class="icon_delete"></a></section>');	
			$("#resource_table").append(li)
								.sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
			
			BigTreeCustomControls(li);
		});
		
		$("#resource_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });

	})();
</script>