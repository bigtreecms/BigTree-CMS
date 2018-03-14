<div class="container">
	<?php
		if (!$api->Connected) {
	?>
	<form method="post" action="<?=DEVELOPER_ROOT?>services/<?=$route?>/activate/" class="module">	
		<?php $admin->drawCSRFToken() ?>
		<section>
			<p>To activate the <?=$name?> API class you must follow these steps:</p>
			<hr />
			<ol>
				<?php foreach ($instructions as $i) { ?>
				<li><?=$i?></li>
				<?php } ?>
			</ol>
			<hr />
			<fieldset>
				<label><?=$key_name?></label>
				<input type="text" name="key" value="<?=htmlspecialchars($api->Settings["key"])?>" />
			</fieldset>
			<fieldset>
				<label><?=$secret_name?></label>
				<input type="text" name="secret" value="<?=htmlspecialchars($api->Settings["secret"])?>" />
			</fieldset>
			<?php
				if ($scope_default) {
			?>
			<fieldset>
				<label>Scope<?php if ($scope_help) { echo $scope_help; } ?></label>
				<input type="text" name="scope" value="<?=htmlspecialchars($api->Settings["scope"] ? $api->Settings["scope"] : $scope_default)?>" />
			</fieldset>
			<?php
				}
				if ($show_test_environment) {
			?>
			<fieldset>
				<input name="test_environment" type="checkbox"<?php if ($api->Settings["test_environment"]) { ?> checked="checked"<?php } ?> />
				<label class="for_checkbox">Use Test Environment</label>
			</fieldset>
			<?php
				}
			?>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Activate <?=$name?> API" />
		</footer>
	</form>
	<?php
		} else {
	?>
	<section>
		<?php if ($api->Settings["user_image"]) { ?>
		<p>Currently connected as:</p>
		<div class="api_account_block">
			<img src="<?=$api->Settings["user_image"]?>" class="gravatar" />
			<strong><?=$api->Settings["user_name"]?></strong>
			#<?=$api->Settings["user_id"]?>
		</div>
		<?php } else { ?>
		<p>
			Currently connected to your account.
			<?php if ($api->Settings["test_environment"]) { ?>
			<br />
			<strong>Test Environment</strong> - Remember to reconnect to live service before launch)
			<?php } ?>
		</p>
		<?php } ?>
	</section>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>services/<?=$route?>/disconnect/?true<?php $admin->drawCSRFTokenGET() ?>" class="button red">Disconnect</a>
	</footer>
	<?php
		}
	?>
</div>