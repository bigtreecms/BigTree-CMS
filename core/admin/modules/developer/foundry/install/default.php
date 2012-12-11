<div class="container">
	<form method="post" action="<?=$developer_root?>foundry/install/unpack/" enctype="multipart/form-data">
		<input type="hidden" name="MAX_FILE_SIZE" value="<?=BigTree::uploadMaxFileSize()?>" />
		<section>
			<?
				if ($_SESSION["upload_error"]) {
			?>
			<p class="error_message"><?=$_SESSION["upload_error"]?></p>
			<?
					unset($_SESSION["upload_error"]);
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