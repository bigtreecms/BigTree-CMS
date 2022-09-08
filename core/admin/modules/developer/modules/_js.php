<script>
	(function() {
		BigTreeFormValidator("form.module");
		
		$("#gbp_on").bind("click",function() {
			$("#gbp").toggle();
		});
		
		$("#graphql").on("click", function() {
			$("#graphql_type_wrapper").toggle();
		});
		
		$("#class_name").on("keyup", function() {
			if ($(this).val()) {
				$("#graphql_wrapper").show();
			} else {
				$("#graphql_wrapper").hide();
			}
		});
		
		$(".container").on("change",".table_select",function(event) {
			var target = $(this).data("pop-target");
			var name = $(this).data("pop-name");

			$(target).load("<?=ADMIN_ROOT?>ajax/developer/load-table-columns/?table=" + $(this).val() + "&field=" + name);
		});
		
		$(".developer_icon_list a").click(function() {
			$(".developer_icon_list a").removeClass("active");
			$(this).addClass("active");
			$("#selected_icon").val($(this).attr("href").substr(1));
			
			return false;
		});
	})();
</script>