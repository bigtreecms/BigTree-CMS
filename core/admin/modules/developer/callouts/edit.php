<?php
	namespace BigTree;
	
	$callout = new Callout(end($bigtree["path"]));	
	Globalize::arrayObject($callout->Array);
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>callouts/update/" enctype="multipart/form-data" class="module">
		<input type="hidden" name="id" value="<?=$callout->ID?>" />
		
		<?php include Router::getIncludePath("admin/modules/developer/callouts/_form-content.php") ?>
		
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />
		</footer>
	</form>
</div>

<?php include Router::getIncludePath("admin/modules/developer/callouts/_common-js.php") ?>