<script>
	BigTree.localReportType = "<?=$type?>";
	BigTree.localCurrentField = false;
	BigTree.localHooks = function() {
		$("#field_table, #filter_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
		BigTreeCustomControls();
	};

	$("#report_table").change(function(event,data) {
		$("#field_area").load("<?=ADMIN_ROOT?>ajax/developer/load-report/", { table: data.value, report_type: $("#report_type").val() }, BigTree.localHooks);
		$("#create").show();
	});

	$("#report_type").change(function(event,data) {
		var v = $(this).val();
		if (v == "csv") {
			$("#filtered_view").hide();
			$("#field_table").show();
		} else {
			$("#filtered_view").show();
			$("#field_table").hide();
		}
	});
	
	$("#field_area").on("click","#field_table .icon_delete",function() {
		var li = $(this).parents("li");
		BigTree.localFieldSelect.addField($(this).attr("name"),li.find("input").val());
		li.remove();
		return false;
	}).on("click","#filter_table .icon_delete",function() {
		var li = $(this).parents("li");
		BigTree.localFilterSelect.addField($(this).attr("name"),li.find("input").val());
		li.remove();
		return false;
	});
	
	BigTree.localHooks();
	BigTreeFormValidator("form.module");
</script>	