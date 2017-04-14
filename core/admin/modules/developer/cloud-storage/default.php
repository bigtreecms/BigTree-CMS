<?php
	namespace BigTree;
	
	/**
	 * @global CloudStorage\Amazon $amazon
	 * @global CloudStorage\Google $google
	 * @global CloudStorage\Rackspace $rackspace
	 * @global Storage $storage
	 */
?>
<div class="container">
	<div class="container_summary"><h2><?=Text::translate("Configure")?></h2></div>
	<form method="post" action="<?=DEVELOPER_ROOT?>cloud-storage/set-default/">
		<?php CSRF::drawPOSTToken(); ?>
		<section>
			<div class="contain">
				<a class="box_select last_row<?php if ($amazon->Active) { ?> connected<?php } ?>" href="<?=DEVELOPER_ROOT?>cloud-storage/amazon/">
					<span class="amazon"></span>
					<p>Amazon S3</p>
				</a>
				<a class="box_select last_row<?php if ($rackspace->Active) { ?> connected<?php } ?>" href="<?=DEVELOPER_ROOT?>cloud-storage/rackspace/">
					<span class="rackspace"></span>
					<p>Rackspace Cloud Files</p>
				</a>
				<a class="box_select last_row<?php if ($google->Active) { ?> connected<?php } ?>" href="<?=DEVELOPER_ROOT?>cloud-storage/google/">
					<span class="google"></span>
					<p>Google Cloud Storage</p>
				</a>
			</div>
			<hr />
			<fieldset>
				<label for="storage_field_service"><?=Text::translate("Default Storage Service")?> <small>(<?=Text::translate("only connected services appear")?>)</small></label>
				<select id="storage_field_service" name="service">
					<option value="local"><?=Text::translate("Local Storage")?></option>
					<?php
						if ($amazon->Active) {
					?>
					<option value="amazon"<?php if ($storage->Settings["Service"] == "s3" || $storage->Settings["Service"] == "amazon") { ?> selected="selected"<?php } ?>>Amazon S3</option>
					<?php
						}
						if ($rackspace->Active) {
					?>
					<option value="rackspace"<?php if ($storage->Settings["Service"] == "rackspace") { ?> selected="selected"<?php } ?>>Rackspace Cloud Files</option>
					<?php
						}
						if ($google->Active) {
					?>
					<option value="google"<?php if ($storage->Settings["Service"] == "google") { ?> selected="selected"<?php } ?>>Google Cloud Storage</option>
					<?php
						}
					?>
				</select>
			</fieldset>
		</section>
		<footer>
			<input type="submit" value="<?=Text::translate("Update", true)?>" class="button blue" />
		</footer>
	</form>
</div>