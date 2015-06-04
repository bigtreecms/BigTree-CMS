<script>
	BigTreeFormValidator("form.module");

	$("#feed_table").change(function(event,data) {
		$("#field_area").load("<?=ADMIN_ROOT?>ajax/developer/load-feed-fields/?table=" + data.value);
	});
	
	$(".options").click(function(ev) {
		ev.preventDefault();

		// Prevent double clicks
		if (BigTree.Busy) {
			return;
		}

		BigTreeDialog({
			title: "Feed Options",
			url: "<?=ADMIN_ROOT?>ajax/developer/load-feed-options/",
			post: { table: $("#feed_table").val(), type: $("#feed_type").val(), data: $("#feed_options").val() },
			icon: "edit",
			callback: function(data) {
				$("#feed_options").val(JSON.stringify(data));
			}
		});
	});
	
	$("#feed_type").change(function(event,data) {
		if (data.value == "rss" || data.value == "rss2") {
			$("#field_area").hide();
		} else {
			$("#field_area").show();
		}
	});
</script>