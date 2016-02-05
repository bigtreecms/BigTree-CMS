<?
	// Load field types requested CSS and JS if it's not already loaded and then run BigTree's ready call
?>
<script>
	var Head = $("head");
	<?
		if (count($bigtree["css"])) {
	?>
	jQuery.each(<?=json_encode($bigtree["css"])?>,function(index,css) {
		css = "<?=ADMIN_ROOT?>" + css;

		// See if it's already loaded
		var loaded = $("link[href='" + css + "']").length;
		if (!loaded) {
			var link = $('<link rel="stylesheet" type="text/css" media="screen">').attr("href",css);
			Head.append(link);
		}
	});
	<?
		}

		if (count($bigtree["js"])) {
	?>
	jQuery.each(<?=json_encode($bigtree["js"])?>,function(index,js) {
		js = "<?=ADMIN_ROOT?>" + js;

		// See if it's already loaded
		var loaded = $("script[src='" + js + "']").length;
		if (!loaded) {
			// Increment the wait counter to keep ready event from firing until we finish loading JS
			BigTree.ReadyCountdown++;

			$.getScript(js,function() {
				BigTree.ReadyCountdown--;
			});
		}
	});
	<?
		}
	?>

	BigTree.ready();
</script>