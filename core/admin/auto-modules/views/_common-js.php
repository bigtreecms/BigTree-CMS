<script>
	(function() {
		var Current = false;
		var SearchTimer = false;

		$("#search").keyup(function() {
			clearTimeout(SearchTimer);
			SearchTimer = setTimeout("BigTree.localSearch();",400);
		});
		
		$(".table").on("click",".icon_delete",function() {
			Current = $(this);
			BigTreeDialog({
				title: "Delete Item",
				content: '<p class="confirm">Are you sure you want to delete this item?</p>',
				icon: "delete",
				alternateSaveText: "OK",
				callback: function() {
					// Allow custom delete implementations
					var href = BigTree.cleanHref(Current.attr("href"));
					// If it's just an ID, we're using the default delete implementation
					if (parseInt(href)) {
						$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/delete/?view=<?=$bigtree["view"]["id"]?>&id=" + href);
						var row = Current.parents("li");
						var list = row.parents("ul");
						row.remove();
						if (!list.find("li").length) {
							var header = list.prev();
							if (header.hasClass("group")) {
								header.remove();
								list.remove();
							}
						}
					} else {
						document.location.href = href;
					}
				}
			});
	
			return false;
		}).on("click",".icon_approve",function() {
			$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/approve/?view=<?=$bigtree["view"]["id"]?>&id=" + BigTree.cleanHref($(this).attr("href")));
			$(this).toggleClass("icon_approve_on");
			return false;
		}).on("click",".icon_feature",function() {
			$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/feature/?view=<?=$bigtree["view"]["id"]?>&id=" + BigTree.cleanHref($(this).attr("href")));
			$(this).toggleClass("icon_feature_on");
			return false;
		}).on("click",".icon_archive",function() {
			$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/archive/?view=<?=$bigtree["view"]["id"]?>&id=" + BigTree.cleanHref($(this).attr("href")));
			$(this).toggleClass("icon_archive_on");
			return false;
		}).on("click",".icon_disabled",function() { return false; });
	})();
</script>