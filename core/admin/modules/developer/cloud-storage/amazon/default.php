<?
	if (isset($cloud->Settings["amazon"])) {
		BigTree::globalizeArray($cloud->Settings["amazon"],"htmlspecialchars");
	} else {
		$key = $secret = "";
	}
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>cloud-storage/amazon/update/" class="module">
		<section>
			<div class="alert">
				<p>To enable usage of Amazon S3 for all BigTree uploads enter your access keys below.<br />Please note that this change is not retroactive -- only future uploads will be stored on Amazon S3.</p>
			</div>	
			<fieldset>
				<label>Access Key ID</label>
				<input type="text" name="key" value="<?=$key?>" />
			</fieldset>
			<fieldset>
				<label>Secret Access Key</label>
				<input type="text" name="secret" value="<?=$secret?>" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>