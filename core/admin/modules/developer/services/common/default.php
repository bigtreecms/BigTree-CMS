<?php
	namespace BigTree;
	
	/**
	 * @global array $instructions
	 * @global bool $show_test_environment
	 * @global OAuth $api
	 * @global string $key_name
	 * @global string $name
	 * @global string $route
	 * @global string $secret_name
	 * @global string $scope_default
	 * @global string $scope_help
	 */
?>
<div class="container">
	<?php
		if (!$api->Connected) {
	?>
	<form method="post" action="<?=DEVELOPER_ROOT?>services/<?=$route?>/activate/" class="module">
		<?php CSRF::drawPOSTToken(); ?>
		<section>
			<p><?=Text::translate("To activate the :api_name: API class you must follow these steps:", false, array(":api_name:" => $name))?></p>
			<hr />
			<?php if ($name == "YouTube" || $name == "Google+") { ?>
			<p class="notice_message"><?=Text::translate("Google's Developer Console changes frequently, these steps may not be up to date.")?></p>
			<?php } ?>
			<ol>
				<?php foreach ($instructions as $instruction) { ?>
				<li><?=$instruction?></li>
				<?php } ?>
			</ol>
			<hr />
			<fieldset>
				<label for="oauth_field_key"><?=Text::translate($key_name)?></label>
				<input id="oauth_field_key" type="text" name="key" value="<?=htmlspecialchars($api->Settings["key"])?>" />
			</fieldset>
			<fieldset>
				<label for="oauth_field_secret"><?=Text::translate($secret_name)?></label>
				<input id="oauth_field_secret" type="text" name="secret" value="<?=htmlspecialchars($api->Settings["secret"])?>" />
			</fieldset>
			<?php
				if ($scope_default) {
			?>
			<fieldset>
				<label for="oauth_field_scope"><?=Text::translate("Scope")?><?php if ($scope_help) { echo $scope_help; } ?></label>
				<input id="oauth_field_scope" type="text" name="scope" value="<?=htmlspecialchars($api->Settings["scope"] ? $api->Settings["scope"] : $scope_default)?>" />
			</fieldset>
			<?php
				}
				
				if ($show_test_environment) {
			?>
			<fieldset>
				<input id="oauth_field_test_environment" name="test_environment" type="checkbox"<?php if ($api->Settings["test_environment"]) { ?> checked="checked"<?php } ?> />
				<label for="oauth_field_test_environment" class="for_checkbox"><?=Text::translate("Use Test Environment")?></label>
			</fieldset>
			<?php
				}
			?>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Activate", true)?> <?=$name?> <?=Text::translate("API", true)?>" />
		</footer>
	</form>
	<?php
		} else {
	?>
	<section>
		<?php if ($api->Settings["user_image"]) { ?>
		<p><?=Text::translate("Currently connected as:")?></p>
		<div class="api_account_block">
			<img src="<?=$api->Settings["user_image"]?>" class="gravatar" />
			<strong><?=$api->Settings["user_name"]?></strong>
			#<?=$api->Settings["user_id"]?>
		</div>
		<?php } else { ?>
		<p>
			<?=Text::translate("Currently connected to your account.")?>
			<?php if ($api->Settings["test_environment"]) { ?>
			<br />
			<?=Text::translate("<strong>Test Environment</strong> - Remember to reconnect to live service before launch")?>
			<?php } ?>
		</p>
		<?php } ?>
	</section>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>services/<?=$route?>/disconnect/?true<?php CSRF::drawGETToken(); ?>" class="button red"><?=Text::translate("Disconnect")?></a>
	</footer>
	<?php
		}
	?>
</div>