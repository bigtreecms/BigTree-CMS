<?php
	namespace BigTree;
	
	// Check writability of directories
	$directories_to_check = [
		"cache/",
		"extensions/",
		"site/extensions/"
	];
	
	foreach ($directories_to_check as $directory) {
		if (!is_writable(SERVER_ROOT.$directory)) {
			Auth::stop("Your <code>/$directory</code> directory must be writable to install extensions.",
						 Router::getIncludePath("admin/layouts/_error.php"));
		}
	}
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>extensions/install/unpack/" enctype="multipart/form-data">
	  <?php CSRF::drawPOSTToken(); ?>
		<input type="hidden" name="MAX_FILE_SIZE" value="<?=Storage::getUploadMaxFileSize()?>" />
		<input type="hidden" name="_bigtree_post_check" value="success" />
		<section>
			<?php
				if ($_SESSION["upload_error"]) {
			?>
			<p class="error_message"><?=Text::translate($_SESSION["upload_error"])?></p>
			<?php
					unset($_SESSION["upload_error"]);
				}

				Utils::drawPOSTErrorMessage();
			?>
			<fieldset>
				<label><?=Text::translate("Extension")?></label>
				<input type="file" name="file" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Unpack", true)?>" />
		</footer>
	</form>
</div>