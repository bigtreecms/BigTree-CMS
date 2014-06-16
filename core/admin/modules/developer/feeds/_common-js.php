<script>
	new BigTreeFormValidator("form.module");

	$("#feed_table").change(function(event,data) {
		$("#field_area").load("<?=ADMIN_ROOT?>ajax/developer/load-feed-fields/?table=" + data.value);
	});
	
	$(".options").click(function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/developer/load-feed-options/", { type: "POST", data: { table: $("#feed_table").val(), type: $("#feed_type").val(), data: $("#feed_options").val() }, complete: function(response) {
			new BigTreeDialog("Feed Options",response.responseText,function(data) {
				$("#feed_options").val(JSON.stringify(data));
			});
		}});
		return false;
	});
	
	$("#feed_type").change(function(event,data) {
		if (data.value == "rss" || data.value == "rss2") {
			$("#field_area").hide();
		} else {
			$("#field_area").show();
		}
	});
</script>