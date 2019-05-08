<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$feed = new Feed(end(Router::$Commands));
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>feeds/update/<?=$feed->ID?>/" class="module">
		<?php include Router::getIncludePath("admin/modules/developer/feeds/_form-content.php") ?>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />
		</footer>
	</form>
</div>

<?php include Router::getIncludePath("admin/modules/developer/feeds/_common-js.php") ?>