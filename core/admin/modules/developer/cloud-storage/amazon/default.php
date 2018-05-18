<?php
	if (isset($cloud->Settings["amazon"])) {
		BigTree::globalizeArray($cloud->Settings["amazon"],"htmlspecialchars");
	} else {
		$key = $secret = "";
	}
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>cloud-storage/amazon/update/" class="module">
		<?php $admin->drawCSRFToken() ?>
		<section>
			<div class="alert">
				<p>
					To enable usage of Amazon S3 for all BigTree uploads enter your access keys below.<br />
					Please note that this change is not retroactive -- only future uploads will be stored on Amazon S3.
				</p>
			</div>
			<fieldset>
				<label for="s3_field_region">AWS Region <small>(if you are unsure of the region, leave the default)</small></label>
				<select id="s3_field_region" name="region">
					<?php
						foreach ($cloud->AWSRegions as $code => $name) {
					?>
					<option value="<?=$code?>"<?php if ($code == $region) { ?> selected="selected"<?php } ?>><?=$name?></option>
					<?php
						}
					?>
				</select>
			</fieldset>
			<fieldset>
				<label for="s3_field_key">Access Key ID</label>
				<input id="s3_field_key" type="text" name="key" value="<?=$key?>" />
			</fieldset>
			<fieldset>
				<label for="s3_field_secret">Secret Access Key</label>
				<input id="s3_field_secret" type="text" name="secret" value="<?=$secret?>" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>