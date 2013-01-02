<script>
	new BigTreeFormValidator("form.module");
	
	var gbp_count = <?=count($gbp)?>;
	var goingToPop;
	
	$("#gbp_on").bind("click",function() {
		$("#gbp").toggle();
	});
	
	$(".table_select").live("change",tablePop);
	
	function tablePop(event,data) {
		goingToPop = $(this).parent().siblings("fieldset");
		goingToPop.children("div").load("<?=ADMIN_ROOT?>ajax/developer/load-table-columns/?table=" + data.value + "&field=" + goingToPop.attr("name"), function() {
			new BigTreeSelect(goingToPop.find("select").get(0));
		});
	}
	
	$(".developer_icon_list a").click(function() {
		$(".developer_icon_list a").removeClass("active");
		$(this).addClass("active");
		$("#selected_icon").val($(this).attr("href").substr(1));
		
		return false;
	});
</script>