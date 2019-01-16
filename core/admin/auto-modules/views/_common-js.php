<?php
	namespace BigTree;
	
	/**
	 * @global ModuleView $view
	 */
?>
<script>
	(function() {
		var Current = false;
		var SearchTimer;

		$("#search").keyup(function() {
			clearTimeout(SearchTimer);
			SearchTimer = setTimeout("BigTree.localSearch();",400);
		});
		
		$(".table").on("click",".js-hook-delete",function() {
			Current = $(this);
			BigTreeDialog({
				title: "<?=Text::translate("Delete Item", true)?>",
				content: '<p class="confirm"><?=Text::translate("Are you sure you want to delete this item?", true)?></p>',
				icon: "delete",
				alternateSaveText: "<?=Text::translate("OK", true)?>",
				callback: function() {
					// Allow custom delete implementations
					var href = BigTree.cleanHref(Current.attr("href"));

					// If it's just an ID, we're using the default delete implementation
					if (parseInt(href) || parseInt(href.substr(1))) {
						var row = Current.parents("li");
						var list = row.parents("ul");

						$.secureAjax("<?=ADMIN_ROOT?>ajax/auto-modules/views/delete/?view=<?=$view->ID?>&id=" + href);
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
		}).on("click",".js-hook-approve",function() {
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
		}).on("click",".js-hook-feature",function() {
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
		}).on("click",".js-hook-archive",function() {
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
		}).on("click",".js-hook-disabled",function() { return false; });
	})();
</script>