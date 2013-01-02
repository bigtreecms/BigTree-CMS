<script>
	var deleteConfirm,deleteTimer,deleteId,searchTimer;

	$("#search").keyup(function() {
		clearTimeout(searchTimer);
		searchTimer = setTimeout("_local_search();",400);
	});
			
	$(".icon_delete").live("click",function() {
		new BigTreeDialog("Delete Item",'<p class="confirm">Are you sure you want to delete this item?</p>',$.proxy(function() {
			$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/delete/?view=<?=$view["id"]?>&id=" + BigTree.CleanHref($(this).attr("href")));
		},this),"delete",false,"OK");

		return false;
	});

	$(".icon_approve").live("click",function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/approve/?view=<?=$view["id"]?>&id=" + BigTree.CleanHref($(this).attr("href")));
		$(this).toggleClass("icon_approve_on");
		return false;
	});

	$(".icon_feature").live("click",function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/feature/?view=<?=$view["id"]?>&id=" + BigTree.CleanHref($(this).attr("href")));
		$(this).toggleClass("icon_feature_on");
		return false;
	});

	$(".icon_archive").live("click",function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/archive/?view=<?=$view["id"]?>&id=" + BigTree.CleanHref($(this).attr("href")));
		$(this).toggleClass("icon_archive_on");
		return false;
	});

	$(".icon_disabled").live("click",function() { return false; });
</script>