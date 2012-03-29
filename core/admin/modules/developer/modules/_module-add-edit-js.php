<script type="text/javascript">
	new BigTreeFormValidator("form.module");
	
	var gbp_count = <?=count($gbp)?>;
	var goingToPop;
	
	$("#gbp_on").bind("checked:click",function() {
		$("#gbp").toggle();
	});
	
	$(".table_select").live("select:changed",tablePop);
	
	function tablePop(event,data) {
		goingToPop = $(this).parent().siblings("fieldset");
		goingToPop.children("div").load("<?=$admin_root?>ajax/developer/load-table-columns/?table=" + data.value + "&field=" + goingToPop.attr("name"), function() {
			new BigTreeSelect(goingToPop.find("select").get(0));
		});
	}
</script>