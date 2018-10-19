<?php
	if (is_array($callouts_sidebar) && count($callouts_sidebar)) {
?>
<div class="page_sidebar callouts_sidebar">
	<?php
		foreach ($callouts_sidebar as $callout) {
			include SERVER_ROOT."templates/callouts/" . $callout["type"] . ".php";
		}
	?>
</div>
<?php
	}
?>