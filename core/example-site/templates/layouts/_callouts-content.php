<?php
	if (is_array($callouts_content) && count($callouts_content)) {
?>
<div class="callouts_content">
	<?php
		foreach ($callouts_content as $callout) {
			include SERVER_ROOT."templates/callouts/" . $callout["type"] . ".php";
		}
	?>
</div>
<?php
	}
?>