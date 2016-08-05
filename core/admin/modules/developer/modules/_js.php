<script>
	(function() {
		var GBPCheckbox = $("#gbp_on");
		var GBPControl = GBPCheckbox.get(0).customControl;
		var GBPSection = $("#gbp");
		var IconList = $(".developer_icon_list a");
		
		BigTreeFormValidator("form.module");
		
		GBPCheckbox.on("click",function() {
			GBPSection.toggle();
		});

		$("#developer_only").on("click",function() {
			if ($(this).prop("checked")) {
				GBPControl.disable();
				GBPSection.hide();
			} else {
				GBPControl.enable();
				if (GBPCheckbox.prop("checked")) {
					GBPSection.show();
				}
			}
		});
		
		$(".container").on("change",".table_select",function(event,data) {
			var target = $(this).data("pop-target");
			var name = $(this).data("pop-name");

			$(target).load("<?=ADMIN_ROOT?>ajax/developer/load-table-columns/?table=" + data.value + "&field=" + name);
		});
		
		IconList.click(function() {
			IconList.removeClass("active");
			$(this).addClass("active");
			$("#selected_icon").val($(this).attr("href").substr(1));
			
			return false;
		});
	})();
</script>