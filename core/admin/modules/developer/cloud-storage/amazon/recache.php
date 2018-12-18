<?php
	if (empty($_GET["marker"])) {
		SQL::delete("bigtree_caches", ["identifier" => "org.bigtreecms.cloudfiles"]);
	}
?>
<div class="container">
	<summary>
		<span class="button_loader"></span>
		<h2>Caching existing Amazon S3 file database.</h2>
	</summary>
	<section>
		<p class="cache_message">Please wait while BigTree caches existing filenames to prevent inadvertantly overwriting existing files.</p>
	</section>
</div>

<script>
	var marker = "<?=(!empty($_GET["marker"]) ? $_GET["marler"] : "")?>";

	function cache_page(marker) {
		$.ajax("<?=ADMIN_ROOT?>ajax/developer/amazon-cache/", { data: { marker: marker } }).done(function(json) {
			window.history.replaceState({}, "", "?marker=" + escape(marker));

			$(".cache_message").html(json.response);

			if (json.error) {
				$(".cache_message").html('<div class="error_message">An error occurred. Try refreshing this page to proceed with the caching process. <strong>Error: ' + json.error + '</strong></div>');
			} else if (json.complete) {
				window.location.href = "<?=DEVELOPER_ROOT?>cloud-storage/amazon/recache-complete/";
			} else {
				cache_page(json.marker);
			}
		}).fail(function(xhr, status) {
			$(".cache_message").html('<div class="error_message">An error occurred (most likely a timeout). Try refreshing this page to proceed with the caching process.</div>');	
		});
	}

	cache_page(marker);
</script>