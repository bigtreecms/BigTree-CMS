<script>
	$("#view_table").change(function(event,data) {
		$("#field_area").load("<?=ADMIN_ROOT?>ajax/developer/load-view-fields/?table=" + data.value);
	});
	
	$(".options").click(function(ev) {
		ev.preventDefault();
		
		// Prevent double clicks
		if (BigTree.Busy) {
			return;
		}

		BigTreeDialog({
			url: "<?=ADMIN_ROOT?>ajax/developer/load-view-options/",
			post: { table: $("#view_table").val(), type: $("#view_type").val(), data: $("#view_options").val() },
			title: "View Options",
			icon: "edit",
			callback: function(data) {
				$("#view_options").val(JSON.stringify(data));
			}
		});
	});
	
	$("#view_type").change(function(event,data) {
		if (data.value == "images" || data.value == "images-grouped") {
			$("#fields").hide();
		} else {
			$("#fields").show();
		}
	});
	
	BigTreeFormValidator("form.module");
</script>