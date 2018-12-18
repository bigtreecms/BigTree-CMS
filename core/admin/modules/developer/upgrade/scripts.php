<?php
	$current_revision = $cms->getSetting("bigtree-internal-revision");
	$update_queue = [];

	if ($current_revision < 22) {
		$update_queue[] = "roll-up-scripts/beta-to-4.0/";
	}

	if ($current_revision < 100) {
		$update_queue[] = "roll-up-scripts/4.0-to-4.1/";
	}

	if ($current_revision < 200) {
		$update_queue[] = "roll-up-scripts/4.1-to-4.2/";
		$current_revision = 200;
	}

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
	var queue = <?=json_encode($update_queue)?>;
	var index = 0;

	function run_script(script, page, total_pages) {
		if (typeof page !== "undefined") {
			var data = {
				page: page,
				total_pages: total_pages
			};
		} else {
			var data = {};
		}

		$.ajax("<?=ADMIN_ROOT?>ajax/developer/upgrade/" + script, { data: data }).done(function(json) {
			$(".upgrade_message").html(json.response);

			if (json.error) {
				$(".upgrade_message").html('<div class="error_message">An error occurred. Try refreshing this page to proceed with the upgrade.<br>Error: ' + json.error + '</div>');
			} else if (json.complete) {
				index++;

				if (index == queue.length) {
					$(".upgrade_message").html("<strong>Upgrade Complete.</strong>");
					$(".container .button_loader").remove();
				} else {
					run_script(queue[index]);
				}
			} else {
				if (json.pages) {
					run_script(queue[index], 1, json.pages);
				} else {
					run_script(queue[index], page + 1, total_pages);
				}
			}
		}).fail(function(xhr, status) {
			$(".upgrade_message").html('<div class="error_message">An error occurred (most likely a timeout). Try refreshing this page to proceed with the upgrade.</div>');	
		});
	}

	run_script(queue[0]);
</script>
<?php
	}
?>