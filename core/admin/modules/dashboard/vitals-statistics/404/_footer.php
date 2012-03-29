<script type="text/javascript">
	var current_editing_id = "";
	var update_timer;
	var search_timer;
	
	function hookResults() {
		$(".autosave").keyup(function(ev) {
			clearTimeout(update_timer);
			current_editing_id = $(this).attr("name");
			if (ev.keyCode != 9) {
				update_timer = setTimeout("save404();",500);
			}
		});
		$(".autosave").blur(function() {
			current_editing_id = $(this).attr("name");
			save404();
		});
	
		$(".icon_delete").click(function() {
			new BigTreeDialog("Delete Callout",'<p class="confirm">Are you sure you want to delete this callout?',$.proxy(function() {
				id = $(this).attr("href").substr(1);
				$(this).parents("li").remove();
				$.ajax("<?=$admin_root?>ajax/dashboard/404/<?=$delete_action?>/", { data: { id: id }, type: "POST" });
			},this),"delete",false,"OK");
			
			return false;
		});
	}
	
	function save404() {
		$.ajax("<?=$admin_root?>ajax/dashboard/404/update/", { data: { id: current_editing_id, value: $("#404_" + current_editing_id).val() }, type: "POST" });
	}
	
	function search404() {
		$("#results").load("<?=$admin_root?>ajax/dashboard/404/search/", { search: $("#404_search").val(), type: "<?=$type?>" }, hookResults);
	}
	
	$("#404_search").keyup(function() {
		clearTimeout(search_timer);
		search_timer = setTimeout("search404();",400);
	});
	
	hookResults();
</script>