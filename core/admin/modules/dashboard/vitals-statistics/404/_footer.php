<script>
	BigTree.localSaveTimer = false;
	BigTree.localSearchTimer = false;
	BigTree.localCurrentField = false;
	
	BigTree.localHooks = function() {
		$(".autosave").keyup(function(ev) {
			clearTimeout(BigTree.localSaveTimer);
			BigTree.localCurrentField = $(this).attr("name");
			if (ev.keyCode != 9) {
				BigTree.localSaveTimer = setTimeout("BigTree.localSave();",500);
			}
		});
		$(".autosave").blur(function() {
			BigTree.localCurrentField = $(this).attr("name");
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
	};
	
	BigTree.localSave = function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/dashboard/404/update/", { data: { id: BigTree.localCurrentField, value: $("#404_" + BigTree.localCurrentField).val() }, type: "POST" });
	};

	BigTree.localSearch = function() {
		$("#results").load("<?=ADMIN_ROOT?>ajax/dashboard/404/search/", { search: $("#404_search").val(), type: "<?=$type?>" }, BigTree.localHooks);
	};
	
	$("#404_search").keyup(function() {
		clearTimeout(BigTree.localSearchTimer);
		BigTree.localSearchTimer = setTimeout("BigTree.localSearch();",400);
	});
	
	$(".table").on("click","#view_paging a",function() {
		if ($(this).hasClass("active") || $(this).hasClass("disabled")) {
			return false;
		}
		$("#results").load("<?=ADMIN_ROOT?>ajax/dashboard/404/search/", { search: $("#404_search").val(), type: "<?=$type?>", page: BigTree.CleanHref($(this).attr("href")) }, BigTree.localHooks);

		return false;
	});
	
	BigTree.localHooks();
</script>