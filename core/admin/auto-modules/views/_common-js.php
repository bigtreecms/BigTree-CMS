<script>
	(function() {
		var Current = false;
		var SearchTimer = false;

		$("#search").keyup(function() {
			clearTimeout(SearchTimer);
			SearchTimer = setTimeout("BigTree.localSearch();",400);
		});

		$(".js-view-description-show").click(function() {
			var id = $(".js-view-description").show().data("id");

			$(this).hide();
			$.cookie("bigtree_admin[ignore_view_description][" + id + "]", "", { expires: 365, path: "/" });
		});

		$(".js-view-description-hide").click(function() {
			var id = $(this).parent().data("id");
			
			$.cookie("bigtree_admin[ignore_view_description][" + id + "]","on", { expires: 365, path: "/" });
			$(this).parent().hide();
			$(".js-view-description-show").show();
		});
		
		$(".table").on("click",".js-delete-hook",function() {
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
					if (parseInt(href) || parseInt(href.substr(1))) {
						$.secureAjax("<?=ADMIN_ROOT?>ajax/auto-modules/views/delete/?view=<?=$bigtree["view"]["id"]?>&id=" + href);
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
		}).on("click",".js-approve-hook",function() {
			<?php if (($bigtree["view"]["type"] == "grouped" || $bigtree["view"]["type"] == "images-grouped") && $bigtree["view"]["settings"]["group_field"] == "approved") { ?>
			$.secureAjax("<?=ADMIN_ROOT?>ajax/auto-modules/views/approve/?view=<?=$bigtree["view"]["id"]?>&id=" + BigTree.cleanHref($(this).attr("href"))).done(BigTree.localSearch);
			<?php } else { ?>
			$.secureAjax("<?=ADMIN_ROOT?>ajax/auto-modules/views/approve/?view=<?=$bigtree["view"]["id"]?>&id=" + BigTree.cleanHref($(this).attr("href")));
			
			if ($(this).hasClass("icon_approve_on")) {
				$(this).attr("title", "Approve");
			} else {
				$(this).attr("title", "Unapprove");
			}

			$(this).toggleClass("icon_approve_on");
			<?php } ?>
			return false;
		}).on("click",".js-feature-hook",function() {
			<?php if (($bigtree["view"]["type"] == "grouped" || $bigtree["view"]["type"] == "images-grouped") && $bigtree["view"]["settings"]["group_field"] == "featured") { ?>
			$.secureAjax("<?=ADMIN_ROOT?>ajax/auto-modules/views/feature/?view=<?=$bigtree["view"]["id"]?>&id=" + BigTree.cleanHref($(this).attr("href"))).done(BigTree.localSearch);
			<?php } else { ?>
			$.secureAjax("<?=ADMIN_ROOT?>ajax/auto-modules/views/feature/?view=<?=$bigtree["view"]["id"]?>&id=" + BigTree.cleanHref($(this).attr("href")));

			if ($(this).hasClass("icon_feature_on")) {
				$(this).attr("title", "Feature");
			} else {
				$(this).attr("title", "Unfeature");
			}
			
			$(this).toggleClass("icon_feature_on");
			<?php } ?>
			return false;
		}).on("click",".js-archive-hook",function() {
			<?php if (($bigtree["view"]["type"] == "grouped" || $bigtree["view"]["type"] == "images-grouped") && $bigtree["view"]["settings"]["group_field"] == "archived") { ?>
			$.secureAjax("<?=ADMIN_ROOT?>ajax/auto-modules/views/archive/?view=<?=$bigtree["view"]["id"]?>&id=" + BigTree.cleanHref($(this).attr("href"))).done(BigTree.localSearch);
			<?php } else { ?>
			$.secureAjax("<?=ADMIN_ROOT?>ajax/auto-modules/views/archive/?view=<?=$bigtree["view"]["id"]?>&id=" + BigTree.cleanHref($(this).attr("href")));

			if ($(this).hasClass("icon_archive_on")) {
				$(this).attr("title", "Archive");
			} else {
				$(this).attr("title", "Restore");
			}

			$(this).toggleClass("icon_archive_on");
			<?php } ?>
			return false;
		}).on("click",".js-disabled-hook",function() { return false; });
	})();
</script>