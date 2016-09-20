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

						$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/delete/?view=<?=$view->ID?>&id=" + href);
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
			$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/approve/?view=<?=$view->ID?>&id=" + BigTree.cleanHref($(this).attr("href")));
			<?php if (($view->Type == "grouped" || $view->Type == "images-grouped") && $view->Settings["group_field"] == "approved") { ?>
			BigTree.localSearch();
			<?php } else { ?>
			$(this).toggleClass("icon_approve_on");
			<?php } ?>
			return false;
		}).on("click",".js-hook-feature",function() {
			$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/feature/?view=<?=$view->ID?>&id=" + BigTree.cleanHref($(this).attr("href")));
			<?php if (($view->Type == "grouped" || $view->Type == "images-grouped") && $view->Settings["group_field"] == "featured") { ?>
			BigTree.localSearch();
			<?php } else { ?>
			$(this).toggleClass("icon_feature_on");
			<?php } ?>
			return false;
		}).on("click",".js-hook-archive",function() {
			$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/archive/?view=<?=$view->ID?>&id=" + BigTree.cleanHref($(this).attr("href")));
			<?php if (($view->Type == "grouped" || $view->Type == "images-grouped") && $view->Settings["group_field"] == "archived") { ?>
			BigTree.localSearch();
			<?php } else { ?>
			$(this).toggleClass("icon_archive_on");
			<?php } ?>
			return false;
		}).on("click",".js-hook-disabled",function() { return false; });
	})();
</script>