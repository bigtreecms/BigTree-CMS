<script>
	var update_timer;
	var search_timer;
	var search = "";
	
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
	
		$(".icon_archive").click(function() {
			id = $(this).attr("href").substr(1);
			$(this).parents("li").remove();
			$.ajax("<?=ADMIN_ROOT?>ajax/dashboard/404/ignore/", { data: { id: id }, type: "POST" });
			BigTree.Growl("404 Report","Ignored 404");
			
			return false;
		});
		
		$(".icon_restore").click(function() {
			id = $(this).attr("href").substr(1);
			$(this).parents("li").remove();
			$.ajax("<?=ADMIN_ROOT?>ajax/dashboard/404/unignore/", { data: { id: id }, type: "POST" });
			BigTree.Growl("404 Report","Unignored 404");
			
			return false;
		});
		
		$(".icon_delete").click(function() {
			new BigTreeDialog("<?=ucwords($delete_action)?> 404",'<p class="confirm">Are you sure you want to delete this 404?',$.proxy(function() {
				id = $(this).attr("href").substr(1);
				$(this).parents("li").remove();
				$.ajax("<?=ADMIN_ROOT?>ajax/dashboard/404/delete/", { data: { id: id }, type: "POST" });
				BigTree.Growl("404 Report","Deleted 404");
			},this),"delete",false,"OK");
			
			return false;
		});
	}
	
	function save404() {
		$.ajax("<?=ADMIN_ROOT?>ajax/dashboard/404/update/", { data: { id: current_editing_id, value: $("#404_" + current_editing_id).val() }, type: "POST" });
	}
	
	function search404() {
		$("#results").load("<?=ADMIN_ROOT?>ajax/dashboard/404/search/", { search: $("#404_search").val(), type: "<?=$type?>" }, hookResults);
		search = $("#404_search").val();
	}
	
	$("#404_search").keyup(function() {
		clearTimeout(search_timer);
		search_timer = setTimeout("search404();",400);
	});
	
	$("#view_paging a").live("click",function() {
		current_page = BigTree.CleanHref($(this).attr("href"));
		if ($(this).hasClass("active") || $(this).hasClass("disabled")) {
			return false;
		}
		$("#results").load("<?=ADMIN_ROOT?>ajax/dashboard/404/search/", { search: search, type: "<?=$type?>", page: current_page }, hookResults);

		return false;
	});
	
	hookResults();
</script>