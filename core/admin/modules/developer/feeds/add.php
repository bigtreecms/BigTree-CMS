<?php
	namespace BigTree;
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>feeds/create/" class="module">
		<?php Router::includeFile("admin/modules/developer/feeds/_form-content.php") ?>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>

<?php Router::includeFile("admin/modules/developer/feeds/_common-js.php") ?>