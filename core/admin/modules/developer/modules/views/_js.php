<script type="text/javascript">
	$("#view_table").bind("select:changed",function(event,data) {
		$("#field_area").load("<?=$admin_root?>ajax/developer/load-view-fields/?table=" + data.value);
	});
	
	$(".options").click(function() {
		$.ajax("<?=$admin_root?>ajax/developer/load-view-options/", { type: "POST", data: { table: $("#view_table").val(), type: $("#view_type").val(), data: $("#view_options").val() }, complete: function(response) {
			new BigTreeDialog("View Options",response.responseText,function(data) {
				$.ajax("<?=$admin_root?>ajax/developer/save-view-options/", { type: "POST", data: data });
			});
		}});
		
		return false;
	});
	
	$("#view_type").bind("select:changed",function(event,data) {
		if (data.value == "images" || data.value == "images-grouped") {
			$("#fields").hide();
		} else {
			$("#fields").show();
		}
	});
</script>