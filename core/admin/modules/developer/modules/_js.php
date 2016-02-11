<script>
	(function() {
		BigTreeFormValidator("form.module");
		
		$("#gbp_on").bind("click",function() {
			$("#gbp").toggle();
		});
		
		$(".container").on("change",".table_select",function(event,data) {
			var target = $(this).data("pop-target");
			var name = $(this).data("pop-name");

			$(target).load("<?=ADMIN_ROOT?>ajax/developer/load-table-columns/?table=" + data.value + "&field=" + name);
		});
		
		$(".developer_icon_list a").click(function() {
			$(".developer_icon_list a").removeClass("active");
			$(this).addClass("active");
			$("#selected_icon").val($(this).attr("href").substr(1));
			
			return false;
		});
	})();
</script>