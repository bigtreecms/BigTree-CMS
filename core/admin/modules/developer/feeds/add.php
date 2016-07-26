<?php
	namespace BigTree;
	
	$feed = new Feed;
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>feeds/create/" class="module">
		<?php include Router::getIncludePath("admin/modules/developer/feeds/_form-content.php") ?>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Create", true)?>" />
		</footer>
	</form>
</div>

<?php include Router::getIncludePath("admin/modules/developer/feeds/_common-js.php") ?>