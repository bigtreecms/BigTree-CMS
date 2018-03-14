<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>feeds/create/" class="module">
		<?php include BigTree::path("admin/modules/developer/feeds/_form-content.php"); ?>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>

<?php include BigTree::path("admin/modules/developer/feeds/_common-js.php"); ?>