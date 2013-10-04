<?
	$ups = $cms->getSetting("bigtree-internal-storage");
	if ($ups["s3"]["keys"]) {
		$access_key_id = htmlspecialchars($ups["s3"]["keys"]["access_key_id"]);
		$secret_access_key = htmlspecialchars($ups["s3"]["keys"]["secret_access_key"]);
		$bucket = htmlspecialchars($ups["s3"]["bucket"]);
	}
?>
<div class="container">
	<form method="post" action="<?=ADMIN_ROOT?>developer/cloud-storage/amazon/update/" class="module">
		<section>
			<div class="alert">
				<p>To enable usage of Amazon S3 for all BigTree uploads enter your access keys below.<br />Please note that this change is not retroactive -- only future uploads will be stored on Amazon S3.</p>
			</div>	
			<fieldset>
				<label>Access Key ID</label>
				<input type="text" name="access_key_id" value="<?=$access_key_id?>" />
			</fieldset>
			<fieldset>
				<label>Secret Access Key</label>
				<input type="text" name="secret_access_key" value="<?=$secret_access_key?>" />
			</fieldset>
			<fieldset>
				<label>Bucket Name <small>(if this is left empty, BigTree will create its own)</small></label>
				<input type="text" name="bucket" value="<?=$bucket?>" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>