<? if ($pages) { ?>
<script type="text/javascript">
	var pages = [<? echo implode(",",$pages) ?>];
	
	var total,current;
	total = pages.length;
	current = 0;
	
	function download_page() {
		$.ajax("<?=$admin_root?>ajax/dashboard/check-page-integrity/?external=<?=$external?>&id=" + pages[current], {
			complete: function(response) {
				$("#updates").append(response);
				current = current + 1;
				$('#progress').html((Math.round(current / total * 10000) / 100) + "%");
				if (current < total) {
					download_page();
				} else {
					$("#progress").addClass("complete");
				}
			}
		});
	}

	download_page();
</script>
<? } ?>