<script>
	BigTreeFormValidator("form.module");

	$("#feed_table").change(function(event) {
		$("#field_area").load("<?=ADMIN_ROOT?>ajax/developer/load-feed-fields/?table=" + $(this).val());
	});
	
	$(".icon_settings").click(function(ev) {
		ev.preventDefault();

		// Prevent double clicks
		if (BigTree.Busy) {
			return;
		}

		$.ajax("<?=ADMIN_ROOT?>ajax/developer/load-feed-settings/", { type: "POST", data: { table: $("#feed_table").val(), type: $("#feed_type").val(), data: $("#feed_settings").val() }, complete: function(response) {
			BigTreeDialog({
				title: "Feed Settings",
				content: response.responseText,
				icon: "edit",
				callback: function(data) {
					$("#feed_settings").val(JSON.stringify(data));
				}
			});
		}});
	});
	
	$("#feed_type").change(function(event) {
		if ($(this).val() == "rss" || $(this).val() == "rss2") {
			$("#field_area").hide();
		} else {
			$("#field_area").show();
		}
	});
</script>