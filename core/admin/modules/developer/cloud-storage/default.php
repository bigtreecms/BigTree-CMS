<?php
	$storage = new BigTreeStorage;
?>
<div class="container">
	<summary><h2>Configure</h2></summary>
	<form method="post" action="<?=DEVELOPER_ROOT?>cloud-storage/set-default/">
		<?php $admin->drawCSRFToken() ?>
		<section>
			<div class="contain">
				<a class="box_select last_row<?php if ($cloud->Settings["amazon"]["active"]) { ?> connected<?php } ?>" href="<?=DEVELOPER_ROOT?>cloud-storage/amazon/">
					<span class="amazon"></span>
					<p>Amazon S3</p>
				</a>
				<a class="box_select last_row<?php if ($cloud->Settings["rackspace"]["active"]) { ?> connected<?php } ?>" href="<?=DEVELOPER_ROOT?>cloud-storage/rackspace/">
					<span class="rackspace"></span>
					<p>Rackspace Cloud Files</p>
				</a>
				<a class="box_select last_row<?php if ($cloud->Settings["google"]["active"]) { ?> connected<?php } ?>" href="<?=DEVELOPER_ROOT?>cloud-storage/google/">
					<span class="google"></span>
					<p>Google Cloud Storage</p>
				</a>
			</div>
			<hr />
			<fieldset>
				<label>Default Storage Service <small>(only connected services appear)</small></label>
				<select name="service">
					<option value="local">Local Storage</option>
					<?php
						if ($cloud->Settings["amazon"]["active"]) {
					?>
					<option value="amazon"<?php if ($storage->Settings->Service == "s3" || $storage->Settings->Service == "amazon") { ?> selected="selected"<?php } ?>>Amazon S3</option>
					<?php
						}
						if ($cloud->Settings["rackspace"]["active"]) {
					?>
					<option value="rackspace"<?php if ($storage->Settings->Service == "rackspace") { ?> selected="selected"<?php } ?>>Rackspace Cloud Files</option>
					<?php
						}
						if ($cloud->Settings["google"]["active"]) {
					?>
					<option value="google"<?php if ($storage->Settings->Service == "google") { ?> selected="selected"<?php } ?>>Google Cloud Storage</option>
					<?php
						}
					?>
				</select>
			</fieldset>
		</section>
		<footer>
			<input type="submit" value="Update" class="button blue" />
		</footer>
	</form>
</div>