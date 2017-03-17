<?
	// Check writability of directories
	$directories_to_check = array(
		"cache/",
		"extensions/",
		"site/extensions/"
	);
	foreach ($directories_to_check as $directory) {
		if (!is_writable(SERVER_ROOT.$directory)) {
?>
<div class="container">
	<section>
		<h3>Error</h3>
		<p>Your <code>/<?=$directory?></code> directory must be writable to install extensions.</p>
	</section>
</div>
<?
			$admin->stop();
		}
	}
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>extensions/install/unpack/" enctype="multipart/form-data">
		<? $admin->drawCSRFToken() ?>
		<input type="hidden" name="MAX_FILE_SIZE" value="<?=BigTree::uploadMaxFileSize()?>" />
		<input type="hidden" name="_bigtree_post_check" value="success" />
		<section>
			<?
				if ($_SESSION["upload_error"]) {
			?>
			<p class="error_message"><?=$_SESSION["upload_error"]?></p>
			<?
					unset($_SESSION["upload_error"]);
				}
				
				if ($_SESSION["bigtree_admin"]["post_max_hit"]) {
					unset($_SESSION["bigtree_admin"]["post_max_hit"]);
			?>
			<p class="warning_message">The file(s) uploaded exceeded the web server's maximum upload size. If you uploaded multiple files, try uploading one at a time.</p>
			<?
				}
			?>
			<fieldset>
				<label>Extension</label>
				<input type="file" name="file" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Unpack" />
		</footer>
	</form>
</div>