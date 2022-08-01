<?php
	if (!empty($callouts_full) && is_array($callouts_full)) {
?>
<div class="callouts_full">
	<?php
		foreach ($callouts_full as $callout) {
			include SERVER_ROOT."templates/callouts/" . $callout["type"] . ".php";
		}
	?>
</div>
<?php
	}
?>