<script>
	(function() {
		var SaveTimer = false;
		var SearchTimer = false;
		var CurrentField = false;

		function hooks() {
			$(".autosave").keyup(function(ev) {
				clearTimeout(SaveTimer);
				CurrentField = $(this).attr("name");
				
				if (ev.keyCode != 9) {
					SaveTimer = setTimeout(save, 500);
				}
			}).blur(function() {
				$.secureAjax("<?=ADMIN_ROOT?>ajax/dashboard/404/update/", {
					data: { id: CurrentField, value: $("#404_" + CurrentField).val() }, 
					type: "POST" 
				});
				CurrentField = $(this).attr("name");
			});
		
			$(".icon_archive").click(function() {
				var id = $(this).attr("href").substr(1);
				
				$(this).parents("li").remove();
				$.secureAjax("<?=ADMIN_ROOT?>ajax/dashboard/404/ignore/", { data: { id: id }, type: "POST" });
				BigTree.growl("404 Report","Ignored 404");
				
				return false;
			});
			
			$(".icon_restore").click(function() {
				var id = $(this).attr("href").substr(1);
				
				$(this).parents("li").remove();
				$.secureAjax("<?=ADMIN_ROOT?>ajax/dashboard/404/unignore/", { data: { id: id }, type: "POST" });
				BigTree.growl("404 Report","Unignored 404");
				
				return false;
			});
			
			$(".icon_delete").click(function() {
				BigTreeDialog({
					title: "<?=ucwords($delete_action)?> 404",
					content: '<p class="confirm">Are you sure you want to delete this 404?</p>',
					icon: "delete",
					alternateSaveText: "OK",
					callback: $.proxy(function() {
						id = $(this).attr("href").substr(1);
						$(this).parents("li").remove();
						$.secureAjax("<?=ADMIN_ROOT?>ajax/dashboard/404/delete/", { data: { id: id }, type: "POST" });
						BigTree.growl("404 Report","Deleted 404");
					},this)
				});
				
				return false;
			});
		}

		function save() {
			$.secureAjax("<?=ADMIN_ROOT?>ajax/dashboard/404/update/", {
				data: { id: CurrentField, value: $("#404_" + CurrentField).val() }, 
				type: "POST"
			});
		}

		function search() {
			$("#results").load("<?=ADMIN_ROOT?>ajax/dashboard/404/search/", { search: $("#404_search").val(), type: "<?=$type?>" }, hooks);	
		}

		$("#404_search").keyup(function() {
			clearTimeout(SearchTimer);
			SearchTimer = setTimeout(search, 400);
		});
	
		$(".table").on("click","#view_paging a",function() {
			if ($(this).hasClass("active") || $(this).hasClass("disabled")) {
				return false;
			}

			$("#results").load("<?=ADMIN_ROOT?>ajax/dashboard/404/search/", {
				search: $("#404_search").val(), 
				type: "<?=$type?>", 
				page: BigTree.cleanHref($(this).attr("href")) 
			}, hooks);
	
			return false;
		});

		$("#site_key_switcher").change(function() {
			$("#results").load("<?=ADMIN_ROOT?>ajax/dashboard/404/search/", {
				search: $("#404_search").val(), 
				type: "<?=$type?>", 
				site_key: $(this).val() 
			}, hooks);
		});

		hooks();
	})();
</script>