<script>
	BigTree.localSearchTimer = false;

	$("#search").keyup(function() {
		clearTimeout(BigTree.localSearchTimer);
		BigTree.localSearchTimer = setTimeout("BigTree.localSearch();",400);
	});
	
	$(".table").on("click",".icon_delete",function() {
		new BigTreeDialog("Delete Item",'<p class="confirm">Are you sure you want to delete this item?</p>',$.proxy(function() {
			$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/delete/?view=<?=$bigtree["view"]["id"]?>&id=" + BigTree.CleanHref($(this).attr("href")));
		},this),"delete",false,"OK");
		return false;
	}).on("click",".icon_approve",function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/approve/?view=<?=$bigtree["view"]["id"]?>&id=" + BigTree.CleanHref($(this).attr("href")));
		$(this).toggleClass("icon_approve_on");
		return false;
	}).on("click",".icon_feature",function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/feature/?view=<?=$bigtree["view"]["id"]?>&id=" + BigTree.CleanHref($(this).attr("href")));
		$(this).toggleClass("icon_feature_on");
		return false;
	}).on("click",".icon_archive",function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/archive/?view=<?=$bigtree["view"]["id"]?>&id=" + BigTree.CleanHref($(this).attr("href")));
		$(this).toggleClass("icon_archive_on");
		return false;
	}).on("click",".icon_disabled",function() { return false; });
</script>