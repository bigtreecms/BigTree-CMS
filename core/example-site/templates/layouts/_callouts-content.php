<?php
	if (!empty($callouts_content) && is_array($callouts_content)) {
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