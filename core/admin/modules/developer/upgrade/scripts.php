<?php
	namespace BigTree;
	
	$current_revision = Setting::value("bigtree-internal-revision");
	$update_queue = [];
	
	if ($current_revision < 404) {
?>
<div class="container">
	<section>
		<div class="error_message">Please upgrade to BigTree 4.4.x before attempting a BigTree 5 upgrade.</div>
	</section>
</div>
<?php
	} else {
		
		while ($current_revision < BIGTREE_REVISION) {
			$current_revision++;
			
			if (file_exists(SERVER_ROOT."core/admin/ajax/developer/upgrade/revisions/$current_revision.php")) {
				$update_queue[] = "revisions/$current_revision/";
			}
		}
?>
<div class="container">
	<summary>
		<span class="button_loader"></span>
		<h2>BigTree Is Upgrading</h2>
	</summary>
	<section>
		<p class="upgrade_message">
			<?php
				if (count($update_queue) == 0) {
					echo "BigTree is up to date.";
				}
			?>
		</p>
	</section>
</div>
<?php
		if (count($update_queue)) {
?>
<script>
	(function() {
		var Index = 0;
		var MessageContainer = $(".upgrade_message");
		var Queue = <?=json_encode($update_queue)?>;

		function run_script(script, page, total_pages) {
			var data;

			if (typeof page !== "undefined") {
				data = {
					page: page,
					total_pages: total_pages
				};
			} else {
				data = {};
			}

			$.ajax("<?=ADMIN_ROOT?>ajax/developer/upgrade/" + script, {data: data}).done(function(json) {
				MessageContainer.html(json.response);

				if (json.error) {
					MessageContainer.html('<div class="error_message">An error occurred. Try refreshing this page to proceed with the upgrade.<br>Error: ' + json.error + '</div>');
				} else if (json.complete) {
					Index++;

					if (Index === Queue.length) {
						MessageContainer.html("<strong>Upgrade Complete.</strong>");
						$(".container .button_loader").remove();
					} else {
						run_script(Queue[Index]);
					}
				} else {
					if (json.pages) {
						run_script(Queue[Index], 1, json.pages);
					} else {
						run_script(Queue[Index], page + 1, total_pages);
					}
				}
			}).fail(function (xhr, status) {
				MessageContainer.html('<div class="error_message">An error occurred (most likely a timeout). Try refreshing this page to proceed with the upgrade.</div>');
			});
		}

		run_script(Queue[0]);
	})();
</script>
<?php
		}
	}
?>