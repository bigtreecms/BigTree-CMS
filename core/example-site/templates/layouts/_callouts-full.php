<?php
	if (is_array($callouts_full) && count($callouts_full)) {
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