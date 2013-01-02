<?
	$keys = $cms->getSetting("bigtree-internal-s3-keys");
	BigTree::globalizeArray($keys,array("htmlspecialchars"));
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
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>