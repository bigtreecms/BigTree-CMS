<?php
	namespace BigTree;
?>
<div class="container">
	<?php
		if (!$api->Connected) {
	?>
	<form method="post" action="<?=DEVELOPER_ROOT?>services/<?=$route?>/activate/" class="module">	
		<section>
			<p><?=Text::translate("To activate the")?> <?=$name?> <?=Text::translate("API class you must follow these steps:")?></p>
			<hr />
			<?php if ($name == "YouTube" || $name == "Google+") { ?>
			<p class="notice_message"><?=Text::translate("Google's Developer Console changes frequently, these steps may not be up to date.")?></p>
			<?php } ?>
			<ol>
				<?php foreach ($instructions as $i) { ?>
				<li><?=Text::translate($i)?></li>
				<?php } ?>
			</ol>
			<hr />
			<fieldset>
				<label><?=Text::translate($key_name)?></label>
				<input type="text" name="key" value="<?=htmlspecialchars($api->Settings["key"])?>" />
			</fieldset>
			<fieldset>
				<label><?=Text::translate($secret_name)?></label>
				<input type="text" name="secret" value="<?=htmlspecialchars($api->Settings["secret"])?>" />
			</fieldset>
			<?php
				if ($scope_default) {
			?>
			<fieldset>
				<label><?=Text::translate("Scope")?><?php if ($scope_help) { echo $scope_help; } ?></label>
				<input type="text" name="scope" value="<?=htmlspecialchars($api->Settings["scope"] ? $api->Settings["scope"] : $scope_default)?>" />
			</fieldset>
			<?php
				}
				if ($show_test_environment) {
			?>
			<fieldset>
				<input name="test_environment" type="checkbox"<?php if ($api->Settings["test_environment"]) { ?> checked="checked"<?php } ?> />
				<label class="for_checkbox"><?=Text::translate("Use Test Environment")?></label>
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
			<strong><?=Text::translate("Test Environment")?></strong> - <?=Text::translate("Remember to reconnect to live service before launch)")?>
			<?php } ?>
		</p>
		<?php } ?>
	</section>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>services/<?=$route?>/disconnect/" class="button red"><?=Text::translate("Disconnect")?></a>
	</footer>
	<?php
		}
	?>
</div>