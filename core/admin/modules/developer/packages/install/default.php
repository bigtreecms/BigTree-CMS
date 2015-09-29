<?php
	if (!is_writable(SERVER_ROOT.'cache/')) {
	    ?>
<div class="container">
	<section>
		<h3>Error</h3>
		<p>Your <code>/cache/</code> directory must be writable.</p>
	</section>
</div>
<?php
		$admin->stop();
	}
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>packages/install/unpack/" enctype="multipart/form-data">
		<input type="hidden" name="MAX_FILE_SIZE" value="<?=BigTree::uploadMaxFileSize()?>" />
		<input type="hidden" name="_bigtree_post_check" value="success" />
		<section>
			<?php
				if ($_SESSION['upload_error']) {
				    ?>
			<p class="error_message"><?=$_SESSION['upload_error']?></p>
			<?php
					unset($_SESSION['upload_error']);
				}

				if ($_SESSION['bigtree_admin']['post_max_hit']) {
				    unset($_SESSION['bigtree_admin']['post_max_hit']);
				    ?>
			<p class="warning_message">The file(s) uploaded exceeded the web server's maximum upload size. If you uploaded multiple files, try uploading one at a time.</p>
			<?php

				}
			?>
			<fieldset>
				<label>Package</label>
				<input type="file" name="file" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Unpack" />
		</footer>
	</form>
</div>