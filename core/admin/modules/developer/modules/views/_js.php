<?php
	namespace BigTree;
?>
<script>
	$("#view_table").change(function(event,data) {
		$("#field_area").load("<?=ADMIN_ROOT?>ajax/developer/load-view-fields/?table=" + data.value + "&type=" + $("#view_type").val());
	});
	
	$(".icon_settings").click(function(ev) {
		ev.preventDefault();
		
		// Prevent double clicks
		if (BigTree.Busy) {
			return;
		}

		BigTreeDialog({
			url: "<?=ADMIN_ROOT?>ajax/developer/load-view-settings/",
			post: { table: $("#view_table").val(), type: $("#view_type").val(), data: $("#view_settings").val() },
			title: "<?=Text::translate("View Settings", true)?>",
			icon: "edit",
			callback: function(data) {
				$("#view_settings").val(JSON.stringify(data));
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