<script>
	$("#view_table").change(function(event,data) {
		$("#field_area").load("<?=ADMIN_ROOT?>ajax/developer/load-view-fields/?table=" + data.value);
	});
	
	$(".options").click(function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/developer/load-view-options/", { type: "POST", data: { table: $("#view_table").val(), type: $("#view_type").val(), data: $("#view_options").val() }, complete: function(response) {
			new BigTreeDialog("View Options",response.responseText,function(data) {
				$("#view_options").val(JSON.stringify(data));
			});
		}});
		
		return false;
	});
	
	$("#view_type").change(function(event,data) {
		if (data.value == "images" || data.value == "images-grouped") {
			$("#fields").hide();
		} else {
			$("#fields").show();
		}
	});
	
	new BigTreeFormValidator("form.module");
</script>