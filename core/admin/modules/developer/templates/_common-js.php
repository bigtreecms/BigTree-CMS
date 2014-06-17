<?
	$cached_types = $admin->getCachedFieldTypes();
	$types = $cached_types["templates"];
?>
<script>
	new BigTreeFormValidator("form.module");
	
	BigTree.localCurrentField = false;
	BigTree.localResourceCount = <?=$x?>;
	
	$(".form_table").on("click",".icon_settings",function() {
		BigTree.localCurrentField = $(this).attr("name");
		
		$.ajax("<?=ADMIN_ROOT?>ajax/developer/load-field-options/", { type: "POST", data: { template: "true", type: $("#type_" + BigTree.localCurrentField).val(), data: $("#options_" + BigTree.localCurrentField).val() }, complete: function(response) {
			new BigTreeDialog("Field Options",response.responseText,function(data) {
				$("#options_" + BigTree.localCurrentField).val(JSON.stringify(data));
			});
		}});
		
		return false;
	}).on("click",".icon_delete",function() {
		new BigTreeDialog("Delete Resource",'<p class="confirm">Are you sure you want to delete this resource?',$.proxy(function() {
			$(this).parents("li").remove();
		},this),"delete",false,"OK");
		
		return false;
	});
	
	$(".add_resource").click(function() {
		BigTree.localResourceCount++;
		
		var li = $('<li>').html('<section class="developer_resource_id"><span class="icon_sort"></span><input type="text" name="resources[' + BigTree.localResourceCount + '][id]" value="" /></section><section class="developer_resource_title"><input type="text" name="resources[' + BigTree.localResourceCount + '][title]" value="" /></section><section class="developer_resource_subtitle"><input type="text" name="resources[' + BigTree.localResourceCount + '][subtitle]" value="" /></section><section class="developer_resource_type"><select name="resources[' + BigTree.localResourceCount + '][type]" id="type_' + BigTree.localResourceCount + '"><? foreach ($types as $k => $v) { ?><option value="<?=$k?>"><?=$v["name"]?></option><? } ?></select><a href="#" tabindex="-1" class="icon_settings" name="' + BigTree.localResourceCount + '"></a><input type="hidden" name="resources[' + BigTree.localResourceCount + '][options]" value="" id="options_' + BigTree.localResourceCount + '" /></section><section class="developer_resource_action right"><a href="#" tabindex="-1" class="icon_delete"></a></section>');

		$("#resource_table").append(li)
							.sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
		
		BigTreeCustomControls(li);
		return false;
	});
	
	$("#resource_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
</script>