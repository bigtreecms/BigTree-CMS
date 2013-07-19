<script>
	new BigTreeFormValidator("form.module");
	
	$("#gbp_on").bind("click",function() {
		$("#gbp").toggle();
	});
	
	$(".container").on("change",".table_select",function(event,data) {
		BigTree.localTablePop = $(this).parent().siblings("fieldset");
		BigTree.localTablePop.children("div").load("<?=ADMIN_ROOT?>ajax/developer/load-table-columns/?table=" + data.value + "&field=" + BigTree.localTablePop.attr("name"));
	});
	
	$(".developer_icon_list a").click(function() {
		$(".developer_icon_list a").removeClass("active");
		$(this).addClass("active");
		$("#selected_icon").val($(this).attr("href").substr(1));
		
		return false;
	});
</script>