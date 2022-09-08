<script>
	$("#view_table").change(function(event) {
		$("#field_area").load("<?=ADMIN_ROOT?>ajax/developer/load-view-fields/?table=" + $(this).val() + "&type=" + $("#view_type").val());
	});
	
	$(".js-view-settings").click(function(ev) {
		ev.preventDefault();
		
		// Prevent double clicks
		if (BigTree.Busy) {
			return;
		}

		$.ajax("<?=ADMIN_ROOT?>ajax/developer/load-view-settings/", { type: "POST", data: { table: $("#view_table").val(), type: $("#view_type").val(), data: $("#view_settings").val() }, complete: function(response) {
			BigTreeDialog({
				title: "View Settings",
				content: response.responseText,
				icon: "edit",
				callback: function(data) {
					$("#view_settings").val(JSON.stringify(data));
				}
			});
		}});
	});
	
	$("#view_type").change(function(event) {
		if ($(this).val() == "images" || $(this).val() == "images-grouped") {
			$("#fields").hide();
		} else {
			$("#fields").show();
		}
	});
	
	BigTreeFormValidator("form.module");
</script>